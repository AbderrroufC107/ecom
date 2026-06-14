<?php
/**
 * Telegram Bot Webhook Handler
 *
 * Endpoint: https://your-site.com/admin/telegram-webhook.php
 *
 * Set webhook via:
 * https://api.telegram.org/bot{BOT_TOKEN}/setWebhook?url=https://your-site.com/admin/telegram-webhook.php
 */

header('Content-Type: application/json; charset=utf-8');

$raw_input = file_get_contents('php://input');
if ($raw_input === false || $raw_input === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request']);
    exit;
}

$update = json_decode($raw_input, true);
if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

require_once __DIR__ . '/inc/config.php';

require_once __DIR__ . '/inc/employee_functions.php';
require_once __DIR__ . '/inc/telegram_bot.php';
require_once __DIR__ . '/inc/telegram_actions.php';

telegram_actions_ensure_tables($pdo);

try {
    telegram_process_update($pdo, $update);
} catch (Exception $e) {
    error_log('Telegram webhook error: ' . $e->getMessage());
}

http_response_code(200);
echo json_encode(['ok' => true]);
