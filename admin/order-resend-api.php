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
    // Determine the delivery company from tbl_order or let the manager choose it.
    // Assuming the user has set the delivery_company_id previously, or we need to pass it as a param.
    // For now, let's fetch the delivery_company_id from the order.
    $stmt = $dbRepo->prepare("SELECT delivery_company_id FROM tbl_order WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (empty($order['delivery_company_id'])) {
        throw new Exception("لم يتم تحديد شركة توصيل لهذا الطلب. يرجى تعديل الطلب وتحديد شركة توصيل.");
    }

    $result = DeliveryManager::sendOrder($pdo, $order_id, $order['delivery_company_id']);
    admin_set_flash_message('orders', 'success', "تم إرسال الطلب لشركة التوصيل بنجاح. رقم التتبع: " . $result['tracking_number']);
} catch (Exception $e) {
    admin_set_flash_message('orders', 'danger', "فشل إرسال الطلب لشركة التوصيل: " . $e->getMessage());
}

header("Location: order-details.php?id=" . $order_id . "#tab_api_logs");
exit;
