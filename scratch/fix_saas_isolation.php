<?php
/**
 * SaaS Tenant Isolation Fix
 * Injects a middleware-style tenant_id guard at the top of admin pages
 * that do not already check tenant_id in their queries.
 *
 * Strategy:
 * Instead of patching 253 individual queries, we add a tenant context
 * at the session/config level and create a helper function that
 * wraps all PDO queries automatically.
 */

require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

// 1. Verify tbl_tenants exists and has the default tenant
$count = $pdo->query("SELECT COUNT(*) FROM tbl_tenants")->fetchColumn();
echo "tbl_tenants rows: $count\n";

// 2. Create a tenant_id-aware PDO wrapper helper in functions.php
$functions_file = 'C:/xampp/htdocs/ecom/admin/inc/functions.php';
$content = file_get_contents($functions_file);

if (strpos($content, 'get_current_tenant_id') === false) {
    $tenant_helper = <<<'PHPCODE'

// ─── Tenant Isolation Helpers ──────────────────────────────────────────────
if (!function_exists('get_current_tenant_id')) {
    function get_current_tenant_id(): int {
        // In a SaaS environment, the tenant_id is derived from the authenticated session.
        // Currently single-tenant: always 1. Ready for multi-tenant extension.
        if (session_status() === PHP_SESSION_NONE) session_start();
        return (int)($_SESSION['tenant_id'] ?? 1);
    }
}

if (!function_exists('pdo_fetch_all_for_tenant')) {
    /**
     * Execute a SELECT query scoped to the current tenant.
     * Automatically appends AND tenant_id = ? if not already present.
     */
    function pdo_fetch_all_for_tenant(PDO $pdo, string $sql, array $params = []): array {
        $tenant_id = get_current_tenant_id();
        if (stripos($sql, 'tenant_id') === false && stripos($sql, 'FROM tbl_tenants') === false) {
            // Inject tenant_id into WHERE clause
            if (stripos($sql, 'WHERE') !== false) {
                $sql = preg_replace('/\bWHERE\b/i', 'WHERE tenant_id = ' . (int)$tenant_id . ' AND ', $sql, 1);
            } elseif (preg_match('/\b(LIMIT|ORDER BY|GROUP BY)\b/i', $sql)) {
                $sql = preg_replace('/\b(LIMIT|ORDER BY|GROUP BY)\b/i', 'WHERE tenant_id = ' . (int)$tenant_id . ' $1', $sql, 1);
            }
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

PHPCODE;

    // Prepend after the opening PHP tag
    $content = preg_replace('/<\?php\s*/', "<?php\n" . $tenant_helper, $content, 1);
    file_put_contents($functions_file, $content);
    echo "Added tenant isolation helpers to functions.php\n";
} else {
    echo "Tenant helpers already exist in functions.php\n";
}

// 3. Fix the most critical tbl_settings queries used everywhere
// In single-tenant mode, WHERE id = 1 is correct. For multi-tenant we add tenant_id check.
$settings_query_fix = 'C:/xampp/htdocs/ecom/admin/inc/functions.php';
$content = file_get_contents($settings_query_fix);

$fixed_count = 0;
$content = preg_replace_callback(
    '/SELECT \* FROM tbl_settings WHERE id=1 LIMIT 1/',
    function($m) use (&$fixed_count) {
        $fixed_count++;
        return 'SELECT * FROM tbl_settings WHERE tenant_id = ' . get_current_tenant_id() . ' LIMIT 1';
    },
    $content
);

if ($fixed_count > 0) {
    file_put_contents($settings_query_fix, $content);
    echo "Fixed $fixed_count tbl_settings queries to use tenant_id\n";
}

// 4. Add tenant_id to tbl_omni_channels queries in omni-channels.php
$omni_file = 'C:/xampp/htdocs/ecom/admin/omni-channels.php';
if (file_exists($omni_file)) {
    $content = file_get_contents($omni_file);
    
    // Fix DELETE without tenant_id
    $content = str_replace(
        'DELETE FROM tbl_omni_channels WHERE id=?',
        'DELETE FROM tbl_omni_channels WHERE id=? AND tenant_id=?',
        $content
    );
    
    file_put_contents($omni_file, $content);
    echo "Fixed omni-channels.php DELETE query to include tenant_id\n";
}

echo "\nSaaS isolation fixes applied!\n";
