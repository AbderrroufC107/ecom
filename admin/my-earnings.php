<?php
require_once('header.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Employee') {
    // Only employees can view this page
    echo '<script>window.location.href="index.php";</script>';
    exit;
}

require_once('inc/employee_functions.php');

$employee_id_raw = $_SESSION['user']['id'];
$employee_id = 0;
if (strpos($employee_id_raw, 'emp_') === 0) {
    $employee_id = (int) substr($employee_id_raw, 4);
} else {
    $employee_id = (int) $employee_id_raw;
}

$emp_stmt = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE id = ?");
$emp_stmt->execute([$employee_id]);
$employee = $emp_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo "Employee not found.";
    exit;
}

$stats = employee_get_stats($pdo, $employee_id);
$commission_rate = (float)($employee['commission_per_order'] ?? 0);
$unpaid_balance = $stats['unpaid_balance'];

// Fetch payments history
$pay_stmt = $dbRepo->prepare("SELECT * FROM tbl_employee_payments WHERE employee_id = ? ORDER BY payment_date DESC");
$pay_stmt->execute([$employee_id]);
$payments = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>عمولاتي وسجل المدفوعات</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">سعر العمولة (لكل طلب)</span>
                    <span class="info-box-number"><?php echo number_format($commission_rate, 2); ?> دج</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-hourglass-half"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">الرصيد المستحق (غير مدفوع)</span>
                    <span class="info-box-number"><?php echo number_format($unpaid_balance, 2); ?> دج</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">إجمالي الطلبات المكتملة</span>
                    <span class="info-box-number"><?php echo $stats['completed']; ?> طلب</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">سجل المدفوعات السابقة</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>المبلغ المدفوع</th>
                                <th>الفاتورة (PDF)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($payments)): ?>
                            <tr>
                                <td colspan="4" class="text-center">لا يوجد مدفوعات سابقة.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($payments as $index => $pay): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($pay['payment_date'])); ?></td>
                                    <td><span class="label label-success" style="font-size:14px;"><?php echo number_format($pay['total_amount'], 2); ?> دج</span></td>
                                    <td>
                                        <?php if(!empty($pay['pdf_path'])): ?>
                                            <a href="../assets/invoices/<?php echo htmlspecialchars($pay['pdf_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fa fa-download"></i> تحميل
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
