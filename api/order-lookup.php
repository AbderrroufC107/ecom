<?php
declare(strict_types=1);

require_once __DIR__ . '/next-common.php';

next_api_headers();

if (isset($pdo)) { next_api_rate_limit($pdo, basename(__FILE__)); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    next_json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.'], 405);
}

try {
    if (function_exists('admin_ensure_order_ecotrack_columns')) {
        admin_ensure_order_ecotrack_columns($pdo);
    }

    $data = next_read_payload();
    $phone = site_security_normalize_phone($data['phone'] ?? $data['customer_phone'] ?? '');

    if (!preg_match('/^0[567][0-9]{8}$/', $phone)) {
        next_json(['success' => false, 'message' => 'أدخل رقم هاتف جزائري صحيح يبدأ بـ 05 أو 06 أو 07 ويتكون من 10 أرقام.'], 422);
    }

    $variants = site_security_phone_variants($phone);
    if (empty($variants)) {
        next_json(['success' => false, 'message' => 'رقم الهاتف غير صحيح.'], 422);
    }

    $placeholders = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $pdo->prepare("
        SELECT id, product_id, product_name, quantity, unit_price, total_price,
               customer_name, customer_phone, wilaya, commune, delivery_type, order_status,
               order_date, ecotrack_tracking, ecotrack_remote_status, ecotrack_remote_time
        FROM tbl_order
        WHERE customer_phone IN ($placeholders)
        ORDER BY order_date DESC, id DESC
        LIMIT 30
    ");
    $stmt->execute($variants);

    $orders = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $orders[] = [
            'id' => (int) ($row['id'] ?? 0),
            'publicId' => (string) ($row['id'] ?? ''),
            'productId' => (int) ($row['product_id'] ?? 0),
            'productName' => next_text($row['product_name'] ?? ''),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'unitPrice' => (float) ($row['unit_price'] ?? 0),
            'totalPrice' => (float) ($row['total_price'] ?? 0),
            'customerName' => next_text($row['customer_name'] ?? ''),
            'phone' => site_security_normalize_phone($row['customer_phone'] ?? ''),
            'wilaya' => next_text($row['wilaya'] ?? ''),
            'commune' => next_text($row['commune'] ?? ''),
            'deliveryType' => next_text($row['delivery_type'] ?? ''),
            'status' => next_text($row['order_status'] ?? ''),
            'orderDate' => (string) ($row['order_date'] ?? ''),
            'tracking' => next_text($row['ecotrack_tracking'] ?? ''),
            'remoteStatus' => next_text($row['ecotrack_remote_status'] ?? ''),
            'remoteTime' => (string) ($row['ecotrack_remote_time'] ?? ''),
        ];
    }

    next_json([
        'success' => true,
        'phone' => $phone,
        'orders' => $orders,
    ]);
} catch (Throwable $e) {
    error_log('order-lookup failed: ' . $e->getMessage());
    next_json(['success' => false, 'message' => 'تعذر البحث عن الطلبات حاليا.'], 500);
}
