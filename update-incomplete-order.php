<?php
require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/inc/incomplete-orders.php';
require_once __DIR__ . '/inc/site-security.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!ensure_incomplete_orders_table($pdo)) {
            echo json_encode(['success' => false, 'error' => 'incomplete_orders table unavailable']);
            exit;
        }

        $timestamp = date('Y-m-d H:i:s');
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $product_id = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int)$_POST['product_id'] : null;
        $product_name = $_POST['product_name'] ?? '';
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_phone = $_POST['customer_phone'] ?? '';
        $normalized_phone = site_security_normalize_phone($customer_phone);
        $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (int)$_POST['quantity'] : null;
        $selected_size = $_POST['selected_size'] ?? '';
        $selected_color = $_POST['selected_color'] ?? '';
        $unit_price = isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : null;
        $total_price = isset($_POST['total_price']) && $_POST['total_price'] !== '' ? (float)$_POST['total_price'] : null;
        $wilaya = $_POST['wilaya'] ?? '';
        $commune = $_POST['commune'] ?? '';
        $address = $_POST['address'] ?? '';
        $delivery_type = $_POST['delivery_type'] ?? '';

        $security_check = site_security_evaluate_order($pdo, [
            'customer_name' => $customer_name,
            'customer_phone' => $normalized_phone !== '' ? $normalized_phone : $customer_phone,
            'wilaya' => $wilaya,
            'commune' => $commune,
            'address' => $address,
            'device_id' => $_POST['device_id'] ?? null
        ]);
        if ($security_check['action'] !== 'allow') {
            site_security_record_rejected_attempt($pdo, $security_check);
            echo json_encode([
                'success' => false,
                'blocked' => true,
                'action' => $security_check['action'],
                'status' => $security_check['status'],
                'message' => $security_check['message'],
                'error' => 'blocked_security_rule'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $customer_phone = ($security_check['context']['phone'] ?? '') !== '' ? $security_check['context']['phone'] : $customer_phone;
        $customer_ip = $security_check['context']['ip_address'] ?? site_security_client_ip();
        $device_id = $security_check['context']['device_id'] ?? site_security_device_id();
        $user_agent = $security_check['context']['user_agent'] ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        if ($id) {
            $update_stmt = $pdo->prepare("UPDATE incomplete_orders SET
                customer_name = ?,
                customer_phone = ?,
                product_id = ?,
                product_name = ?,
                quantity = ?,
                selected_size = ?,
                selected_color = ?,
                unit_price = ?,
                total_price = ?,
                wilaya = ?,
                commune = ?,
                address = ?,
                delivery_type = ?,
                customer_ip = ?,
                device_id = ?,
                user_agent = ?,
                last_updated = ?
                WHERE id = ?");
            $update_stmt->execute([
                $customer_name,
                $customer_phone,
                $product_id,
                $product_name,
                $quantity,
                $selected_size,
                $selected_color,
                $unit_price,
                $total_price,
                $wilaya,
                $commune,
                $address,
                $delivery_type,
                $customer_ip,
                $device_id,
                $user_agent,
                $timestamp,
                $id
            ]);
        } else {
            if ($product_id !== null) {
                $update_stmt = $pdo->prepare("UPDATE incomplete_orders SET
                    customer_name = ?,
                    customer_phone = ?,
                    product_id = ?,
                    product_name = ?,
                    quantity = ?,
                    selected_size = ?,
                    selected_color = ?,
                    unit_price = ?,
                    total_price = ?,
                    wilaya = ?,
                    commune = ?,
                    address = ?,
                    delivery_type = ?,
                    customer_ip = ?,
                    device_id = ?,
                    user_agent = ?,
                    last_updated = ?
                    WHERE customer_phone = ? AND product_id = ?");
                $update_stmt->execute([
                    $customer_name,
                    $customer_phone,
                    $product_id,
                    $product_name,
                    $quantity,
                    $selected_size,
                    $selected_color,
                    $unit_price,
                    $total_price,
                    $wilaya,
                    $commune,
                    $address,
                    $delivery_type,
                    $customer_ip,
                    $device_id,
                    $user_agent,
                    $timestamp,
                    $customer_phone,
                    $product_id
                ]);
            } else {
                $update_stmt = $pdo->prepare("UPDATE incomplete_orders SET
                    customer_name = ?,
                    customer_phone = ?,
                    product_id = NULL,
                    product_name = ?,
                    quantity = ?,
                    selected_size = ?,
                    selected_color = ?,
                    unit_price = ?,
                    total_price = ?,
                    wilaya = ?,
                    commune = ?,
                    address = ?,
                    delivery_type = ?,
                    customer_ip = ?,
                    device_id = ?,
                    user_agent = ?,
                    last_updated = ?
                    WHERE customer_phone = ? AND product_id IS NULL");
                $update_stmt->execute([
                    $customer_name,
                    $customer_phone,
                    $product_name,
                    $quantity,
                    $selected_size,
                    $selected_color,
                    $unit_price,
                    $total_price,
                    $wilaya,
                    $commune,
                    $address,
                    $delivery_type,
                    $customer_ip,
                    $device_id,
                    $user_agent,
                    $timestamp,
                    $customer_phone
                ]);
            }
        }

        if ($update_stmt->rowCount() === 0) {
            $insert_stmt = $pdo->prepare("INSERT INTO incomplete_orders (
                customer_name, customer_phone, product_id, product_name,
                quantity, selected_size, selected_color, unit_price, total_price,
                wilaya, commune, address, delivery_type, customer_ip, device_id, user_agent, created_at, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $insert_stmt->execute([
                $customer_name,
                $customer_phone,
                $product_id,
                $product_name,
                $quantity,
                $selected_size,
                $selected_color,
                $unit_price,
                $total_price,
                $wilaya,
                $commune,
                $address,
                $delivery_type,
                $customer_ip,
                $device_id,
                $user_agent,
                $timestamp,
                $timestamp
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Error updating incomplete order: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
