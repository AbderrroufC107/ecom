<?php

namespace Ecom\Audit;

use PDO;
use DateTime;

class AuditRepository
{
    public static function insert(PDO $pdo, int $storeId, string $action, string $entityType,
        int $entityId, ?array $oldValue = null, ?array $newValue = null,
        ?int $staffId = null, ?string $ipAddress = null, ?string $userAgent = null): int
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_audit_log (store_id, staff_id, action, entity_type, entity_id,
            old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $storeId,
            $staffId,
            $action,
            $entityType,
            $entityId,
            $oldValue ? json_encode($oldValue) : null,
            $newValue ? json_encode($newValue) : null,
            $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function query(PDO $pdo, ?int $storeId = null, ?string $action = null,
        ?string $entityType = null, ?string $dateFrom = null, ?string $dateTo = null,
        int $page = 1, int $perPage = 50): array
    {
        $where = '1=1';
        $params = [];

        if ($storeId !== null) {
            $where .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        if ($action !== null) {
            $where .= ' AND l.action = ?';
            $params[] = $action;
        }
        if ($entityType !== null) {
            $where .= ' AND l.entity_type = ?';
            $params[] = $entityType;
        }
        if ($dateFrom !== null) {
            $where .= ' AND l.created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where .= ' AND l.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT l.*, s.name AS store_name
            FROM tbl_audit_log l
            LEFT JOIN tbl_stores s ON l.store_id = s.id
            WHERE {$where} ORDER BY l.id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function count(PDO $pdo, ?int $storeId = null, ?string $action = null,
        ?string $entityType = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $where = '1=1';
        $params = [];

        if ($storeId !== null) {
            $where .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        if ($action !== null) {
            $where .= ' AND action = ?';
            $params[] = $action;
        }
        if ($entityType !== null) {
            $where .= ' AND entity_type = ?';
            $params[] = $entityType;
        }
        if ($dateFrom !== null) {
            $where .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_audit_log WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function getActionsByStore(PDO $pdo, int $storeId, int $limit = 20): array
    {
        $stmt = $pdo->prepare("SELECT action, COUNT(*) AS cnt
            FROM tbl_audit_log WHERE store_id = ?
            GROUP BY action ORDER BY cnt DESC LIMIT ?");
        $stmt->execute([$storeId, $limit]);
        return $stmt->fetchAll();
    }

    public static function getRecentByStore(PDO $pdo, int $storeId, int $limit = 10): array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_audit_log WHERE store_id = ?
            ORDER BY id DESC LIMIT ?");
        $stmt->execute([$storeId, $limit]);
        return $stmt->fetchAll();
    }

    public static function getTimeline(PDO $pdo, int $days = 7): array
    {
        $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, action, COUNT(*) AS cnt
            FROM tbl_audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at), action ORDER BY day ASC");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public static function cleanup(PDO $pdo, int $olderThanDays = 90): int
    {
        $stmt = $pdo->prepare("DELETE FROM tbl_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$olderThanDays]);
        return $stmt->rowCount();
    }
}
