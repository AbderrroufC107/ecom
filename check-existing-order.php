<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// تضمين ملف التكوين
require_once('admin/inc/config.php');
require_once('inc/site-security.php');

// دالة للتحقق من وجود طلبات سابقة برقم الهاتف
function checkExistingOrder($pdo, $customer_phone) {
    try {
        // التحقق من الطلبات المعلقة (pending)
        $phone_variants = function_exists('site_security_phone_variants') ? site_security_phone_variants($customer_phone) : [$customer_phone];
        if (!$phone_variants) {
            return ['exists' => false, 'message' => 'Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ Ù…Ø·Ù„ÙˆØ¨'];
        }
        $placeholders = implode(',', array_fill(0, count($phone_variants), '?'));
        $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE customer_phone IN ($placeholders) AND LOWER(order_status) = 'pending' ORDER BY order_date DESC LIMIT 1");
        $stmt->execute($phone_variants);
        $pending_order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pending_order) {
            $order_id = $pending_order['order_id'] ?? ($pending_order['id'] ?? null);
            return [
                'exists' => true,
                'order_id' => $order_id,
                'order_date' => $pending_order['order_date'],
                'product_name' => $pending_order['product_name'],
                'status' => 'pending',
                'message' => 'لقد قمت بالطلب مسبقاً! انتظر للاتصال بك أو تأكيد الطلب.'
            ];
        }
        
        // ملاحظة: لا نمنع الطلبات غير المكتملة، يمكن للعميل إرسال طلب جديد
        
        return ['exists' => false, 'message' => 'لا توجد طلبات سابقة'];
        
    } catch (PDOException $e) {
        error_log('خطأ في التحقق من الطلبات السابقة: ' . $e->getMessage());
        return ['exists' => false, 'message' => 'خطأ في التحقق من الطلبات السابقة'];
    }
}

// معالجة الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'])) {
    $phone = trim($_POST['phone']);
    
    if (empty($phone)) {
        echo json_encode(['exists' => false, 'message' => 'رقم الهاتف مطلوب']);
        exit;
    }
    
    $security_check = site_security_evaluate_order($pdo, [
        'customer_name' => $_POST['customer_name'] ?? $_POST['name'] ?? '',
        'customer_phone' => $phone,
        'wilaya' => $_POST['wilaya'] ?? '',
        'commune' => $_POST['commune'] ?? '',
        'address' => $_POST['address'] ?? $_POST['customer_address'] ?? '',
        'device_id' => $_POST['device_id'] ?? null
    ]);
    if ($security_check['action'] !== 'allow') {
        site_security_record_rejected_attempt($pdo, $security_check);
        echo json_encode([
            'exists' => true,
            'blocked' => true,
            'action' => $security_check['action'],
            'status' => $security_check['status'],
            'message' => $security_check['message']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $phone = ($security_check['context']['phone'] ?? '') !== '' ? $security_check['context']['phone'] : $phone;
    $result = checkExistingOrder($pdo, $phone);
    echo json_encode($result);
} else {
    echo json_encode(['exists' => false, 'message' => 'طلب غير صحيح']);
}
?>
