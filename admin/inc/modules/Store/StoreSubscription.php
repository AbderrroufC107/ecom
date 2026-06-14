<?php

namespace Ecom\Store;

use PDO;

class StoreSubscription
{
    public static function get(PDO $pdo, int $storeId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_subscriptions WHERE store_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getStatus(PDO $pdo, int $storeId): string
    {
        $sub = self::get($pdo, $storeId);
        if (!$sub) {
            return 'none';
        }
        return $sub['status'];
    }

    public static function isReadOnly(PDO $pdo, int $storeId): bool
    {
        $status = self::getStatus($pdo, $storeId);
        return in_array($status, ['cancelled', 'expired']);
    }

    public static function requireWriteAccess(PDO $pdo, int $storeId): void
    {
        if (self::isReadOnly($pdo, $storeId)) {
            header('Location: ../suspended.php');
            exit;
        }
    }

    public static function getPlanLimits(PDO $pdo, int $storeId): array
    {
        $store = StoreRepository::get($pdo, $storeId);
        if (!$store) {
            return [
                'max_products'   => 0,
                'max_employees'  => 0,
                'max_storage_mb' => 0,
                'features'       => [],
            ];
        }
        return [
            'max_products'   => (int) ($store['max_products'] ?? 0),
            'max_employees'  => (int) ($store['max_employees'] ?? 0),
            'max_storage_mb' => (int) ($store['max_storage_mb'] ?? 0),
            'features'       => $store['features'] ? json_decode($store['features'], true) : [],
        ];
    }

    public static function checkFeature(PDO $pdo, int $storeId, string $feature): bool
    {
        $limits = self::getPlanLimits($pdo, $storeId);
        if (empty($limits['features'])) {
            return false;
        }
        return in_array($feature, $limits['features'], true);
    }

    public static function checkEmployeeLimit(PDO $pdo, int $storeId, int $currentCount): bool
    {
        $limits = self::getPlanLimits($pdo, $storeId);
        $max = $limits['max_employees'];
        if ($max <= 0) {
            return false;
        }
        return $currentCount < $max;
    }

    public static function create(PDO $pdo, int $storeId, int $planId, string $status = 'active',
        ?string $expiresAt = null): int
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_subscriptions (store_id, plan_id, status, expires_at)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([$storeId, $planId, $status, $expiresAt]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $storeId, array $data): void
    {
        $allowed = ['plan_id', 'status', 'expires_at', 'cancelled_at'];
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
        $params[] = $storeId;
        $stmt = $pdo->prepare("UPDATE tbl_subscriptions SET " . implode(', ', $sets)
            . " WHERE store_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute($params);
    }
}
