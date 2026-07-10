<?php
/**
 * One-time production setup for the n8n / Meta Ads integration work.
 *
 * Run this ONCE on the real server (CLI is safest: `php admin/setup-n8n-integration.php`),
 * then delete it. It only creates things if missing — safe to re-run.
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['run_setup'])) {
    http_response_code(403);
    exit('Forbidden. Run via CLI: php admin/setup-n8n-integration.php');
}

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/modules/Store/StoreRepository.php';
require_once __DIR__ . '/inc/modules/Common/Helpers.php';
require_once __DIR__ . '/inc/modules/Api/ApiKeyService.php';
require_once __DIR__ . '/inc/integration/N8nManager.php';
require_once __DIR__ . '/../inc/meta-marketing.php';

function out(string $line): void
{
    echo $line . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
}

out('=== 1) Ensuring SaaS tables (tbl_api_keys, tbl_api_logs, ...) ===');
\Ecom\Store\StoreRepository::migrateTables($pdo);
out('OK.');

out('=== 2) Ensuring Meta Marketing settings columns on tbl_settings ===');
meta_marketing_ensure_settings_columns($pdo);
out('OK.');

out('=== 3) API keys for n8n ===');

$stmt = $pdo->prepare("SELECT api_key FROM tbl_api_keys WHERE store_id = 1 AND label = ? AND status = 'active' LIMIT 1");
$stmt->execute(['n8n Sales Agent']);
$v1Key = $stmt->fetchColumn();
if (!$v1Key) {
    $id = \Ecom\Api\ApiKeyService::create($pdo, 1, 'n8n Sales Agent', ['products.read', 'orders.read', 'orders.write', 'customers.read', 'analytics.read']);
    $stmt = $pdo->prepare('SELECT api_key FROM tbl_api_keys WHERE id = ?');
    $stmt->execute([$id]);
    $v1Key = $stmt->fetchColumn();
    out('Created api/v1 key.');
} else {
    out('api/v1 key already exists.');
}
out('API_V1_KEY=' . $v1Key);

$stmt = $pdo->prepare("SELECT api_key FROM tbl_api_key WHERE name = ? AND is_active = 1 LIMIT 1");
$stmt->execute(['n8n Sales Agent - AI Product Knowledge']);
$aiKey = $stmt->fetchColumn();
if (!$aiKey) {
    $aiKey = 'aiapi_' . bin2hex(random_bytes(24));
    $stmt = $pdo->prepare('INSERT INTO tbl_api_key (tenant_id, store_id, api_key, name, is_active, created_at) VALUES (1, 1, ?, ?, 1, NOW())');
    $stmt->execute([$aiKey, 'n8n Sales Agent - AI Product Knowledge']);
    out('Created api/ai key.');
} else {
    out('api/ai key already exists.');
}
out('API_AI_KEY=' . $aiKey);

out('=== 4) n8n integration row (base_url + webhook paths + shared secret) ===');
$stmt = $pdo->prepare("SELECT id, api_key FROM tbl_n8n_integrations WHERE environment = 'production' LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$webhookPaths = json_encode([
    'ai_agent' => '/webhook/ai-sales-agent-v2',
    'product_sync' => '/webhook/product-sync',
    'provider_manager' => '/webhook/provider-manager',
    'customer360' => '/webhook/customer360',
    'order_events' => '/webhook/order-events',
    'analytics' => '/webhook/analytics',
    'notifications' => '/webhook/notifications',
    'delivery_status' => '/webhook/delivery-status',
    'sales_agent' => '/webhook/sales-agent',
]);

// CHANGE THIS if you are using a different n8n instance in production.
$n8nBaseUrl = 'https://thikastore19.app.n8n.cloud';

if ($row) {
    $sharedSecret = $row['api_key'] ? \Integration\N8nManager::decryptApiKeyPublic($row['api_key']) : '';
    if (!$sharedSecret) {
        $sharedSecret = bin2hex(random_bytes(24));
        $encrypted = \Integration\N8nManager::encryptApiKey($sharedSecret);
        $pdo->prepare('UPDATE tbl_n8n_integrations SET base_url = ?, webhook_paths = ?, api_key = ? WHERE id = ?')
            ->execute([$n8nBaseUrl, $webhookPaths, $encrypted, $row['id']]);
        out('Updated existing row with a new shared secret.');
    } else {
        $pdo->prepare('UPDATE tbl_n8n_integrations SET base_url = ?, webhook_paths = ? WHERE id = ?')
            ->execute([$n8nBaseUrl, $webhookPaths, $row['id']]);
        out('Updated existing row, kept its shared secret.');
    }
} else {
    $sharedSecret = bin2hex(random_bytes(24));
    $encrypted = \Integration\N8nManager::encryptApiKey($sharedSecret);
    $pdo->prepare("INSERT INTO tbl_n8n_integrations (environment, label, base_url, webhook_paths, api_key, is_active) VALUES ('production', 'n8n Cloud', ?, ?, ?, 1)")
        ->execute([$n8nBaseUrl, $webhookPaths, $encrypted]);
    out('Created new row with a new shared secret.');
}
out('N8N_SHARED_SECRET=' . $sharedSecret);

out('');
out('=== DONE. Copy the three values above into the n8n credentials. Then DELETE this file. ===');
