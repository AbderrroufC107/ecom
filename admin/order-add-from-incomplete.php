<?php require_once('header.php'); ?>
<?php
require_once __DIR__ . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/site-security.php';

if (!isset($_GET['id'])) { header('Location: incomplete-orders.php'); exit; }
$id = (int)$_GET['id'];

// جلب السجل
$stmt = $pdo->prepare("SELECT * FROM incomplete_orders WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { header('Location: incomplete-orders.php'); exit; }

if (!function_exists('admin_normalize_phone_for_compare')) {
    function admin_normalize_phone_for_compare($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '213') === 0) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) === 9) {
            $digits = '0' . $digits;
        }
        return $digits;
    }
}

$raw_phone = trim((string)($row['customer_phone'] ?? ''));
$blocked_phone = site_security_normalize_phone($raw_phone);
$security_check = site_security_evaluate_order($pdo, [
    'customer_name' => $row['customer_name'] ?? '',
    'customer_phone' => $blocked_phone !== '' ? $blocked_phone : $raw_phone,
    'wilaya' => $row['wilaya'] ?? '',
    'commune' => $row['commune'] ?? '',
    'address' => $row['address'] ?? $row['customer_address'] ?? '',
    'device_id' => $row['device_id'] ?? null
]);
if ($security_check['action'] !== 'allow') {
    site_security_record_rejected_attempt($pdo, $security_check);
    $_SESSION['incomplete_flash'] = [
        'type' => 'danger',
        'message' => 'لا يمكن تحويل هذا السجل: ' . $security_check['message']
    ];
    header('Location: incomplete-orders.php');
    exit;
}
$raw_phone = ($security_check['context']['phone'] ?? '') !== '' ? $security_check['context']['phone'] : $raw_phone;
$normalized_phone = admin_normalize_phone_for_compare($raw_phone);
$phone_variants = function_exists('site_security_phone_variants')
    ? site_security_phone_variants($raw_phone)
    : array_values(array_unique(array_filter([
        $raw_phone,
        $normalized_phone,
        ltrim($normalized_phone, '0')
    ])));

if (!empty($phone_variants)) {
    $placeholders = implode(',', array_fill(0, count($phone_variants), '?'));
    $check = $pdo->prepare("SELECT id, order_status FROM tbl_order WHERE customer_phone IN ($placeholders) ORDER BY id DESC LIMIT 1");
    $check->execute($phone_variants);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $_SESSION['incomplete_flash'] = [
            'type' => 'danger',
            'message' => 'لا يمكن تحويل هذا السجل: رقم الهاتف مسجل مسبقاً في الطلب #' . (int)$existing['id'] . '.'
        ];
        header('Location: incomplete-orders.php');
        exit;
    }
}

// تحويل السجل بنفس المعلومات الموجودة فقط (بدون قيم افتراضية مزيفة).
$product_id = isset($row['product_id']) ? (int)$row['product_id'] : 0;
$product_name = trim((string)($row['product_name'] ?? ''));
$customer_name = trim((string)($row['customer_name'] ?? ''));
$quantity = isset($row['quantity']) ? (int)$row['quantity'] : 0;
$unit_price = isset($row['unit_price']) ? (float)$row['unit_price'] : 0.0;
$total_price = isset($row['total_price']) ? (float)$row['total_price'] : 0.0;
$order_size = trim((string)($row['selected_size'] ?? ''));
$order_color = trim((string)($row['selected_color'] ?? ''));
$wilaya = trim((string)($row['wilaya'] ?? ''));
$commune = trim((string)($row['commune'] ?? ''));
$delivery_type = trim((string)($row['delivery_type'] ?? ''));
$address = trim((string)($row['address'] ?? ''));
$customer_ip = trim((string)($row['customer_ip'] ?? ''));
$device_id = site_security_device_id($row['device_id'] ?? '');
$user_agent = substr((string)($row['user_agent'] ?? ''), 0, 255);

if ($product_id <= 0 || $product_name === '' || $customer_name === '' || $raw_phone === '') {
    $_SESSION['incomplete_flash'] = [
        'type' => 'danger',
        'message' => 'لا يمكن التحويل: السجل غير المكتمل لا يحتوي على معلومات كافية لإنشاء طلب فعلي.'
    ];
    header('Location: incomplete-orders.php');
    exit;
}

if ($quantity <= 0) {
    $quantity = 1;
}
if ($unit_price < 0) {
    $unit_price = 0.0;
}
if ($total_price <= 0) {
    $total_price = $quantity * $unit_price;
}

site_security_ensure_order_columns($pdo);
$insert = $pdo->prepare("INSERT INTO tbl_order (customer_id, product_id, order_size, order_color, product_name, quantity, unit_price, total_price, customer_name, customer_type, customer_phone, wilaya, commune, delivery_type, address, customer_ip, device_id, user_agent, order_date, order_status) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'direct', ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')");
$insert->execute([
    $product_id,
    $order_size !== '' ? $order_size : null,
    $order_color !== '' ? $order_color : null,
    $product_name,
    $quantity,
    $unit_price,
    $total_price,
    $customer_name,
    $raw_phone,
    $wilaya,
    $commune,
    $delivery_type,
    $address,
    $customer_ip,
    $device_id,
    $user_agent
]);
$created_order_id = (int) $pdo->lastInsertId();
$created_order_context = [
    'id' => $created_order_id,
    'customer_name' => $customer_name,
    'customer_phone' => $raw_phone,
    'product_name' => $product_name,
    'quantity' => $quantity,
    'total_price' => $total_price,
    'order_status' => 'Pending',
    'status' => 'Pending',
    'wilaya' => $wilaya,
    'commune' => $commune,
    'address' => $address,
    'delivery_type' => $delivery_type,
    'customer_ip' => $customer_ip,
    'device_id' => $device_id
];
$auto_sms_result = admin_send_order_sms_automation($pdo, 'order_created', $created_order_context);
if (empty($auto_sms_result['skipped']) && empty($auto_sms_result['success'])) {
    error_log('Automatic SMS failed for order #' . $created_order_id . ': ' . trim((string) ($auto_sms_result['error'] ?? 'Gateway error')));
}

// حذف السجل من غير المكتملة
$del = $pdo->prepare("DELETE FROM incomplete_orders WHERE id = ?");
$del->execute([$id]);

$_SESSION['incomplete_flash'] = [
    'type' => 'success',
    'message' => 'تم تحويل السجل غير المكتمل إلى طلب جديد بنجاح.'
];
header('Location: order.php');
exit;
?>


