<?php
declare(strict_types=1);
/**
 * DEPRECATED — This endpoint only logs webhook events to tbl_meta_webhook_log.
 * Use api/meta_webhook.php (full) or api/omni/webhook.php (multi-channel) instead.
 * This file is kept for backward compatibility with old Meta webhook registrations.
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/../inc/meta-platform.php';

header('Content-Type: application/json; charset=utf-8');

meta_platform_ensure_settings_columns($pdo);
meta_platform_ensure_webhook_log_table($pdo);
$settings = meta_platform_settings($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
    $expected = trim((string) ($settings['meta_webhook_verify_token'] ?? ''));

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

$signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
$appSecret = trim((string) ($settings['meta_app_secret'] ?? ''));
if (!meta_platform_verify_signature($raw, $appSecret, $signature)) {
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

$objectType = (string) ($payload['object'] ?? '');
$entries = $payload['entry'] ?? [];
if (!is_array($entries)) {
    $entries = [];
}

try {
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        foreach (($entry['messaging'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $field = isset($event['message']) ? 'message' : (isset($event['postback']) ? 'postback' : 'messaging');
            $stmt = $pdo->prepare("INSERT INTO tbl_meta_webhook_log (object_type, event_field, sender_id, recipient_id, payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $objectType !== '' ? $objectType : 'page',
                $field,
                (string) ($event['sender']['id'] ?? ''),
                (string) ($event['recipient']['id'] ?? ''),
                json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        foreach (($entry['changes'] ?? []) as $change) {
            if (!is_array($change)) {
                continue;
            }
            $value = $change['value'] ?? [];
            $sender = '';
            if (is_array($value)) {
                $sender = (string) ($value['from']['id'] ?? $value['user_id'] ?? $value['sender_id'] ?? '');
            }
            $stmt = $pdo->prepare("INSERT INTO tbl_meta_webhook_log (object_type, event_field, sender_id, recipient_id, payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $objectType,
                (string) ($change['field'] ?? 'change'),
                $sender,
                (string) ($entry['id'] ?? ''),
                json_encode($change, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }
} catch (Throwable $e) {
    error_log('Meta webhook processing failed: ' . $e->getMessage());
}

http_response_code(200);
echo json_encode(['ok' => true]);
