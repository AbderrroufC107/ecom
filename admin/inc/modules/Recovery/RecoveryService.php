<?php

namespace Ecom\Recovery;

use PDO;

class RecoveryService
{
    public static function checkDatabaseConnection(PDO $pdo): array
    { global $dbRepo;
        $start = microtime(true);
        try {
            $dbRepo->query("SELECT 1");
            $latency = round((microtime(true) - $start) * 1000, 2);
            return [
                'status'   => 'healthy',
                'latency'  => $latency,
                'message'  => 'Database connection is working',
            ];
        } catch (\PDOException $e) {
            return [
                'status'  => 'critical',
                'latency' => 0,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public static function checkStorage(PDO $pdo): array
    { global $dbRepo;
        $backupDir = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'backups';
        $uploadsDir = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'uploads';

        $result = [
            'backup_dir' => ['status' => 'unknown', 'size' => 0, 'writable' => false],
            'uploads_dir' => ['status' => 'unknown', 'size' => 0, 'writable' => false],
        ];

        if (is_dir($backupDir)) {
            $size = self::dirSize($backupDir);
            $writable = is_writable($backupDir);
            $free = disk_free_space(dirname($backupDir));
            $result['backup_dir'] = [
                'status'        => $writable ? 'healthy' : 'warning',
                'size'          => $size,
                'writable'      => $writable,
                'free_space'    => $free,
                'free_space_hr' => self::formatBytes($free),
            ];
        } else {
            $result['backup_dir'] = ['status' => 'missing', 'size' => 0, 'writable' => false];
        }

        if (is_dir($uploadsDir)) {
            $size = self::dirSize($uploadsDir);
            $writable = is_writable($uploadsDir);
            $result['uploads_dir'] = [
                'status'   => $writable ? 'healthy' : 'warning',
                'size'     => $size,
                'writable' => $writable,
            ];
        } else {
            $result['uploads_dir'] = ['status' => 'missing', 'size' => 0, 'writable' => false];
        }

        return $result;
    }

    public static function checkSystemRequirements(): array
    { global $dbRepo;
        $checks = [];
        $allPassed = true;

        $requirements = [
            'PHP Version >= 8.0' => PHP_VERSION_ID >= 80000,
            'PDO Extension'      => extension_loaded('pdo'),
            'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
            'JSON Extension'     => extension_loaded('json'),
            'CURL Extension'     => extension_loaded('curl'),
            'Zip Extension'      => extension_loaded('zip'),
            'MBString Extension' => extension_loaded('mbstring'),
            'OpenSSL Extension'  => extension_loaded('openssl'),
            'GD Extension'       => extension_loaded('gd'),
            'MySQL Dump CLI'     => self::commandExists('mysqldump'),
            'GZip CLI'           => self::commandExists('gzip'),
        ];

        foreach ($requirements as $name => $pass) {
            $checks[] = [
                'name'    => $name,
                'status'  => $pass ? 'passed' : 'failed',
            ];
            if (!$pass) {
                $allPassed = false;
            }
        }

        return [
            'all_passed' => $allPassed,
            'checks'     => $checks,
            'php_version' => PHP_VERSION,
        ];
    }

    public static function checkQueueHealth(PDO $pdo): array
    { global $dbRepo;
        try {
            $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_queue_jobs WHERE status = 'pending'");
            $pending = (int) $stmt->fetchColumn();

            $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_queue_jobs WHERE status = 'processing'");
            $processing = (int) $stmt->fetchColumn();

            $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_failed_jobs");
            $failed = (int) $stmt->fetchColumn();

            $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_queue_jobs WHERE status = 'processing'
                AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
            $stuck = (int) $stmt->fetchColumn();

            $status = 'healthy';
            $issues = [];

            if ($stuck > 5) {
                $status = 'warning';
                $issues[] = "{$stuck} stuck jobs detected";
            }
            if ($failed > 20) {
                $status = 'warning';
                $issues[] = "{$failed} failed jobs";
            }
            if ($pending > 200) {
                $status = 'warning';
                $issues[] = "{$pending} pending jobs";
            }

            return [
                'status'     => $status,
                'pending'    => $pending,
                'processing' => $processing,
                'failed'     => $failed,
                'stuck'      => $stuck,
                'issues'     => $issues,
            ];
        } catch (\PDOException $e) {
            return [
                'status' => 'critical',
                'error'  => $e->getMessage(),
            ];
        }
    }

    public static function performFullHealthCheck(PDO $pdo): array
    { global $dbRepo;
        return [
            'database' => self::checkDatabaseConnection($pdo),
            'storage'  => self::checkStorage($pdo),
            'system'   => self::checkSystemRequirements(),
            'queue'    => self::checkQueueHealth($pdo),
            'timestamp' => date('c'),
        ];
    }

    private static function dirSize(string $dir): int
    { global $dbRepo;
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private static function commandExists(string $command): bool
    { global $dbRepo;
        $cmd = PHP_OS_FAMILY === 'Windows' ? "where {$command} 2>NUL" : "which {$command} 2>/dev/null";
        exec($cmd, $output, $exitCode);
        return $exitCode === 0;
    }

    private static function formatBytes(int $bytes): string
    { global $dbRepo;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
