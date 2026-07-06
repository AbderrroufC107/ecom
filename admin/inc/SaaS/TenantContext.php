<?php
namespace SaaS;

class TenantContext {
    private static ?int $tenant_id = null;
    private static ?int $store_id = null;
    private static ?int $role_id = null;
    private static ?array $permissions = null;

    /**
     * Initializes the context for the current request.
     */
    public static function init(int $tenant_id, int $store_id = 0, int $role_id = 0, array $permissions = []) {
        self::$tenant_id = $tenant_id;
        self::$store_id = $store_id;
        self::$role_id = $role_id;
        self::$permissions = $permissions;
    }

    public static function getTenantId(): int {
        if (self::$tenant_id === null) {
            throw new \Exception("TenantContext is not initialized. Cannot access tenant_id.");
        }
        return self::$tenant_id;
    }

    public static function getStoreId(): int {
        return self::$store_id ?? 0;
    }

    public static function getRoleId(): int {
        return self::$role_id ?? 0;
    }

    public static function hasPermission(string $permission): bool {
        if (self::$permissions === null) return true; // Default admin
        return in_array($permission, self::$permissions);
    }
}
