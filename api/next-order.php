<?php
declare(strict_types=1);

require_once __DIR__ . '/next-common.php';
require_once __DIR__ . '/../assets/telegram-notification.php';
require_once __DIR__ . '/../websocket/broadcast.php';

next_api_headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    next_json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.'], 405);
}

function next_order_fail(string $message, int $status = 422, array $extra = []): void
{
    next_json(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

try {
    $data = next_read_payload();
    $productId = next_resolve_product_id($data['product_id'] ?? $data['id'] ?? '');
    if ($productId <= 0) {
        next_order_fail('اختر منتجا صحيحا قبل إرسال الطلب.');
    }

    $payload = next_load_product_payload($pdo, $productId);
    if (empty($payload)) {
        next_order_fail('المنتج غير متاح حاليا.', 404);
    }

    $product = $payload['product'];
    $name = next_text($data['customer_name'] ?? $data['name'] ?? '');
    $phone = site_security_normalize_phone($data['customer_phone'] ?? $data['phone'] ?? '');
    $wilaya = next_text($data['wilaya'] ?? '');
    $commune = next_text($data['commune'] ?? '');
    $address = next_text($data['address'] ?? '');
    $size = trim((string) ($data['selected_size'] ?? ''));
    $color = trim((string) ($data['selected_color'] ?? ''));
    $deviceId = trim((string) ($data['device_id'] ?? ''));
    $requestedDeliveryType = trim((string) ($data['delivery_type'] ?? ''));
    $quantity = max(1, (int) ($data['quantity'] ?? 1));

    if (mb_strlen($name, 'UTF-8') < 3 || mb_strlen($name, 'UTF-8') > 60) {
        next_order_fail('اكتب اسم العميل بشكل واضح.');
    }
    if (!preg_match('/^0[567][0-9]{8}$/', $phone)) {
        next_order_fail('رقم الهاتف غير صحيح. استعمل رقما جزائريا من 10 أرقام.');
    }
    if ($wilaya === '') {
        next_order_fail('اختر الولاية.');
    }
    if ($commune === '') {
        next_order_fail('اكتب البلدية أو المدينة.');
    }
    if ($address === '') {
        next_order_fail('اكتب العنوان حتى يتم تأكيد التوصيل.');
    }

    site_security_ensure_order_columns($pdo);
    $security = site_security_reject_if_needed($pdo, [
        'customer_name' => $name,
        'customer_phone' => $phone,
        'wilaya' => $wilaya,
        'commune' => $commune,
        'address' => $address,
        'device_id' => $deviceId !== '' ? $deviceId : null,
    ]);
    $phone = $security['context']['phone'] ?? $phone;

    $phoneVariants = function_exists('site_security_phone_variants') ? site_security_phone_variants($phone) : [$phone];
    $phonePlaceholders = implode(',', array_fill(0, count($phoneVariants), '?'));
    $pending = $pdo->prepare("SELECT id, product_name, order_date FROM tbl_order WHERE customer_phone IN ($phonePlaceholders) AND LOWER(order_status) = 'pending' ORDER BY order_date DESC LIMIT 1");
    $pending->execute($phoneVariants);
    $pendingOrder = $pending->fetch(PDO::FETCH_ASSOC);
    if ($pendingOrder) {
        next_order_fail('لديك طلب قيد التأكيد بنفس رقم الهاتف. انتظر اتصال فريق المتجر قبل إرسال طلب جديد.', 409, [
            'pendingOrder' => [
                'id' => (int) ($pendingOrder['id'] ?? 0),
                'productName' => next_text($pendingOrder['product_name'] ?? ''),
                'date' => (string) ($pendingOrder['order_date'] ?? ''),
            ],
        ]);
    }

    $selectedOfferId = (int) ($data['offer_id'] ?? 0);
    $unitPrice = (float) ($product['price'] ?? 0);
    foreach ($payload['offers'] as $offer) {
        if ((int) ($offer['id'] ?? 0) === $selectedOfferId) {
            $quantity = max(1, (int) ($offer['qty'] ?? 1));
            $unitPrice = (float) ($offer['unitPrice'] ?? $unitPrice);
            break;
        }
    }

    $delivery = $payload['delivery'];
    $deliveryMode = (string) ($delivery['mode'] ?? 'home_office');
    $deliveryPrices = $delivery['prices'] ?? [];
    $deliveryType = function_exists('resolve_delivery_type_by_mode')
        ? resolve_delivery_type_by_mode($requestedDeliveryType, $deliveryMode)
        : $requestedDeliveryType;

    if (function_exists('resolve_available_delivery_type_for_wilaya')) {
        $resolvedType = resolve_available_delivery_type_for_wilaya($deliveryPrices, $wilaya, $deliveryMode, $deliveryType);
        if ($resolvedType !== '') {
            $deliveryType = $resolvedType;
        }
    }

    $available = function_exists('get_available_delivery_prices_for_wilaya')
        ? get_available_delivery_prices_for_wilaya($deliveryPrices, $wilaya, $deliveryMode)
        : ($deliveryPrices[$wilaya] ?? []);

    if ($deliveryMode !== 'free' && empty($available)) {
        next_order_fail('لا يوجد سعر توصيل متاح لهذه الولاية حاليا.');
    }

    $shippingFee = 0.0;
    if ($deliveryMode !== 'free') {
        $shippingFee = (float) ($available[$deliveryType] ?? 0);
    }
    $total = ($quantity * $unitPrice) + $shippingFee;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO tbl_order (
        product_id, product_name, order_size, order_color, quantity, unit_price, total_price,
        customer_name, customer_phone, wilaya, commune, delivery_type, address,
        customer_ip, device_id, user_agent, order_status, order_date
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $productId,
        $product['name'],
        $size !== '' ? $size : null,
        $color !== '' ? $color : null,
        $quantity,
        $unitPrice,
        $total,
        $name,
        $phone,
        $wilaya,
        $commune,
        $deliveryType,
        $address,
        $security['context']['ip_address'] ?? site_security_client_ip(),
        $security['context']['device_id'] ?? site_security_device_id($deviceId),
        $security['context']['user_agent'] ?? substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'pending',
        date('Y-m-d H:i:s'),
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->commit();

    // WebSocket broadcast - notify admin of new order
    ws_broadcast_order_new([
        'id' => $orderId,
        'customer_name' => $name,
        'customer_phone' => $phone,
        'wilaya' => $wilaya,
        'commune' => $commune,
        'delivery_type' => $deliveryType,
        'product_name' => $product['name'],
        'quantity' => $quantity,
        'total_price' => $total,
        'order_status' => 'pending',
        'order_date' => date('Y-m-d H:i:s'),
    ]);

    $settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];
    if (!empty($settings['telegram_orders_enabled']) && !empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id'])) {
        try {
            $telegram = new TelegramNotification($settings['telegram_bot_token'], $settings['telegram_chat_id']);
            $telegram->sendOrderNotification([
                'customer_name' => $name,
                'customer_phone' => $phone,
                'wilaya' => $wilaya,
                'commune' => $commune,
                'delivery_type' => $deliveryType,
                'product_name' => $product['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $total,
                'selected_size' => $size,
                'selected_color' => $color,
            ]);
        } catch (Throwable $e) {
            error_log('Telegram order notification failed for Next order #' . $orderId . ': ' . $e->getMessage());
        }
    }

    next_json([
        'success' => true,
        'message' => 'تم تسجيل الطلب بنجاح.',
        'order' => [
            'id' => $orderId,
            'status' => 'pending',
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'shippingFee' => $shippingFee,
            'total' => $total,
            'deliveryType' => $deliveryType,
        ],
        'pixel' => [
            'event' => 'Purchase',
            'value' => $total,
            'currency' => 'DZD',
            'content_ids' => [$productId],
            'content_name' => $product['name'],
            'num_items' => $quantity,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('next-order failed: ' . $e->getMessage());
    next_order_fail($e->getMessage() !== '' ? $e->getMessage() : 'تعذر تسجيل الطلب.', 422);
}
