<?php

namespace Ecom\Backup;

use PDO;
use RuntimeException;

class RestoreService
{
    public static function createRequest(PDO $pdo, int $backupId, int $requestedBy,
        ?int $storeId = null, ?string $notes = null): int
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_restore_request (backup_id, store_id, requested_by, status, notes)
            VALUES (?, ?, ?, 'pending', ?)");
        $stmt->execute([$backupId, $storeId, $requestedBy, $notes]);
        return (int) $pdo->lastInsertId();
    }

    public static function getRequest(PDO $pdo, int $requestId): ?array
    {
        $stmt = $pdo->prepare("SELECT r.*, b.file_path, b.file_size, b.type AS backup_type,
            b.checksum, s.name AS store_name
            FROM tbl_restore_request r
            LEFT JOIN tbl_backup_job b ON r.backup_id = b.id
            LEFT JOIN tbl_stores s ON r.store_id = s.id
            WHERE r.id = ?");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateRequest(PDO $pdo, int $requestId, array $data): void
    {
        $allowed = ['status', 'approved_by', 'approved_at', 'executed_at', 'notes'];
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
        $params[] = $requestId;
        $stmt = $pdo->prepare("UPDATE tbl_restore_request SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    }

    public static function getRequests(PDO $pdo, int $page = 1, int $perPage = 20,
        ?string $statusFilter = null): array
    {
        $where = '1=1';
        $params = [];
        if ($statusFilter) {
            $where .= ' AND r.status = ?';
            $params[] = $statusFilter;
        }
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT r.*, b.file_path, b.type AS backup_type,
            b.file_size, s.name AS store_name
            FROM tbl_restore_request r
            LEFT JOIN tbl_backup_job b ON r.backup_id = b.id
            LEFT JOIN tbl_stores s ON r.store_id = s.id
            WHERE {$where} ORDER BY r.id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function getRequestCount(PDO $pdo, ?string $statusFilter = null): int
    {
        $where = '1=1';
        $params = [];
        if ($statusFilter) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_restore_request WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function approve(PDO $pdo, int $requestId, int $approvedBy): void
    {
        self::updateRequest($pdo, $requestId, [
            'status'      => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function reject(PDO $pdo, int $requestId): void
    {
        self::updateRequest($pdo, $requestId, ['status' => 'rejected']);
    }

    public static function execute(PDO $pdo, int $requestId): bool
    {
        $request = self::getRequest($pdo, $requestId);
        if (!$request || $request['status'] !== 'approved') {
            throw new RuntimeException('Restore request must be approved first');
        }

        $filePath = $request['file_path'];
        if (!$filePath || !file_exists($filePath)) {
            throw new RuntimeException('Backup file not found');
        }

        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? DB_NAME : 'ecom';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;

        if (str_ends_with($filePath, '.gz')) {
            $cmd = sprintf(
                'gunzip -c %s | mysql --host=%s --port=%d --user=%s --password=%s %s 2>&1',
                escapeshellarg($filePath),
                escapeshellarg($host),
                $port,
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name)
            );
        } elseif (str_ends_with($filePath, '.sql')) {
            $cmd = sprintf(
                'mysql --host=%s --port=%d --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($host),
                $port,
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name),
                escapeshellarg($filePath)
            );
        } else {
            throw new RuntimeException('Unsupported backup file format');
        }

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            self::updateRequest($pdo, $requestId, [
                'status'       => 'rejected',
                'notes'        => 'Restore execution failed: ' . $error,
            ]);
            throw new RuntimeException("Restore failed: {$error}");
        }

        self::updateRequest($pdo, $requestId, [
            'status'       => 'executed',
            'executed_at'  => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
