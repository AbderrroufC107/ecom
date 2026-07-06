<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/../inc/whatsapp-business.php';
require_once __DIR__ . '/../inc/meta-platform.php';

header('Content-Type: application/json; charset=utf-8');

whatsapp_business_ensure_settings_columns($pdo);

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_whatsapp_webhook_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL DEFAULT '',
            wa_message_id VARCHAR(190) NOT NULL DEFAULT '',
            from_phone VARCHAR(40) NOT NULL DEFAULT '',
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wa_message_id (wa_message_id),
            INDEX idx_from_phone (from_phone),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    error_log('WhatsApp webhook table migration failed: ' . $e->getMessage());
}

$settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
    $expected = trim((string) ($settings['whatsapp_business_verify_token'] ?? ''));

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Webhook verification failed']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request']);
    exit;
}

$platformSettings = function_exists('meta_platform_settings') ? meta_platform_settings($pdo) : [];
$appSecret = trim((string) ($platformSettings['meta_app_secret'] ?? ''));
$signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
if (function_exists('meta_platform_verify_signature') && !meta_platform_verify_signature($raw, $appSecret, $signature)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

try {
    $entries = $payload['entry'] ?? [];
    if (!is_array($entries)) {
        $entries = [];
    }

    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        if (!is_array($changes)) {
            continue;
        }
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            if (!is_array($value)) {
                continue;
            }

            foreach (($value['messages'] ?? []) as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $stmt = $pdo->prepare("INSERT INTO tbl_whatsapp_webhook_log (event_type, wa_message_id, from_phone, payload, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([
                    'message',
                    (string) ($message['id'] ?? ''),
                    (string) ($message['from'] ?? ''),
                    json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            foreach (($value['statuses'] ?? []) as $status) {
                if (!is_array($status)) {
                    continue;
                }
                $stmt = $pdo->prepare("INSERT INTO tbl_whatsapp_webhook_log (event_type, wa_message_id, from_phone, payload, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([
                    'status',
                    (string) ($status['id'] ?? ''),
                    (string) ($status['recipient_id'] ?? ''),
                    json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }
} catch (Throwable $e) {
    error_log('WhatsApp webhook processing failed: ' . $e->getMessage());
}

http_response_code(200);
echo json_encode(['ok' => true]);
