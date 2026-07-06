<?php
namespace SaaS;

require_once __DIR__ . '/TenantContext.php';

class TenantMiddleware {
    /**
     * Bootstraps the tenant context for the application.
     * Called in config.php early in the request lifecycle.
     * Safe to call from CLI (e.g. test scripts).
     *
     * @param \PDO $pdo  – Database connection (reserved for future use)
     */
    public static function boot(\PDO $pdo): void {
        // In CLI mode, do nothing — tests call TenantContext::init() directly.
        if (php_sapi_name() === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // For SaaS, tenant_id MUST come from the authenticated session.
        // It MUST NOT be read from $_GET or $_POST (prevents tenant-hijacking).
        $tenant_id  = (int) ($_SESSION['tenant_id']  ?? 1); // Default = 1 (Main Tenant)
        $store_id   = (int) ($_SESSION['store_id']   ?? 0);
        $role_id    = (int) ($_SESSION['role_id']    ?? 0);
        $permissions = $_SESSION['permissions']      ?? [];

        TenantContext::init($tenant_id, $store_id, $role_id, $permissions);
    }

    /**
     * Alias for backward-compatibility with code calling ::handle().
     */
    public static function handle(): void {
        // This is a no-op alias; boot() is the canonical entry point.
        // $pdo is not available here, but boot() handles this.
        if (php_sapi_name() === 'cli') return;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $tenant_id  = (int) ($_SESSION['tenant_id']  ?? 1);
        $store_id   = (int) ($_SESSION['store_id']   ?? 0);
        $role_id    = (int) ($_SESSION['role_id']    ?? 0);
        $permissions = $_SESSION['permissions']      ?? [];

        TenantContext::init($tenant_id, $store_id, $role_id, $permissions);
    }
}
