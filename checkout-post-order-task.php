<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$job_file = $argv[1] ?? '';
if ($job_file === '' || !is_file($job_file)) {
    exit(1);
}

$raw = file_get_contents($job_file);
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    @unlink($job_file);
    exit(1);
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';
require_once __DIR__ . '/assets/telegram-notification.php';

$order_id = (int)($payload['order_id'] ?? 0);

try {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT telegram_bot_token, telegram_chat_id, telegram_orders_enabled FROM tbl_settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Checkout async settings read failed for order #' . $order_id . ': ' . $e->getMessage());
    }

    $order_data = $payload['telegram_order_data'] ?? [];
    if (!empty($settings['telegram_orders_enabled']) && !empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id']) && !empty($order_data)) {
        $telegram = new TelegramNotification($settings['telegram_bot_token'], $settings['telegram_chat_id']);
        $telegram->sendOrderNotification($order_data);
    }

    $context = $payload['context'] ?? [];
    if (!empty($context) && function_exists('admin_send_order_sms_automation')) {
        $auto_sms_result = admin_send_order_sms_automation($pdo, 'order_created', $context);
        if (empty($auto_sms_result['skipped']) && empty($auto_sms_result['success'])) {
            error_log('Automatic SMS failed for order #' . $order_id . ': ' . trim((string)($auto_sms_result['error'] ?? 'Gateway error')));
        }
    }

    @unlink($job_file);
    exit(0);
} catch (Throwable $e) {
    error_log('Checkout async task failed for order #' . $order_id . ': ' . $e->getMessage());
    exit(1);
}
