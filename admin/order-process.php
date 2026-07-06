<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");
require_once(dirname(__DIR__) . '/inc/site-security.php');

site_security_ensure_tables($pdo);
site_security_ensure_order_columns($pdo);

if(isset($_POST['form1'])) {
    try {
        // التحقق من البيانات المطلوبة
        if(empty($_POST['customer_name']) || 
           empty($_POST['customer_phone']) || 
           empty($_POST['customer_address'])) {
            throw new Exception("الرجاء إدخال جميع البيانات المطلوبة");
        }

        // إنشاء رقم الطلب
        $security_check = site_security_reject_if_needed($pdo, [
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'address' => $_POST['customer_address'] ?? '',
            'wilaya' => $_POST['wilaya'] ?? '',
            'commune' => $_POST['commune'] ?? '',
            'device_id' => $_POST['device_id'] ?? null
        ]);
        $_POST['customer_phone'] = $security_check['context']['phone'] ?? site_security_normalize_phone($_POST['customer_phone'] ?? '');

        $final_total = 0;
        if (!empty($_SESSION['cart_product'])) {
            foreach ($_SESSION['cart_product'] as $key => $value) {
                $final_total += ($value['unit_price'] ?? 0) * ($value['quantity'] ?? 1);
            }
        }

        $manager_id = (int)($_SESSION['user']['id'] ?? 0);

        $statement = $dbRepo->prepare("INSERT INTO tbl_order (
            product_name,
            quantity,
            unit_price,
            total_price,
            customer_name,
            customer_phone,
            address,
            wilaya,
            commune,
            order_date,
            order_status,
            manager_id
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        
        $first_product = $_SESSION['cart_product'][0] ?? [];
        $statement->execute(array(
            $first_product['product_name'] ?? '',
            $first_product['quantity'] ?? 1,
            $first_product['unit_price'] ?? 0,
            $final_total,
            $_POST['customer_name'],
            $_POST['customer_phone'],
            $_POST['customer_address'],
            $_POST['wilaya'] ?? '',
            $_POST['commune'] ?? '',
            date('Y-m-d H:i:s'),
            'Pending',
            $manager_id > 0 ? $manager_id : null
        ));
        $db_order_id = (int) $dbRepo->lastInsertId();

        foreach($_SESSION['cart_product'] as $key => $value) {
            $statement = $dbRepo->prepare("INSERT INTO tbl_order_details (
                order_id,
                product_id,
                quantity,
                unit_price
            ) VALUES (?,?,?,?)");
            
            $statement->execute(array(
                $db_order_id,
                $value['product_id'],
                $value['quantity'],
                $value['unit_price']
            ));
        }

        // Central Order Assignment
        if (file_exists(__DIR__ . '/inc/employee_functions.php')) {
            require_once __DIR__ . '/inc/employee_functions.php';
            if (function_exists('assign_order_by_strategy') && !empty($db_order_id)) {
                assign_order_by_strategy($pdo, $db_order_id, 'order_process');
            }
        }

        // تفريغ سلة المشتريات
        unset($_SESSION['cart_product']);
        
        // رسالة نجاح
        $success_message = 'تم إرسال طلبك بنجاح. رقم طلبك هو: ' . $db_order_id;

    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
