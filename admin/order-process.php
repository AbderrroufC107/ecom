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

        $order_id = time();
        
        // حفظ بيانات الطلب في قاعدة البيانات
        $statement = $pdo->prepare("INSERT INTO tbl_order (
            order_id,
            customer_name,
            customer_phone,
            customer_address,
            total_amount,
            order_date,
            status
        ) VALUES (?,?,?,?,?,?,?)");
        
        $statement->execute(array(
            $order_id,
            $_POST['customer_name'],
            $_POST['customer_phone'],
            $_POST['customer_address'],
            $final_total,
            date('Y-m-d H:i:s'),
            'جديد'
        ));

        // حفظ تفاصيل المنتجات
        foreach($_SESSION['cart_product'] as $key => $value) {
            $statement = $pdo->prepare("INSERT INTO tbl_order_details (
                order_id,
                product_id,
                quantity,
                unit_price
            ) VALUES (?,?,?,?)");
            
            $statement->execute(array(
                $order_id,
                $value['product_id'],
                $value['quantity'],
                $value['unit_price']
            ));
        }

        // تفريغ سلة المشتريات
        unset($_SESSION['cart_product']);
        
        // رسالة نجاح
        $success_message = 'تم إرسال طلبك بنجاح. رقم طلبك هو: ' . $order_id;

    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?> 
