<?php
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/admin/inc/config.php';
    require_once __DIR__ . '/admin/inc/CSRF_Protect.php';
    require_once __DIR__ . '/inc/rate-limiter.php';

    // 1. Rate Limiting Check
    $limiter = new PublicRateLimiter($pdo);
    $limiter->check('save_incomplete_order', 10, 300, $_POST['device_id'] ?? null, true);

    // 2. CSRF token validation
    $csrf = new CSRF_Protect();
    if (!$csrf->isTokenValid($_POST['_csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF_INVALID', 'message' => 'CSRF validation failed.']);
        exit;
    }

    $name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $phone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';

    if ($name && $phone) {
        require_once __DIR__ . '/inc/incomplete-orders.php';
        require_once __DIR__ . '/inc/site-security.php';

        $security_check = site_security_evaluate_order($pdo, [
            'customer_name' => $name,
            'customer_phone' => $phone,
            'wilaya' => $_POST['wilaya'] ?? '',
            'commune' => $_POST['commune'] ?? '',
            'address' => $_POST['address'] ?? '',
            'device_id' => $_POST['device_id'] ?? null
        ]);
        if ($security_check['action'] !== 'allow') {
            site_security_record_rejected_attempt($pdo, $security_check);
            echo json_encode([
                'success' => false,
                'blocked' => true,
                'action' => $security_check['action'],
                'status' => $security_check['status'],
                'message' => $security_check['message']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (($security_check['context']['phone'] ?? '') !== '') {
            $phone = $security_check['context']['phone'];
        }
        require_once __DIR__ . '/assets/telegram-notification.php';

        if (!ensure_incomplete_orders_table($pdo)) {
            http_response_code(500);
            exit;
        }

        $product_id = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int)$_POST['product_id'] : null;
        $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
        $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (int)$_POST['quantity'] : null;
        $unit_price = isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : null;
        $total_price = isset($_POST['total_price']) && $_POST['total_price'] !== '' ? (float)$_POST['total_price'] : null;
        $selected_size = isset($_POST['selected_size']) ? trim($_POST['selected_size']) : '';
        $selected_color = isset($_POST['selected_color']) ? trim($_POST['selected_color']) : '';
        $selected_color_name = $selected_color;
        if ($selected_color !== '' && ctype_digit($selected_color)) {
            try {
                $stmt = $pdo->prepare("SELECT color_name FROM tbl_color WHERE color_id = ? LIMIT 1");
                $stmt->execute([$selected_color]);
                $color_name = $stmt->fetchColumn();
                if ($color_name) {
                    $selected_color_name = $color_name;
                }
            } catch (Exception $e) {
                $selected_color_name = $selected_color;
            }
            if ($selected_color_name === $selected_color && ctype_digit($selected_color)) {
                $selected_color_name = '';
            }
        }
        $wilaya = isset($_POST['wilaya']) ? trim($_POST['wilaya']) : '';
        $commune = isset($_POST['commune']) ? trim($_POST['commune']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $delivery_type = isset($_POST['delivery_type']) ? trim($_POST['delivery_type']) : '';
        $customer_ip = $security_check['context']['ip_address'] ?? site_security_client_ip();
        $device_id = $security_check['context']['device_id'] ?? site_security_device_id();
        $user_agent = $security_check['context']['user_agent'] ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $source = isset($_POST['source']) ? trim($_POST['source']) : 'site';

        $is_new = true;
        $record_id = null;

        if ($product_id !== null) {
            $stmt = $pdo->prepare("SELECT id FROM incomplete_orders WHERE customer_phone = ? AND product_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$phone, $product_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM incomplete_orders WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$phone]);
        }
        $existing_id = $stmt->fetchColumn();

        if ($existing_id) {
            $is_new = false;
            $record_id = $existing_id;
            $update = $pdo->prepare("UPDATE incomplete_orders SET customer_name=?, product_id=?, product_name=?, quantity=?, unit_price=?, total_price=?, selected_size=?, selected_color=?, wilaya=?, commune=?, address=?, delivery_type=?, customer_ip=?, device_id=?, user_agent=?, last_updated=NOW() WHERE id=?");
            $update->execute([$name, $product_id, $product_name, $quantity, $unit_price, $total_price, $selected_size, $selected_color, $wilaya, $commune, $address, $delivery_type, $customer_ip, $device_id, $user_agent, $record_id]);
        } else {
            $insert = $pdo->prepare("INSERT INTO incomplete_orders (customer_name, customer_phone, product_id, product_name, quantity, unit_price, total_price, selected_size, selected_color, wilaya, commune, address, delivery_type, customer_ip, device_id, user_agent, created_at, last_updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())");
            $insert->execute([$name, $phone, $product_id, $product_name, $quantity, $unit_price, $total_price, $selected_size, $selected_color, $wilaya, $commune, $address, $delivery_type, $customer_ip, $device_id, $user_agent]);
            $record_id = $pdo->lastInsertId();
        }

        // Telegram notification for incomplete orders
        $settings = [];
        try {
            $stmt = $pdo->query("SELECT telegram_bot_token, telegram_chat_id, telegram_incomplete_enabled, telegram_incomplete_chat_id, telegram_incomplete_bot_token FROM tbl_settings WHERE id=1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $settings = [];
        }

        $telegram_bot_token = $settings['telegram_bot_token'] ?? '';
        $telegram_chat_id = $settings['telegram_chat_id'] ?? '';
        $telegram_incomplete_enabled = isset($settings['telegram_incomplete_enabled']) ? (int)$settings['telegram_incomplete_enabled'] : 0;
        $telegram_incomplete_chat_id = $settings['telegram_incomplete_chat_id'] ?? '';
        $telegram_incomplete_bot_token = $settings['telegram_incomplete_bot_token'] ?? '';
        if ($telegram_incomplete_chat_id === '') {
            $telegram_incomplete_chat_id = $telegram_chat_id;
        }
        if ($telegram_incomplete_bot_token === '') {
            $telegram_incomplete_bot_token = $telegram_bot_token;
        }

        if ($telegram_incomplete_enabled && $telegram_incomplete_bot_token && $telegram_incomplete_chat_id) {
            $telegram = new TelegramNotification($telegram_incomplete_bot_token, $telegram_incomplete_chat_id);
            $telegram->sendIncompleteOrderNotification([
                'incomplete_id' => $record_id,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'product_id' => $product_id,
                'product_name' => $product_name,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'selected_size' => $selected_size,
                'selected_color' => $selected_color_name,
                'wilaya' => $wilaya,
                'commune' => $commune,
                'address' => $address,
                'delivery_type' => $delivery_type,
                'source' => $source,
                'is_update' => !$is_new
            ]);
        }
    }
}
?>
