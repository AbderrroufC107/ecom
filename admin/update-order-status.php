<?php
session_start();
if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
    http_response_code(403);
    exit;
}
require_once('inc/config.php');
require_once('inc/functions.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');

if(isset($_POST['order_id']) && isset($_POST['status'])) {
    site_security_ensure_tables($pdo);
    site_security_ensure_order_columns($pdo);
    $target_status = admin_normalize_order_status($_POST['status']);
    $order_lookup = $pdo->prepare("SELECT id, order_id, order_status, customer_name, customer_phone, wilaya, commune, address, delivery_type, customer_ip, device_id FROM tbl_order WHERE order_id=? LIMIT 1");
    $order_lookup->execute([$_POST['order_id']]);
    $order = $order_lookup->fetch(PDO::FETCH_ASSOC) ?: [];
    $statement = $pdo->prepare("UPDATE tbl_order SET order_status=? WHERE order_id=?");
    $statement->execute(array($target_status, $_POST['order_id']));
    if ($target_status === 'Returned' && $order) {
        site_security_try_record_delivery_return($pdo, $order, 'Returned', 'manual quick status update', 'manual_quick_status');
    }
    echo 'success';
}
?> 

