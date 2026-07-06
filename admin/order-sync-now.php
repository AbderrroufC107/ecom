<?php
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/integration/DeliveryManager.php');

$order_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($order_id <= 0) {
    admin_set_flash_message('orders', 'danger', 'معرف الطلب غير صالح.');
    header('Location: order.php');
    exit;
}

try {
    $result = DeliveryManager::syncOrder($pdo, $order_id);
    admin_set_flash_message('orders', 'success', "تم تحديث حالة الطلب بنجاح. الحالة الحالية: " . $result['new_status']);
} catch (Exception $e) {
    admin_set_flash_message('orders', 'danger', "خطأ أثناء المزامنة: " . $e->getMessage());
}

header("Location: order-details.php?id=" . $order_id . "#tab_api_logs");
exit;
