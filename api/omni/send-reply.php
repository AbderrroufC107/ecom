<?php
declare(strict_types=1);

require_once __DIR__ . '/../../admin/inc/config.php';
require_once __DIR__ . '/../../admin/inc/integration/N8nManager.php';
require_once __DIR__ . '/../../admin/inc/Omni/UnifiedMessage.php';
require_once __DIR__ . '/../../admin/inc/Omni/Adapters/AdapterInterface.php';
require_once __DIR__ . '/../../admin/inc/Omni/Adapters/MetaAdapter.php';
require_once __DIR__ . '/../../admin/inc/Omni/EventLogger.php';

header('Content-Type: application/json; charset=utf-8');

function send_reply_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    send_reply_error('Method not allowed', 405);
}

// Shared-secret auth: reuses the same key configured for outbound N8nManager calls,
// so there is a single credential to manage on the n8n side for both directions.
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
$providedKey = trim((string) ($headers['x-n8n-api-key'] ?? ''));
if ($providedKey === '') {
    send_reply_error('Missing X-N8N-API-KEY header', 401);
}

try {
    $stmt = $pdo->prepare("SELECT api_key FROM tbl_n8n_integrations WHERE is_active = 1 ORDER BY FIELD(environment,'production','staging','development') LIMIT 1");
    $stmt->execute();
    $encrypted = (string) $stmt->fetchColumn();
    $expectedKey = $encrypted !== '' ? \Integration\N8nManager::decryptApiKeyPublic($encrypted) : '';
} catch (Throwable $e) {
    $expectedKey = '';
}

if ($expectedKey === '' || !hash_equals($expectedKey, (string) $providedKey)) {
    send_reply_error('Invalid API key', 403);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    send_reply_error('Invalid JSON payload');
}

$channelId = (int) ($body['channel_id'] ?? 0);
$platformUserId = trim((string) ($body['platform_user_id'] ?? ''));
$text = trim((string) ($body['text'] ?? ''));

if ($channelId <= 0 || $platformUserId === '' || $text === '') {
    send_reply_error('channel_id, platform_user_id and text are required');
}

$options = [];
if (!empty($body['is_private_reply'])) {
    $options['is_private_reply'] = true;
}
if (!empty($body['attachment_url'])) {
    $options['attachment_url'] = (string) $body['attachment_url'];
}

$stmt = $pdo->prepare("SELECT id, provider FROM tbl_omni_channels WHERE id = ? AND status = 'ACTIVE'");
$stmt->execute([$channelId]);
$channel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$channel) {
    send_reply_error('Unknown or inactive channel_id', 404);
}

if ($channel['provider'] !== 'meta') {
    send_reply_error('Unsupported provider: ' . $channel['provider'], 400);
}

$adapter = new \Omni\Adapters\MetaAdapter();
$sent = $adapter->sendMessage($channelId, $platformUserId, $text, $options);

echo json_encode(['success' => (bool) $sent], JSON_UNESCAPED_UNICODE);
