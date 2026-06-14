<?php

namespace Ecom\Store;

use PDO;

class StoreUsage
{
    public static function recordUsage(PDO $pdo, int $storeId, string $resourceType, int $used, int $limitValue): void
    {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO tbl_store_usage (store_id, resource_type, used, limit_value, recorded_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE used = VALUES(used), limit_value = VALUES(limit_value)");
        $stmt->execute([$storeId, $resourceType, $used, $limitValue, $today]);
    }

    public static function getUsage(PDO $pdo, int $storeId, string $resourceType, ?string $date = null): ?array
    {
        $date = $date ?: date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM tbl_store_usage
            WHERE store_id = ? AND resource_type = ? AND recorded_at = ?");
        $stmt->execute([$storeId, $resourceType, $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAllUsage(PDO $pdo, int $storeId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM tbl_store_usage
            WHERE store_id = ? AND recorded_at = ?");
        $stmt->execute([$storeId, $date]);
        return $stmt->fetchAll();
    }

    public static function getUsageHistory(PDO $pdo, int $storeId, string $resourceType, int $days = 30): array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_store_usage
            WHERE store_id = ? AND resource_type = ?
            AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY recorded_at ASC");
        $stmt->execute([$storeId, $resourceType, $days]);
        return $stmt->fetchAll();
    }

    public static function checkQuota(PDO $pdo, int $storeId, string $resourceType, int $currentUsed): bool
    {
        $limits = StoreSubscription::getPlanLimits($pdo, $storeId);
        $limitKey = 'max_' . $resourceType;
        $max = $limits[$limitKey] ?? 0;
        if ($max <= 0) {
            return false;
        }
        return $currentUsed < $max;
    }

    public static function getQuotaUsagePercent(PDO $pdo, int $storeId, string $resourceType, int $currentUsed): float
    {
        $limits = StoreSubscription::getPlanLimits($pdo, $storeId);
        $limitKey = 'max_' . $resourceType;
        $max = $limits[$limitKey] ?? 0;
        if ($max <= 0) {
            return 100.0;
        }
        return round(($currentUsed / $max) * 100, 2);
    }
}
