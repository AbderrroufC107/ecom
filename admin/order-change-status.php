<?php
ob_start();
session_start();

require_once('inc/config.php');
require_once('inc/functions.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');
require_once('inc/performance_functions.php');
require_once(dirname(__DIR__) . '/websocket/broadcast.php');

if (!function_exists('admin_orders_redirect')) {
    function admin_orders_redirect($target = 'order.php')
    {
        $target = trim((string) $target);
        $is_allowed = preg_match('/^order\.php(?:#[A-Za-z0-9_-]+)?$/', $target)
            || preg_match('/^order-statistics\.php$/', $target)
            || preg_match('/^order-details\.php\?id=\d+(?:#[A-Za-z0-9_-]+)?$/', $target);
        if (!$is_allowed) {
            $target = 'order.php';
        }

        header('Location: ' . $target);
        exit;
    }
}

$redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : 'order.php';
$order_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
$target_status = admin_normalize_order_status($_REQUEST['status'] ?? '');
$status_note = trim((string) ($_REQUEST['status_note'] ?? ''));
$changed_by = trim((string) ($_SESSION['user']['full_name'] ?? ''));

if ($order_id <= 0 || $target_status === '') {
    admin_set_flash_message('orders', 'danger', 'ØªØ¹Ø°Ø± ØªØ­Ø¯ÙŠØ« Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨ Ù„Ø£Ù† Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© ØºÙŠØ± Ù…ÙƒØªÙ…Ù„Ø©.');
    admin_orders_redirect($redirect);
}

try {
    admin_ensure_order_status_log_table($pdo);
    site_security_ensure_tables($pdo);
    site_security_ensure_order_columns($pdo);

    $statement = $pdo->prepare('SELECT id, order_status, customer_name, customer_phone, product_name, quantity, total_price, wilaya, commune, address, delivery_type, customer_ip, device_id FROM tbl_order WHERE id = ? LIMIT 1');
    $statement->execute([$order_id]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Ø§Ù„Ø·Ù„Ø¨ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨ ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯.');
    }

    $current_status = admin_normalize_order_status($order['order_status'] ?? '');
    if ($current_status === $target_status) {
        throw new Exception('Ø§Ù„Ø·Ù„Ø¨ Ù…ÙˆØ¬ÙˆØ¯ Ø¨Ø§Ù„ÙØ¹Ù„ ÙÙŠ Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.');
    }

    if (!admin_can_transition_order_status($current_status, $target_status)) {
        $current_meta = admin_get_order_status_meta($current_status);
        $target_meta = admin_get_order_status_meta($target_status);
        throw new Exception('Ù„Ø§ ÙŠÙ…ÙƒÙ† Ù†Ù‚Ù„ Ø§Ù„Ø·Ù„Ø¨ Ù…Ù† Ø­Ø§Ù„Ø© "' . $current_meta['label'] . '" Ø¥Ù„Ù‰ "' . $target_meta['label'] . '".');
    }

    $pdo->beginTransaction();

    $statement = $pdo->prepare('UPDATE tbl_order SET order_status = ? WHERE id = ?');
    $statement->execute([$target_status, $order_id]);

    admin_log_order_status_change($pdo, $order_id, $current_status, $target_status, $status_note, $changed_by);
    if ($target_status === 'Returned') {
        site_security_try_record_delivery_return($pdo, $order, 'Returned', $status_note, 'manual_order_status');
    }
    if ($target_status === 'Completed') {
        performance_ensure_tables($pdo);
        performance_auto_record_commission($pdo, $order_id);
    }

    $pdo->commit();

    // WebSocket broadcast - notify admin of status change
    ws_broadcast_order_status([
        'id' => $order_id,
        'order_no' => $order['order_no'] ?? '#' . $order_id,
        'customer_name' => $order['customer_name'] ?? '',
        'customer_phone' => $order['customer_phone'] ?? '',
        'total_price' => $order['total_price'] ?? 0,
        'previous_status' => $current_status,
        'new_status' => $target_status,
        'changed_by' => $changed_by,
        'order_date' => $order['order_date'] ?? '',
    ]);

    $target_meta = admin_get_order_status_meta($target_status);
    $order['order_status'] = $target_status;
    $order['status'] = $target_status;
    $order_label = '#' . $order_id . ' - ' . trim((string) ($order['customer_name'] ?? 'Ø¹Ù…ÙŠÙ„ ØºÙŠØ± Ù…Ø­Ø¯Ø¯'));
    admin_set_flash_message('orders', 'success', 'ØªÙ… ØªØ­Ø¯ÙŠØ« Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨ ' . $order_label . ' Ø¥Ù„Ù‰ "' . $target_meta['label'] . '" Ø¨Ù†Ø¬Ø§Ø­.');
    $auto_sms_result = admin_send_order_sms_automation($pdo, admin_resolve_sms_status_event_key($target_status), $order);
    if (empty($auto_sms_result['skipped'])) {
        $flash_message = 'ØªÙ… ØªØ­Ø¯ÙŠØ« Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨ ' . $order_label . ' Ø¥Ù„Ù‰ "' . $target_meta['label'] . '" Ø¨Ù†Ø¬Ø§Ø­.';
        if (!empty($auto_sms_result['success'])) {
            $flash_message .= ' ØªÙ… Ø¥Ø±Ø³Ø§Ù„ SMS ØªÙ„Ù‚Ø§Ø¦ÙŠ Ù„Ù„Ø²Ø¨ÙˆÙ†.';
        } else {
            $flash_message .= ' ØªØ¹Ø°Ø± Ø¥Ø±Ø³Ø§Ù„ SMS ØªÙ„Ù‚Ø§Ø¦ÙŠ: ' . trim((string) ($auto_sms_result['error'] ?? 'Gateway error')) . '.';
        }
        admin_set_flash_message('orders', 'success', $flash_message);
    }
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    admin_set_flash_message('orders', 'danger', $exception->getMessage());
}

admin_orders_redirect($redirect);

