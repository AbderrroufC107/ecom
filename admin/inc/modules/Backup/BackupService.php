<?php

namespace Ecom\Backup;

use PDO;
use RuntimeException;
use Ecom\Queue\QueueService;

class BackupService
{
    public static function getTypeLabels(): array
    {
        return ['database' => 'Database', 'files' => 'Files', 'full' => 'Full Backup'];
    }

    public static function getScopeLabels(): array
    {
        return ['global' => 'Global', 'store' => 'Single Store', 'selected_tables' => 'Selected Tables'];
    }

    public static function getStatusLabels(): array
    {
        return ['pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed'];
    }

    public static function getStoragePath(): string
    {
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'backups';
    }

    public static function createJob(PDO $pdo, string $type, string $scope = 'global',
        ?int $storeId = null, ?array $selectedTables = null): int
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_backup_job (store_id, type, scope, selected_tables, status)
            VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([
            $storeId,
            $type,
            $scope,
            $selectedTables ? json_encode($selectedTables) : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function getJob(PDO $pdo, int $backupId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_backup_job WHERE id = ?");
        $stmt->execute([$backupId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateJob(PDO $pdo, int $backupId, array $data): void
    {
        $allowed = ['status', 'file_path', 'file_size', 'checksum', 'storage_location',
            's3_key', 'started_at', 'completed_at', 'error_message'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $backupId;
        $stmt = $pdo->prepare("UPDATE tbl_backup_job SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    }

    public static function getJobs(PDO $pdo, int $page = 1, int $perPage = 20,
        ?string $typeFilter = null, ?string $statusFilter = null): array
    {
        $where = '1=1';
        $params = [];
        if ($typeFilter) {
            $where .= ' AND b.type = ?';
            $params[] = $typeFilter;
        }
        if ($statusFilter) {
            $where .= ' AND b.status = ?';
            $params[] = $statusFilter;
        }
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT b.*, s.name AS store_name, s.slug AS store_slug
            FROM tbl_backup_job b
            LEFT JOIN tbl_stores s ON b.store_id = s.id
            WHERE {$where} ORDER BY b.id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function getJobCount(PDO $pdo, ?string $typeFilter = null, ?string $statusFilter = null): int
    {
        $where = '1=1';
        $params = [];
        if ($typeFilter) {
            $where .= ' AND type = ?';
            $params[] = $typeFilter;
        }
        if ($statusFilter) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_backup_job WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function deleteJob(PDO $pdo, int $backupId): void
    {
        $job = self::getJob($pdo, $backupId);
        if ($job && $job['file_path'] && file_exists($job['file_path'])) {
            unlink($job['file_path']);
        }
        $stmt = $pdo->prepare("DELETE FROM tbl_backup_job WHERE id = ?");
        $stmt->execute([$backupId]);
    }

    public static function writeLog(PDO $pdo, int $backupId, string $message, ?int $storeId = null,
        string $level = 'info'): void
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_backup_log (backup_id, store_id, log_level, message, created_at)
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$backupId, $storeId, $level, $message]);
    }

    public static function getLogs(PDO $pdo, int $backupId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_backup_log WHERE backup_id = ? ORDER BY id ASC");
        $stmt->execute([$backupId]);
        return $stmt->fetchAll();
    }

    public static function getConfig(PDO $pdo, string $key, mixed $default = null): mixed
    {
        $stmt = $pdo->prepare("SELECT config_value FROM tbl_backup_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['config_value'] : $default;
    }

    public static function setConfig(PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_backup_config (config_key, config_value)
            VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
        $stmt->execute([$key, $value]);
    }

    public static function getAllConfigs(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT config_key, config_value FROM tbl_backup_config");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['config_key']] = $row['config_value'];
        }
        return $result;
    }

    public static function executeDatabaseBackup(PDO $pdo, int $backupId, ?int $storeId = null,
        ?array $selectedTables = null): string
    {
        $backupDir = self::getStoragePath() . DIRECTORY_SEPARATOR . 'database';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $prefix = $storeId ? "store_{$storeId}" : 'global';
        $filename = "db_backup_{$prefix}_{$timestamp}.sql.gz";
        $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? DB_NAME : 'ecom';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;

        $tables = '';
        if (!empty($selectedTables)) {
            $tables = implode(' ', array_map('trim', $selectedTables));
        } elseif ($storeId) {
            $tables = '--where="store_id=' . (int) $storeId . '"';
        } else {
            $tables = '';
        }

        $cmd = sprintf(
            'mysqldump --host=%s --port=%d --user=%s --password=%s --single-transaction --routines --triggers %s %s | gzip > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($name),
            $tables,
            escapeshellarg($filepath)
        );

        self::writeLog($pdo, $backupId, "Starting database backup: {$filename}");
        self::updateJob($pdo, $backupId, ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]);

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            self::writeLog($pdo, $backupId, "Backup failed: {$error}", null, 'error');
            self::updateJob($pdo, $backupId, ['status' => 'failed', 'error_message' => $error]);
            throw new RuntimeException("Database backup failed: {$error}");
        }

        if (!file_exists($filepath)) {
            self::updateJob($pdo, $backupId, ['status' => 'failed', 'error_message' => 'Output file not created']);
            throw new RuntimeException('Backup file was not created');
        }

        $fileSize = filesize($filepath);
        $checksum = hash_file('sha256', $filepath);

        self::updateJob($pdo, $backupId, [
            'status'           => 'completed',
            'file_path'        => $filepath,
            'file_size'        => $fileSize,
            'checksum'         => $checksum,
            'storage_location' => 'local',
            'completed_at'     => date('Y-m-d H:i:s'),
        ]);

        self::writeLog($pdo, $backupId, "Backup completed: {$filename} (" . self::formatBytes($fileSize) . ")");

        return $filepath;
    }

    public static function executeFilesBackup(PDO $pdo, int $backupId, ?int $storeId = null): string
    {
        $backupDir = self::getStoragePath() . DIRECTORY_SEPARATOR . 'files';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $prefix = $storeId ? "store_{$storeId}" : 'global';
        $filename = "files_backup_{$prefix}_{$timestamp}.zip";
        $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

        $basePath = dirname(__DIR__, 4);
        $dirsToBackup = [];
        foreach (['uploads', 'images', 'documents', 'exports'] as $dir) {
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($fullPath)) {
                $dirsToBackup[] = $fullPath;
            }
        }

        if (empty($dirsToBackup)) {
            throw new RuntimeException('No directories found to backup');
        }

        self::writeLog($pdo, $backupId, "Starting files backup: {$filename}");
        self::updateJob($pdo, $backupId, ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]);

        $zip = new \ZipArchive();
        if ($zip->open($filepath, \ZipArchive::CREATE) !== true) {
            self::updateJob($pdo, $backupId, ['status' => 'failed', 'error_message' => 'Failed to create zip archive']);
            throw new RuntimeException('Failed to create zip archive');
        }

        foreach ($dirsToBackup as $dirPath) {
            $localName = basename($dirPath);
            $zip->addEmptyDir($localName);
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $relativePath = $localName . '/' . $files->getSubPathName();
                    $zip->addFile($file->getRealPath(), $relativePath);
                }
            }
        }

        $zip->close();

        if (!file_exists($filepath)) {
            self::updateJob($pdo, $backupId, ['status' => 'failed', 'error_message' => 'Output file not created']);
            throw new RuntimeException('Backup file was not created');
        }

        $fileSize = filesize($filepath);
        $checksum = hash_file('sha256', $filepath);

        self::updateJob($pdo, $backupId, [
            'status'           => 'completed',
            'file_path'        => $filepath,
            'file_size'        => $fileSize,
            'checksum'         => $checksum,
            'storage_location' => 'local',
            'completed_at'     => date('Y-m-d H:i:s'),
        ]);

        self::writeLog($pdo, $backupId, "Files backup completed: {$filename} (" . self::formatBytes($fileSize) . ")");

        return $filepath;
    }

    public static function uploadToS3(PDO $pdo, int $backupId): bool
    {
        $job = self::getJob($pdo, $backupId);
        if (!$job || !$job['file_path'] || !file_exists($job['file_path'])) {
            return false;
        }

        $config = self::getAllConfigs($pdo);
        $s3Endpoint = $config['s3_endpoint'] ?? '';
        $s3Bucket   = $config['s3_bucket'] ?? '';
        $s3Region   = $config['s3_region'] ?? 'us-east-1';
        $s3Key      = $config['s3_access_key'] ?? '';
        $s3Secret   = $config['s3_secret_key'] ?? '';

        if (empty($s3Endpoint) || empty($s3Bucket) || empty($s3Key) || empty($s3Secret)) {
            self::writeLog($pdo, $backupId, 'S3 not configured, skipping upload', null, 'warning');
            return false;
        }

        $filename = basename($job['file_path']);
        $s3ObjectKey = 'backups/' . date('Y/m/d') . '/' . $filename;
        $fileContent = file_get_contents($job['file_path']);

        $url = rtrim($s3Endpoint, '/') . '/' . $s3Bucket . '/' . $s3ObjectKey;
        $date = gmdate('D, d M Y H:i:s T');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_PUT           => true,
            CURLOPT_INFILE        => fopen($job['file_path'], 'rb'),
            CURLOPT_INFILESIZE    => filesize($job['file_path']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => 300,
            CURLOPT_HTTPHEADER    => [
                'Host: ' . parse_url($s3Endpoint, PHP_URL_HOST),
                'Date: ' . $date,
                'Content-Type: application/octet-stream',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            self::updateJob($pdo, $backupId, [
                'storage_location' => 's3',
                's3_key'           => $s3ObjectKey,
            ]);
            self::writeLog($pdo, $backupId, "Uploaded to S3: {$s3ObjectKey}");
            return true;
        }

        self::writeLog($pdo, $backupId, "S3 upload failed (HTTP {$httpCode})", null, 'error');
        return false;
    }

    public static function download(PDO $pdo, int $backupId): ?string
    {
        $job = self::getJob($pdo, $backupId);
        if (!$job || !$job['file_path'] || !file_exists($job['file_path'])) {
            return null;
        }
        return $job['file_path'];
    }

    public static function getHealth(PDO $pdo): array
    {
        $health = [
            'last_backup'        => null,
            'total_backups'      => 0,
            'successful'         => 0,
            'failed'             => 0,
            'success_rate'       => 100,
            'total_size'         => 0,
            'recent_failures'    => [],
            'last_24h_backups'   => 0,
        ];

        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job");
            $health['total_backups'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job WHERE status = 'completed'");
            $health['successful'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job WHERE status = 'failed'");
            $health['failed'] = (int) $stmt->fetchColumn();

            if ($health['total_backups'] > 0) {
                $health['success_rate'] = round(
                    ($health['successful'] / $health['total_backups']) * 100, 2
                );
            }

            $stmt = $pdo->query("SELECT COALESCE(SUM(file_size), 0) FROM tbl_backup_job WHERE status = 'completed'");
            $health['total_size'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT * FROM tbl_backup_job WHERE status = 'completed' ORDER BY completed_at DESC LIMIT 1");
            $last = $stmt->fetch();
            if ($last) {
                $health['last_backup'] = $last;
            }

            $stmt = $pdo->query("SELECT * FROM tbl_backup_job WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 5");
            $health['recent_failures'] = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $health['last_24h_backups'] = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
        }

        return $health;
    }

    public static function checkAlerts(PDO $pdo): array
    {
        $alerts = [];
        $configs = self::getAllConfigs($pdo);
        $lastAlertKey = 'backup_last_alert_sent';
        $lastAlert = $configs[$lastAlertKey] ?? '';
        $cooldownHours = 6;

        if ($lastAlert && (time() - strtotime($lastAlert)) < $cooldownHours * 3600) {
            return $alerts;
        }

        $health = self::getHealth($pdo);

        if (!$health['last_backup']) {
            $alerts[] = 'No backup has ever been created';
        } elseif (strtotime($health['last_backup']['completed_at']) < time() - 86400) {
            $alerts[] = 'No backup in the last 24 hours';
        }

        if ($health['failed'] > 0 && $health['success_rate'] < 80) {
            $alerts[] = "Backup success rate is {$health['success_rate']}%";
        }

        if (!empty($health['recent_failures'])) {
            $alerts[] = count($health['recent_failures']) . ' recent backup failures detected';
        }

        if ($health['total_size'] > 1073741824) {
            $alerts[] = 'Total backup storage exceeds 1GB';
        }

        if (!empty($alerts)) {
            $telegramChatId = $configs['telegram_chat_id'] ?? '';
            if ($telegramChatId) {
                $message = "Backup Alert:\n" . implode("\n", $alerts);
                QueueService::enqueueTelegram($pdo, $telegramChatId, $message);
            }
            self::setConfig($pdo, $lastAlertKey, date('Y-m-d H:i:s'));
        }

        return $alerts;
    }

    public static function getStorageSummary(PDO $pdo): array
    {
        $summary = [
            'total_local' => 0,
            'total_s3'    => 0,
            'by_type'     => [],
        ];

        try {
            $stmt = $pdo->query("SELECT storage_location, COALESCE(SUM(file_size), 0) AS total
                FROM tbl_backup_job WHERE status = 'completed' GROUP BY storage_location");
            while ($row = $stmt->fetch()) {
                if ($row['storage_location'] === 's3') {
                    $summary['total_s3'] = (int) $row['total'];
                } else {
                    $summary['total_local'] = (int) $row['total'];
                }
            }

            $stmt = $pdo->query("SELECT type, COALESCE(SUM(file_size), 0) AS total
                FROM tbl_backup_job WHERE status = 'completed' GROUP BY type");
            while ($row = $stmt->fetch()) {
                $summary['by_type'][$row['type']] = (int) $row['total'];
            }
        } catch (\PDOException $e) {
        }

        return $summary;
    }

    public static function handleDatabaseJob(PDO $pdo, array $payload, array $job): string
    {
        $storeId = $job['store_id'];
        $selectedTables = $payload['selected_tables'] ?? null;
        $backupId = (int) $job['id'];

        if ($storeId) {
            $scope = $selectedTables ? 'selected_tables' : 'store';
        } else {
            $scope = 'global';
        }

        $newJobId = self::createJob($pdo, 'database', $scope, $storeId, $selectedTables);
        return self::executeDatabaseBackup($pdo, $newJobId, $storeId, $selectedTables);
    }

    public static function handleFilesJob(PDO $pdo, array $payload, array $job): string
    {
        $storeId = $job['store_id'];
        $backupId = (int) $job['id'];

        $newJobId = self::createJob($pdo, 'files', $storeId ? 'store' : 'global', $storeId);
        return self::executeFilesBackup($pdo, $newJobId, $storeId);
    }

    public static function handleStoreJob(PDO $pdo, array $payload, array $job): array
    {
        $storeId = $job['store_id'];
        $scope = $payload['scope'] ?? 'store';

        $dbJobId = self::createJob($pdo, 'database', $scope, $storeId);
        $dbPath = self::executeDatabaseBackup($pdo, $dbJobId, $storeId);

        $filesJobId = self::createJob($pdo, 'files', $scope, $storeId);
        $filesPath = self::executeFilesBackup($pdo, $filesJobId, $storeId);

        return ['database' => $dbPath, 'files' => $filesPath];
    }

    public static function enqueueDatabase(PDO $pdo, ?int $storeId = null,
        ?array $selectedTables = null, string $priority = 'low'): int
    {
        $payload = [];
        if ($selectedTables) {
            $payload['selected_tables'] = $selectedTables;
        }
        return QueueService::enqueue($pdo, 'backup_database', $priority, $payload, $storeId);
    }

    public static function enqueueFiles(PDO $pdo, ?int $storeId = null, string $priority = 'low'): int
    {
        return QueueService::enqueue($pdo, 'backup_files', $priority, [], $storeId);
    }

    public static function enqueueStore(PDO $pdo, int $storeId, string $priority = 'low'): int
    {
        return QueueService::enqueue($pdo, 'backup_store', $priority, ['scope' => 'store'], $storeId);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
