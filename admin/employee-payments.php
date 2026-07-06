<?php
require_once('header.php');
require_once('inc/employee_functions.php');

// Allow admin users (any role) and store owners
$_is_admin = isset($_SESSION['user']);
$_is_store_owner = isset($_SESSION['store_user']);
if (!$_is_admin && !$_is_store_owner) {
    die("Access denied");
}

employee_ensure_tables($pdo);

// Generate an invoice preview number
$year = date('Y');
$stmt_inv = $dbRepo->query("SELECT COUNT(*) FROM tbl_employee_payments WHERE YEAR(payment_date) = {$year}");
$next_inv = (int) $stmt_inv->fetchColumn() + 1;
$invoice_preview = "INV-{$year}-" . str_pad($next_inv, 6, '0', STR_PAD_LEFT);

// Fetch employees with unpaid confirmed orders
$sql = "
    SELECT
        e.id, e.full_name, e.commission_per_order,
        SUM(CASE WHEN o.order_status = 'Completed' AND oa.is_paid = 0 THEN 1 ELSE 0 END) AS unpaid_completed
    FROM tbl_employee e
    LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
    LEFT JOIN tbl_order o ON o.id = oa.order_id
    WHERE e.is_active = 1
    GROUP BY e.id, e.full_name, e.commission_per_order
    HAVING unpaid_completed > 0
";
$pending_stmt = $dbRepo->query($sql);
$pending_employees = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment history
$history_stmt = $dbRepo->query("
    SELECT p.*, e.full_name, a.full_name as admin_name
    FROM tbl_employee_payments p
    LEFT JOIN tbl_employee e ON p.employee_id = e.id
    LEFT JOIN tbl_user a ON p.admin_id = a.id
    ORDER BY p.id DESC
");
$payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

$l = [
    'payments_dashboard' => 'إدارة المدفوعات',
    'pending_payments' => 'المدفوعات المستحقة',
    'payment_history' => 'سجل المدفوعات',
    'employee' => 'الموظف',
    'orders_count' => 'الطلبات المؤكدة',
    'commission_rate' => 'سعر العمولة',
    'total_due' => 'الإجمالي المستحق',
    'pay_now' => 'دفع الآن',
    'invoice_no' => 'رقم الفاتورة',
    'date' => 'تاريخ الدفع',
    'admin' => 'المدير الموكل',
    'download' => 'تنزيل PDF',
    'confirm_payment' => 'تأكيد الدفع',
    'cancel' => 'إلغاء'
];

?>

<section class="content-header">
    <div class="content-header-left">
        <h1><?php echo $l['payments_dashboard']; ?></h1>
    </div>
</section>

<section class="content">
    <?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">تم تنفيذ الدفع وإنشاء الفاتورة بنجاح.</div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <!-- Pending Payments -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title" style="color:#e11d48;"><i class="fa fa-clock-o"></i> <?php echo $l['pending_payments']; ?></h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $l['employee']; ?></th>
                                <th><?php echo $l['orders_count']; ?></th>
                                <th><?php echo $l['commission_rate']; ?></th>
                                <th style="color:#e11d48; font-size:16px;"><?php echo $l['total_due']; ?></th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_employees)): ?>
                                <tr><td colspan="5" class="text-center">لا توجد مدفوعات مستحقة.</td></tr>
                            <?php else: ?>
                                <?php foreach($pending_employees as $emp): 
                                    $total_due = $emp['unpaid_completed'] * $emp['commission_per_order'];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                    <td><span class="label label-success" style="font-size:14px;"><?php echo $emp['unpaid_completed']; ?></span></td>
                                    <td><?php echo number_format($emp['commission_per_order'], 2); ?> دج</td>
                                    <td style="font-weight:bold; color:#e11d48; font-size:16px;"><?php echo number_format($total_due, 2); ?> دج</td>
                                    <td>
                                        <button class="btn btn-primary" onclick="openPaymentModal(<?php echo $emp['id']; ?>, '<?php echo addslashes($emp['full_name']); ?>', <?php echo $emp['unpaid_completed']; ?>, <?php echo $emp['commission_per_order']; ?>, <?php echo $total_due; ?>)"><i class="fa fa-money"></i> <?php echo $l['pay_now']; ?></button>
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

    <!-- Payment History -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title" style="color:#16a34a;"><i class="fa fa-history"></i> <?php echo $l['payment_history']; ?></h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th><?php echo $l['invoice_no']; ?></th>
                                <th><?php echo $l['employee']; ?></th>
                                <th><?php echo $l['date']; ?></th>
                                <th><?php echo $l['orders_count']; ?></th>
                                <th style="color:#16a34a;"><?php echo $l['total_due']; ?></th>
                                <th><?php echo $l['admin']; ?></th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payment_history)): ?>
                                <tr><td colspan="7" class="text-center">لا توجد سجلات.</td></tr>
                            <?php else: ?>
                                <?php foreach($payment_history as $hist): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($hist['invoice_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($hist['full_name'] ?? 'مجهول'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($hist['payment_date'])); ?></td>
                                    <td><span class="label label-default"><?php echo $hist['confirmed_orders']; ?></span></td>
                                    <td style="font-weight:bold; color:#16a34a;"><?php echo number_format($hist['total_amount'], 2); ?> دج</td>
                                    <td><?php echo htmlspecialchars($hist['admin_name'] ?? '-'); ?></td>
                                    <td>
                                        <a href="../<?php echo htmlspecialchars($hist['pdf_path']); ?>" target="_blank" class="btn btn-default btn-sm"><i class="fa fa-file-pdf-o" style="color:#ef4444;"></i> <?php echo $l['download']; ?></a>
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

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:8px;">
            <form action="employee-payment-process.php" method="POST" id="paymentForm">
                <input type="hidden" name="employee_id" id="modal_employee_id">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-header bg-primary" style="border-radius:8px 8px 0 0;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-money"></i> تأكيد الدفع</h4>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 40%;">الموظف</th>
                            <td id="modal_employee_name"></td>
                        </tr>
                        <tr>
                            <th>الطلبات المؤكدة</th>
                            <td id="modal_orders"></td>
                        </tr>
                        <tr>
                            <th>العمولة المطبقة</th>
                            <td id="modal_commission"></td>
                        </tr>
                        <tr>
                            <th>الفاتورة القادمة</th>
                            <td><strong><?php echo $invoice_preview; ?></strong></td>
                        </tr>
                        <tr>
                            <th style="font-size:18px; color:#e11d48;">الإجمالي المستحق</th>
                            <td style="font-size:18px; font-weight:bold; color:#e11d48;" id="modal_total"></td>
                        </tr>
                    </table>
                    <div class="alert alert-warning" style="margin-bottom:0;">
                        <i class="fa fa-warning"></i> عند التأكيد، سيتم تصفير رصيد الموظف وإنشاء فاتورة PDF بشكل تلقائي ولا يمكن التراجع عن هذه العملية.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $l['cancel']; ?></button>
                    <button type="submit" class="btn btn-primary" onclick="this.disabled=true; this.innerHTML='جاري المعالجة...'; document.getElementById('paymentForm').submit();"><i class="fa fa-check"></i> <?php echo $l['confirm_payment']; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPaymentModal(id, name, orders, commission, total) { global $dbRepo;
    global $dbRepo;

    document.getElementById('modal_employee_id').value = id;
    document.getElementById('modal_employee_name').innerText = name;
    document.getElementById('modal_orders').innerText = orders;
    document.getElementById('modal_commission').innerText = parseFloat(commission).toFixed(2) + ' دج';
    document.getElementById('modal_total').innerText = parseFloat(total).toFixed(2) + ' دج';
    $('#paymentModal').modal('show');
}
</script>

<?php require_once('footer.php'); ?>
