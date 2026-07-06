<?php

namespace Ecom\Store;

use PDO;
use Ecom\Common\Helpers;

class StoreService
{
    private static ?int $currentStoreId = null;

    public static function setCurrentId(?int $id): void
    { global $dbRepo;
        self::$currentStoreId = $id;
    }

    public static function getCurrentId(): ?int
    { global $dbRepo;
        return self::$currentStoreId;
    }

    public static function resolve(PDO $pdo): int
    { global $dbRepo;
        if (self::$currentStoreId !== null) {
            return self::$currentStoreId;
        }

        if (isset($_SESSION['store_id'])) {
            self::$currentStoreId = (int) $_SESSION['store_id'];
            return self::$currentStoreId;
        }

        if (isset($_GET['store_id'])) {
            self::$currentStoreId = (int) $_GET['store_id'];
            return self::$currentStoreId;
        }

        if (isset($_SERVER['HTTP_X_STORE_ID'])) {
            self::$currentStoreId = (int) $_SERVER['HTTP_X_STORE_ID'];
            return self::$currentStoreId;
        }

        $store = StoreRepository::getByDomain($pdo, $_SERVER['HTTP_HOST'] ?? '');
        if ($store) {
            self::$currentStoreId = (int) $store['id'];
            return self::$currentStoreId;
        }

        return 0;
    }

    public static function authenticate(PDO $pdo, string $email, string $password): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_store_users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    public static function createUser(PDO $pdo, int $storeId, string $name, string $email,
        string $password, string $role = 'staff'): int
    { global $dbRepo;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $dbRepo->prepare("INSERT INTO tbl_store_users (store_id, name, email, password, role)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$storeId, $name, $email, $hash, $role]);
        return (int) $dbRepo->lastInsertId();
    }

    public static function getUsers(PDO $pdo, int $storeId): array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_store_users WHERE store_id = ? ORDER BY id ASC");
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public static function getSetting(PDO $pdo, int $storeId, string $key, mixed $default = null): mixed
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT setting_value FROM tbl_store_settings WHERE store_id = ? AND setting_key = ?");
        $stmt->execute([$storeId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    public static function setSetting(PDO $pdo, int $storeId, string $key, mixed $value): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_store_settings (store_id, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$storeId, $key, $value]);
    }

    public static function getAllSettings(PDO $pdo, int $storeId): array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT setting_key, setting_value FROM tbl_store_settings WHERE store_id = ?");
        $stmt->execute([$storeId]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    public static function validateOwnership(PDO $pdo, int $storeId): bool
    { global $dbRepo;
        $currentId = self::getCurrentId();
        if ($currentId === null) {
            return false;
        }
        if ($storeId === $currentId) {
            return true;
        }
        $store = StoreRepository::get($pdo, $storeId);
        return $store !== null && (int) $store['id'] === $currentId;
    }

    public static function getStats(PDO $pdo, int $storeId): array
    { global $dbRepo;
        $productCount = 0;
        $employeeCount = 0;
        $totalSales = 0;

        try {
            $stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_products WHERE store_id = ?");
            $stmt->execute([$storeId]);
            $productCount = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
        }

        try {
            $stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_store_users WHERE store_id = ?");
            $stmt->execute([$storeId]);
            $employeeCount = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
        }

        try {
            $stmt = $dbRepo->prepare("SELECT COALESCE(SUM(total), 0) FROM tbl_invoices WHERE store_id = ? AND status = 'paid'");
            $stmt->execute([$storeId]);
            $totalSales = (float) $stmt->fetchColumn();
        } catch (\PDOException $e) {
        }

        return [
            'products'  => $productCount,
            'employees' => $employeeCount,
            'sales'     => $totalSales,
        ];
    }

    public static function getGlobalStats(PDO $pdo): array
    { global $dbRepo;
        $total = 0;
        $active = 0;
        $trial = 0;
        $suspended = 0;
        $revenue = 0;

        try {
            $total = (int) $dbRepo->query("SELECT COUNT(*) FROM tbl_stores")->fetchColumn();
            $active = (int) $dbRepo->query("SELECT COUNT(*) FROM tbl_stores WHERE status = 'active'")->fetchColumn();
            $trial = (int) $dbRepo->query("SELECT COUNT(*) FROM tbl_stores WHERE status = 'trial'")->fetchColumn();
            $suspended = (int) $dbRepo->query("SELECT COUNT(*) FROM tbl_stores WHERE status = 'suspended'")->fetchColumn();
            $revenue = (float) $dbRepo->query("SELECT COALESCE(SUM(total), 0) FROM tbl_invoices WHERE status = 'paid'")->fetchColumn();
        } catch (\PDOException $e) {
        }

        return [
            'total'     => $total,
            'active'    => $active,
            'trial'     => $trial,
            'suspended' => $suspended,
            'revenue'   => $revenue,
        ];
    }

    public static function getTheme(PDO $pdo, int $storeId): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_store_themes WHERE store_id = ?");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function saveTheme(PDO $pdo, int $storeId, array $themeData): void
    { global $dbRepo;
        $json = json_encode($themeData);
        $stmt = $dbRepo->prepare("INSERT INTO tbl_store_themes (store_id, theme_json)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE theme_json = VALUES(theme_json)");
        $stmt->execute([$storeId, $json]);
    }

    public static function buildWhere(PDO $pdo, array $filters, string $alias = 's'): array
    { global $dbRepo;
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = "{$alias}.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['plan_id'])) {
            $conditions[] = "{$alias}.plan_id = ?";
            $params[] = (int) $filters['plan_id'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = "({$alias}.name LIKE ? OR {$alias}.email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = "{$alias}.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = "{$alias}.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        return ['where' => implode(' AND ', $conditions), 'params' => $params];
    }

    public static function applyWhere(PDO $pdo, string $baseSql, array $filters, string $alias = 's'): array
    { global $dbRepo;
        $built = self::buildWhere($pdo, $filters, $alias);
        $sql = $baseSql . ' WHERE ' . $built['where'];
        return ['sql' => $sql, 'params' => $built['params']];
    }
}
