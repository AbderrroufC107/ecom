<?php
require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/inc/site-security.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cart_id = $_POST['cart_id'];
        $product_id = $_POST['product_id'];
        $timestamp = date('Y-m-d H:i:s');
        $customer_phone = site_security_normalize_phone($_POST['customer_phone'] ?? '');
        $security_check = site_security_evaluate_order($pdo, [
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $customer_phone,
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
                'message' => $security_check['message'],
                'error' => 'blocked_security_rule'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $customer_phone = ($security_check['context']['phone'] ?? '') !== '' ? $security_check['context']['phone'] : $customer_phone;
        
        // تحديث معلومات السلة
        $update_stmt = $pdo->prepare("UPDATE tbl_abandoned_cart SET 
            customer_name = ?,
            customer_phone = ?,
            quantity = ?,
            selected_size = ?,
            selected_color = ?,
            total_price = ?,
            wilaya = ?,
            commune = ?,
            address = ?,
            delivery_type = ?,
            last_updated = ?
            WHERE cart_id = ? AND product_id = ?");
            
        $update_stmt->execute([
            $_POST['customer_name'],
            $customer_phone,
            $_POST['quantity'],
            $_POST['selected_size'],
            $_POST['selected_color'],
            $_POST['total_price'],
            $_POST['wilaya'],
            $_POST['commune'],
            $_POST['address'],
            $_POST['delivery_type'],
            $timestamp,
            $cart_id,
            $product_id
        ]);
        
        // إذا لم يتم تحديث أي صف، قم بإضافة سلة جديدة
        if ($update_stmt->rowCount() === 0) {
            $insert_stmt = $pdo->prepare("INSERT INTO tbl_abandoned_cart (
                cart_id, product_id, customer_name, customer_phone, quantity,
                selected_size, selected_color, total_price, wilaya, commune,
                address, delivery_type, created_at, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $insert_stmt->execute([
                $cart_id,
                $product_id,
                $_POST['customer_name'],
                $customer_phone,
                $_POST['quantity'],
                $_POST['selected_size'],
                $_POST['selected_color'],
                $_POST['total_price'],
                $_POST['wilaya'],
                $_POST['commune'],
                $_POST['address'],
                $_POST['delivery_type'],
                $timestamp,
                $timestamp
            ]);
        }
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("خطأ في تحديث السلة المهجورة: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
} 
