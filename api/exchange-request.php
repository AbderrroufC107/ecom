<?php
declare(strict_types=1);

require_once __DIR__ . '/next-common.php';
require_once __DIR__ . '/../inc/exchange-requests.php';

next_api_headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    next_json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.'], 405);
}

function exchange_api_fail(string $message, int $status = 422, array $extra = []): void
{
    next_json(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

function exchange_api_fetch_orders(PDO $pdo, array $phoneVariants): array
{
    $placeholders = implode(',', array_fill(0, count($phoneVariants), '?'));
    $stmt = $pdo->prepare("
        SELECT o.id, o.product_id, o.product_name, o.quantity, o.unit_price, o.total_price,
               o.customer_name, o.customer_phone, o.wilaya, o.commune, o.delivery_type,
               o.order_status, o.order_date, o.ecotrack_tracking, o.ecotrack_remote_status,
               o.ecotrack_remote_time, p.p_qty AS product_stock
        FROM tbl_order o
        LEFT JOIN tbl_product p ON p.p_id = o.product_id
        WHERE o.customer_phone IN ($placeholders)
        ORDER BY o.order_date DESC, o.id DESC
        LIMIT 30
    ");
    $stmt->execute($phoneVariants);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function exchange_api_has_open_request(PDO $pdo, int $orderId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE order_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$orderId]);
    return (int) $stmt->fetchColumn() > 0;
}

function exchange_api_eligibility(PDO $pdo, array $order): array
{
    $quantity = max(1, (int) ($order['quantity'] ?? 1));
    $stock = isset($order['product_stock']) ? (int) $order['product_stock'] : 0;
    $deliveredAt = exchange_requests_delivery_time($order);
    $hours = exchange_requests_hours_since_delivery($order);
    $eligible = true;
    $reason = '';

    if ((int) ($order['product_id'] ?? 0) <= 0) {
        $eligible = false;
        $reason = 'الطلب غير مربوط بمنتج صالح.';
    } elseif (!$deliveredAt) {
        $eligible = false;
        $reason = 'التبديل متاح فقط بعد تسجيل حالة التسليم ووقت التسليم من شركة التوصيل.';
    } elseif ($hours !== null && $hours > 72) {
        $eligible = false;
        $reason = 'انتهت مهلة التبديل: يجب أن يكون الطلب خلال 72 ساعة من التسليم.';
    } elseif ($stock < $quantity) {
        $eligible = false;
        $reason = 'المخزون الحالي لا يكفي لتبديل هذا المنتج.';
    } elseif (exchange_api_has_open_request($pdo, (int) ($order['id'] ?? 0))) {
        $eligible = false;
        $reason = 'يوجد طلب تبديل مفتوح لهذا الطلب.';
    }

    return [
        'eligible' => $eligible,
        'reason' => $reason,
        'deliveredAt' => $deliveredAt ? $deliveredAt->format('Y-m-d H:i:s') : '',
        'hoursSinceDelivery' => $hours !== null ? round($hours, 2) : null,
        'stock' => $stock,
    ];
}

function exchange_api_order_payload(PDO $pdo, array $order): array
{
    $eligibility = exchange_api_eligibility($pdo, $order);

    return [
        'id' => (int) ($order['id'] ?? 0),
        'publicId' => (string) ($order['id'] ?? ''),
        'productId' => (int) ($order['product_id'] ?? 0),
        'productName' => next_text($order['product_name'] ?? ''),
        'quantity' => (int) ($order['quantity'] ?? 0),
        'totalPrice' => (float) ($order['total_price'] ?? 0),
        'customerName' => next_text($order['customer_name'] ?? ''),
        'phone' => site_security_normalize_phone($order['customer_phone'] ?? ''),
        'wilaya' => next_text($order['wilaya'] ?? ''),
        'commune' => next_text($order['commune'] ?? ''),
        'deliveryType' => next_text($order['delivery_type'] ?? ''),
        'status' => next_text($order['order_status'] ?? ''),
        'orderDate' => (string) ($order['order_date'] ?? ''),
        'tracking' => next_text($order['ecotrack_tracking'] ?? ''),
        'remoteStatus' => next_text($order['ecotrack_remote_status'] ?? ''),
        'remoteTime' => (string) ($order['ecotrack_remote_time'] ?? ''),
        'exchangeEligible' => $eligibility['eligible'],
        'exchangeReason' => $eligibility['reason'],
        'deliveredAt' => $eligibility['deliveredAt'],
        'hoursSinceDelivery' => $eligibility['hoursSinceDelivery'],
        'stock' => $eligibility['stock'],
    ];
}

function exchange_api_validate_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        exchange_api_fail('ارفع صورة توضح سبب التبديل.');
    }

    if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        exchange_api_fail('حجم الصورة يجب ألا يتجاوز 5MB.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        exchange_api_fail('تعذر قراءة الصورة المرفوعة.');
    }

    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) {
        exchange_api_fail('الملف المرفوع يجب أن يكون صورة صالحة.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        exchange_api_fail('صيغة الصورة غير مدعومة. استعمل JPG أو PNG أو WEBP أو GIF.');
    }

    return [$tmp, $ext];
}

try {
    if (function_exists('admin_ensure_order_ecotrack_columns')) {
        admin_ensure_order_ecotrack_columns($pdo);
    }
    exchange_requests_ensure_table($pdo);

    $data = next_read_payload();
    $action = trim((string) ($data['action'] ?? 'lookup'));
    $phone = site_security_normalize_phone($data['phone'] ?? $data['customer_phone'] ?? '');

    if (!preg_match('/^0[567][0-9]{8}$/', $phone)) {
        exchange_api_fail('أدخل رقم هاتف جزائري صحيح يبدأ بـ 05 أو 06 أو 07 ويتكون من 10 أرقام.');
    }

    $variants = site_security_phone_variants($phone);
    if (empty($variants)) {
        exchange_api_fail('رقم الهاتف غير صحيح.');
    }

    if ($action === 'lookup') {
        $orders = array_map(
            fn($order) => exchange_api_order_payload($pdo, $order),
            exchange_api_fetch_orders($pdo, $variants)
        );

        next_json([
            'success' => true,
            'phone' => $phone,
            'orders' => $orders,
        ]);
    }

    if ($action !== 'submit') {
        exchange_api_fail('إجراء غير معروف.');
    }

    $orderId = (int) ($data['order_id'] ?? 0);
    $reason = trim((string) ($data['reason'] ?? ''));
    if ($orderId <= 0) {
        exchange_api_fail('اختر الطلب المراد تبديله.');
    }
    if (mb_strlen($reason, 'UTF-8') < 5 || mb_strlen($reason, 'UTF-8') > 1000) {
        exchange_api_fail('اكتب سبب التبديل بوضوح.');
    }

    $orders = exchange_api_fetch_orders($pdo, $variants);
    $selected = null;
    foreach ($orders as $order) {
        if ((int) ($order['id'] ?? 0) === $orderId) {
            $selected = $order;
            break;
        }
    }
    if (!$selected) {
        exchange_api_fail('هذا الطلب غير مرتبط برقم الهاتف المدخل.', 404);
    }

    $eligibility = exchange_api_eligibility($pdo, $selected);
    if (!$eligibility['eligible']) {
        exchange_api_fail($eligibility['reason'] !== '' ? $eligibility['reason'] : 'هذا الطلب غير مؤهل للتبديل.');
    }

    [$tmp, $ext] = exchange_api_validate_image($_FILES['proof_image'] ?? []);
    $uploadDir = __DIR__ . '/../assets/uploads/exchange-requests';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        exchange_api_fail('تعذر إنشاء مجلد صور التبديل.', 500);
    }

    $token = bin2hex(random_bytes(5));
    $fileName = 'exchange-' . $orderId . '-' . time() . '-' . $token . '.' . $ext;
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmp, $target)) {
        exchange_api_fail('تعذر حفظ صورة التبديل.', 500);
    }

    $relativePath = 'exchange-requests/' . $fileName;
    $stmt = $pdo->prepare("
        INSERT INTO exchange_requests
            (order_id, product_id, customer_name, customer_phone, product_name, quantity, reason, proof_image, status, delivered_at, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([
        $orderId,
        (int) ($selected['product_id'] ?? 0),
        next_text($selected['customer_name'] ?? ''),
        $phone,
        next_text($selected['product_name'] ?? ''),
        max(1, (int) ($selected['quantity'] ?? 1)),
        $reason,
        $relativePath,
        $eligibility['deliveredAt'],
    ]);

    next_json([
        'success' => true,
        'message' => 'تم تسجيل طلب التبديل بنجاح. سيتم مراجعته من الإدارة.',
        'request' => [
            'id' => (int) $pdo->lastInsertId(),
            'status' => 'pending',
        ],
    ]);
} catch (Throwable $e) {
    error_log('exchange-request failed: ' . $e->getMessage());
    next_json(['success' => false, 'message' => 'تعذر معالجة طلب التبديل حاليا.'], 500);
}
