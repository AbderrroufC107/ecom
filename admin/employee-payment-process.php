<?php
require_once('header.php');
require_once('inc/employee_functions.php');
if (file_exists('inc/telegram_bot.php')) { require_once('inc/telegram_bot.php'); }
require_once('inc/audit.php'); // Include telegram_bot.php

// Allow admin users (any role) and store owners
$_is_admin = isset($_SESSION['user']);
$_is_store_owner = isset($_SESSION['store_user']);
if (!$_is_admin && !$_is_store_owner) {
    die("Access denied");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['employee_id'])) {
    header('Location: employee-payments.php?error=Invalid Request');
    exit;
}

$employee_id = (int)$_POST['employee_id'];
$admin_id = (int)$_SESSION['user']['id'];

try {
    $pdo->beginTransaction();

    // 1. Fetch Employee Info
    $stmt_emp = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE id = ? FOR UPDATE");
    $stmt_emp->execute([$employee_id]);
    $employee = $stmt_emp->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception("الموظف غير موجود.");
    }

    $commission_rate = (float)$employee['commission_per_order'];

    // 2. Fetch Unpaid Completed Orders
    $stmt_orders = $dbRepo->prepare("
        SELECT oa.id as assignment_id, o.id as order_id, o.customer_name, o.order_date
        FROM tbl_order_assignment oa
        INNER JOIN tbl_order o ON o.id = oa.order_id
        WHERE oa.employee_id = ? AND o.order_status = 'Completed' AND oa.is_paid = 0
        FOR UPDATE
    ");
    $stmt_orders->execute([$employee_id]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    $confirmed_orders_count = count($orders);
    
    if ($confirmed_orders_count === 0) {
        throw new Exception("لا توجد طلبات مستحقة الدفع لهذا الموظف.");
    }

    $total_amount = $confirmed_orders_count * $commission_rate;

    // 3. Generate Invoice Number
    $year = date('Y');
    $stmt_inv = $dbRepo->query("SELECT COUNT(*) FROM tbl_employee_payments WHERE YEAR(payment_date) = {$year}");
    $next_inv = (int) $stmt_inv->fetchColumn() + 1;
    $invoice_number = "INV-{$year}-" . str_pad($next_inv, 6, '0', STR_PAD_LEFT);

    // 4. Generate PDF using TCPDF
    require_once(__DIR__ . '/inc/tcpdf/tcpdf.php');

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Admin System');
    $pdf->SetTitle('Invoice ' . $invoice_number);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Support Arabic (RTL)
    $pdf->setRTL(true);
    $pdf->SetFont('freeserif', '', 12);
    
    $pdf->AddPage();

    // Store Settings
    $stmt_settings = $dbRepo->query("SELECT * FROM tbl_settings WHERE id = 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $store_name = $settings['contact_email'] ?? 'متجر إلكتروني';
    
    $html = '
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #333;">فاتورة أرباح موظف</h1>
        <h3 style="color: #666;">'.$store_name.'</h3>
    </div>
    
    <table cellpadding="5" cellspacing="0" style="width: 100%; border-bottom: 2px solid #ccc; margin-bottom: 20px;">
        <tr>
            <td style="width: 50%;">
                <strong>رقم الفاتورة:</strong> '.$invoice_number.'<br>
                <strong>تاريخ الدفع:</strong> '.date('Y-m-d H:i').'<br>
                <strong>المدير الموكل:</strong> '.$_SESSION['user']['full_name'].'
            </td>
            <td style="width: 50%;">
                <strong>اسم الموظف:</strong> '.$employee['full_name'].'<br>
                <strong>البريد الإلكتروني:</strong> '.$employee['email'].'<br>
                <strong>سعر العمولة:</strong> '.number_format($commission_rate, 2).' دج
            </td>
        </tr>
    </table>
    
    <h3 style="margin-top: 30px;">تفاصيل الطلبات المؤكدة</h3>
    <table border="1" cellpadding="5" cellspacing="0" style="width: 100%; text-align: center;">
        <tr style="background-color: #f2f2f2; font-weight: bold;">
            <th style="width: 10%;">#</th>
            <th style="width: 25%;">رقم الطلب</th>
            <th style="width: 40%;">اسم الزبون</th>
            <th style="width: 25%;">تاريخ الطلب</th>
        </tr>';
        
    $count = 1;
    foreach ($orders as $o) {
        $html .= '<tr>
            <td>'.$count++.'</td>
            <td>#'.$o['order_id'].'</td>
            <td>'.$o['customer_name'].'</td>
            <td>'.date('Y-m-d', strtotime($o['order_date'])).'</td>
        </tr>';
    }
    
    $html .= '</table>
    
    <div style="margin-top: 30px; border-top: 2px solid #333; padding-top: 10px;">
        <table cellpadding="5" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 60%;"></td>
                <td style="width: 40%; text-align: left;">
                    <strong>إجمالي الطلبات المؤكدة:</strong> '.$confirmed_orders_count.'<br>
                    <strong>العمولة لكل طلب:</strong> '.number_format($commission_rate, 2).' دج<br>
                    <h3 style="color: #e11d48; margin-top: 10px;">الإجمالي المستحق: '.number_format($total_amount, 2).' دج</h3>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="text-align: center; margin-top: 50px; color: #666; font-size: 10px;">
        تم إصدار هذه الفاتورة إلكترونياً ولا تحتاج إلى توقيع فعلي.
    </div>
    ';

    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf_filename = $invoice_number . '_' . time() . '.pdf';
    $pdf_path = 'assets/invoices/' . $pdf_filename;
    $pdf_abs_path = '../' . $pdf_path;
    
    $pdf->Output(dirname(__DIR__) . '/' . $pdf_path, 'F');

    // 5. Insert Payment Record
    $stmt_pay = $dbRepo->prepare("
        INSERT INTO tbl_employee_payments 
        (invoice_number, employee_id, admin_id, confirmed_orders, commission_rate, total_amount, pdf_path, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed')
    ");
    $stmt_pay->execute([
        $invoice_number,
        $employee_id,
        $admin_id,
        $confirmed_orders_count,
        $commission_rate,
        $total_amount,
        $pdf_path
    ]);
    
    $payment_id = $dbRepo->lastInsertId();

    if (function_exists('audit_log_employee')) {
        audit_log_employee($pdo, $employee_id, 'payment_processed', null, ['payment_id' => $payment_id, 'amount' => $total_amount, 'invoice' => $invoice_number], 'admin_panel', $_SESSION['user']['id']);
    }

    if (function_exists('telegram_notify_payment')) {
        telegram_notify_payment($pdo, $employee_id, [
            'invoice_number' => $invoice_number,
            'confirmed_orders' => $confirmed_orders_count,
            'commission_rate' => $commission_rate,
            'total_amount' => $total_amount
        ]);
    }

    // 6. Mark Orders as Paid
    $assignment_ids = array_column($orders, 'assignment_id');
    $placeholders = str_repeat('?,', count($assignment_ids) - 1) . '?';
    $stmt_update_orders = $dbRepo->prepare("UPDATE tbl_order_assignment SET is_paid = 1, payment_id = ? WHERE id IN ($placeholders)");
    $update_params = array_merge([$payment_id], $assignment_ids);
    $stmt_update_orders->execute($update_params);

    $pdo->commit();
    
    header("Location: employee-payments.php?success=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: employee-payments.php?error=" . urlencode($e->getMessage()));
    exit;
}
