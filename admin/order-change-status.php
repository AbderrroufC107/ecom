<?php
ob_start();
session_start();

require_once('inc/config.php');
require_once('inc/functions.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');
require_once('inc/performance_functions.php');
require_once('inc/stock_functions.php');
require_once(dirname(__DIR__) . '/websocket/broadcast.php');

if (!function_exists('admin_orders_redirect')) {
    function admin_orders_redirect($target = 'order.php')
    { global $dbRepo;
    global $dbRepo;

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
    if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'تعذر تحديث حالة الطلب لأن البيانات المطلوبة غير مكتملة.']);
        exit;
    }
    admin_set_flash_message('orders', 'danger', 'ØªØ¹Ø°Ø± ØªØ­Ø¯ÙŠØ« Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨ Ù„Ø£Ù† Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© ØºÙŠØ± Ù…ÙƒØªÙ…Ù„Ø©.');
    admin_orders_redirect($redirect);
}

try {
    admin_ensure_order_status_log_table($pdo);
    site_security_ensure_tables($pdo);
    site_security_ensure_order_columns($pdo);

    $statement = $dbRepo->prepare('SELECT id, order_status, customer_name, customer_phone, product_name, quantity, total_price, wilaya, commune, address, delivery_type, customer_ip, device_id, employee_id, delivery_company_id, tracking_number FROM tbl_order WHERE id = ? LIMIT 1');
    $statement->execute([$order_id]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Ø§Ù„Ø·Ù„Ø¨ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨ ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯.');
    }

    // Ownership guard: a regular manager may only change/confirm their own team's
    // orders (or ones they claimed). Super Admin is unrestricted. Employees may
    // only change orders assigned to them.
    require_once('inc/employee_functions.php');
    if (!order_can_manager_change($pdo, $order_id, $_SESSION['user'] ?? [])) {
        throw new Exception('هذا الطلب ليس ضمن طلبات فريقك. اضغط «استلام الطلب» أولاً لإدخال نفسك ثم غيّر حالته.');
    }

    $current_status = admin_normalize_order_status($order['order_status'] ?? '');
    if ($current_status === $target_status) {
        throw new Exception('Ø§Ù„Ø·Ù„Ø¨ Ù…ÙˆØ¬ÙˆØ¯ Ø¨Ø§Ù„Ù Ø¹Ù„ Ù ÙŠ Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.');
    }

    if (!admin_can_transition_order_status($current_status, $target_status)) {
        $current_meta = admin_get_order_status_meta($current_status);
        $target_meta = admin_get_order_status_meta($target_status);
        throw new Exception('Ù„Ø§ ÙŠÙ…ÙƒÙ† Ù†Ù‚Ù„ Ø§Ù„Ø·Ù„Ø¨ Ù…Ù† Ø­Ø§Ù„Ø© "' . $current_meta['label'] . '" Ø¥Ù„Ù‰ "' . $target_meta['label'] . '".');
    }

    // Phase 8: Prevent manual API status changes
    $api_statuses = ['قيد النقل', 'في مركز التوزيع', 'خرج للتوزيع', 'تم التسليم', 'فشل التسليم', 'مؤجل', 'مرتجع', 'ملغي', 'رفض العميل', 'بانتظار إعادة المحاولة', 'Delivered', 'Returned', 'Cancelled'];
    if (!empty($order['delivery_company_id']) && !empty($order['tracking_number'])) {
        if (in_array($target_status, $api_statuses)) {
            throw new Exception('لا يمكن تغيير حالة الشحن يدوياً لأن هذا الطلب مرتبط بشركة توصيل عبر API.');
        }
    }

    $pdo->beginTransaction();

    $statement = $dbRepo->prepare('UPDATE tbl_order SET order_status = ? WHERE id = ?');
    $statement->execute([$target_status, $order_id]);

    // Phase 8: Log to permanent timeline
    $admin_id = $_SESSION['user']['id'] ?? 0;
    $stmtTimeline = $dbRepo->prepare("INSERT INTO tbl_order_timeline (order_id, action, description, user_id) VALUES (?, ?, ?, ?)");
    $stmtTimeline->execute([$order_id, 'تحديث الحالة يدوياً', "من {$current_status} إلى {$target_status}", $admin_id]);

    admin_log_order_status_change($pdo, $order_id, $current_status, $target_status, $status_note, $changed_by);
    if ($target_status === 'Returned') {
        site_security_try_record_delivery_return($pdo, $order, 'Returned', $status_note, 'manual_order_status');
    }
    if ($target_status === 'Completed') {
        performance_ensure_tables($pdo);
        performance_auto_record_commission($pdo, $order_id);
    }
    
    // Automatically handle stock decrement/restoration based on order status
    $admin_id = $_SESSION['user']['id'] ?? null;
    stock_handle_order_status_change($pdo, $order, $current_status, $target_status, $admin_id);

    $pdo->commit();

    if (function_exists('admin_send_order_status_telegram')) {
        admin_send_order_status_telegram($pdo, $order, $current_status, $target_status, ['note' => $status_note]);
    }

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
    if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'order_id' => $order_id,
            'new_status' => $target_status,
            'new_status_label' => $target_meta['label']
        ]);
        exit;
    }
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
        exit;
    }

    admin_set_flash_message('orders', 'danger', $exception->getMessage());
}

admin_orders_redirect($redirect);

