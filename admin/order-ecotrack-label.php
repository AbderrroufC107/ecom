<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$inline_view = !empty($_GET['inline']) || !empty($_GET['print']);
$redirect = $order_id > 0 ? 'order-details.php?id=' . $order_id : 'order.php';

admin_ensure_ecotrack_setting_columns($pdo);
admin_ensure_order_ecotrack_columns($pdo);

$statement = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
$statement->execute([$order_id]);
$order = $statement->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    admin_set_flash_message('orders', 'danger', 'الطلب غير موجود.');
    header('Location: order.php');
    exit;
}

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
if (!ecotrack_is_configured($settings)) {
    admin_set_flash_message('orders', 'danger', 'إعدادات ECOTRACK غير مكتملة. أضف التوكن أولاً من الإعدادات.');
    header('Location: ' . $redirect);
    exit;
}

$tracking = trim((string) ($order['ecotrack_tracking'] ?? ''));
if ($tracking === '') {
    admin_set_flash_message('orders', 'warning', 'لا يمكن تنزيل الليبل قبل إرسال الطلب إلى ECOTRACK.');
    header('Location: ' . $redirect);
    exit;
}

list($base_ok, $base_url, $base_error) = ecotrack_resolve_base_url($pdo, $settings);
if (!$base_ok || $base_url === '') {
    admin_set_flash_message('orders', 'danger', $base_error !== '' ? $base_error : 'تعذر تحديد رابط ECOTRACK.');
    header('Location: ' . $redirect);
    exit;
}

$url = rtrim($base_url, '/') . '/api/v1/get/order/label?tracking=' . rawurlencode($tracking);
$headers = [
    'Accept: application/pdf',
    'Authorization: Bearer ' . $settings['ecotrack_api_token']
];

$response = '';
$status_code = 0;
$error = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    if ($ch === false) {
        admin_set_flash_message('orders', 'danger', 'Unable to initialize cURL.');
        header('Location: ' . $redirect);
        exit;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    list($response, $error) = ecotrack_curl_execute($ch);
    $status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers)
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        $response = '';
    }

    if (!empty($http_response_header) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $matches)) {
        $status_code = (int) $matches[1];
    }
}

$json = ecotrack_json_decode($response);
if ($status_code < 200 || $status_code >= 300 || $response === '' || is_array($json)) {
    $error_text = '';

    if (is_array($json) && !empty($json['message'])) {
        $error_text = trim((string) $json['message']);
    }
    if ($error_text === '' && !empty($error)) {
        $error_text = trim((string) $error);
    }
    if ($error_text === '') {
        $error_text = 'تعذر تنزيل الليبل من ECOTRACK.';
    }

    admin_set_flash_message('orders', 'danger', $error_text);
    header('Location: ' . $redirect);
    exit;
}

$filename = 'ecotrack-label-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $tracking) . '.pdf';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($inline_view ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Content-Length: ' . strlen($response));
echo $response;
exit;
