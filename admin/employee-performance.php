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

$current_manager_id = (int)($_SESSION['user']['id'] ?? 0);
$is_super_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin');
$effective_manager_id = $is_super_admin ? null : $current_manager_id;

// Get Date Range Filter
$filter = $_GET['filter'] ?? 'all';
$date_where = '';
$date_params = [];

if ($filter === 'today') {
    $date_where = " AND DATE(o.order_date) = CURDATE() ";
} elseif ($filter === 'yesterday') {
    $date_where = " AND DATE(o.order_date) = CURDATE() - INTERVAL 1 DAY ";
} elseif ($filter === 'week') {
    $date_where = " AND YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1) ";
} elseif ($filter === 'month') {
    $date_where = " AND MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE()) ";
} elseif ($filter === 'custom' && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $date_where = " AND DATE(o.order_date) BETWEEN ? AND ? ";
    $date_params[] = $_GET['start_date'];
    $date_params[] = $_GET['end_date'];
}

// Fetch employees and performance stats
$manager_filter = '';
if ($effective_manager_id !== null && $effective_manager_id > 0) {
    $manager_filter = " AND e.manager_id = " . (int) $effective_manager_id;
} elseif ($effective_manager_id === 0) {
    $manager_filter = " AND (e.manager_id IS NULL OR e.manager_id = 0)";
}

$sql = "
    SELECT
        e.id, e.full_name, e.email, e.is_active, e.commission_per_order,
        COUNT(oa.id) AS total_assigned,
        SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned,
        SUM(CASE WHEN o.order_status = 'Completed' AND oa.is_paid = 0 THEN 1 ELSE 0 END) AS unpaid_completed,
        MAX(CASE WHEN o.order_status = 'Completed' THEN o.order_date ELSE NULL END) AS last_completed_order
    FROM tbl_employee e
    LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
    LEFT JOIN tbl_order o ON o.id = oa.order_id $date_where
    WHERE e.is_active = 1 $manager_filter
    GROUP BY e.id, e.full_name, e.email, e.is_active, e.commission_per_order
";
$stmt = $dbRepo->prepare($sql);
$stmt->execute($date_params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch last payment dates for each employee
$payments_stmt = $dbRepo->query("SELECT employee_id, MAX(payment_date) as last_payment FROM tbl_employee_payments GROUP BY employee_id");
$payments_map = [];
while ($row = $payments_stmt->fetch(PDO::FETCH_ASSOC)) {
    $payments_map[$row['employee_id']] = $row['last_payment'];
}

// Global Stats
$total_employees = count($employees);
$total_unpaid_balance = 0;
$total_completed_orders = 0;
$highest_earner_name = '-';
$highest_earner_val = -1;

foreach ($employees as &$emp) {
    $emp['unpaid_balance'] = $emp['unpaid_completed'] * $emp['commission_per_order'];
    $emp['total_earnings'] = $emp['completed'] * $emp['commission_per_order'];
    $emp['last_payment'] = $payments_map[$emp['id']] ?? null;
    $emp['success_rate'] = $emp['total_assigned'] > 0 ? round(($emp['completed'] / $emp['total_assigned']) * 100, 1) : 0;
    
    $total_unpaid_balance += $emp['unpaid_balance'];
    $total_completed_orders += $emp['completed'];
    
    if ($emp['total_earnings'] > $highest_earner_val) {
        $highest_earner_val = $emp['total_earnings'];
        $highest_earner_name = $emp['full_name'];
    }
}
unset($emp);

// Calculate Paid This Month
$stmt_paid = $dbRepo->query("SELECT SUM(total_amount) FROM tbl_employee_payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
$total_paid_this_month = (float) $stmt_paid->fetchColumn();

// Sort employees by total earnings descending
usort($employees, function($a, $b) {
    return $b['total_earnings'] <=> $a['total_earnings'];
});

$l = [
    'perf_dashboard' => 'أداء الموظفين والأرباح',
    'total_employees' => 'إجمالي الموظفين',
    'total_unpaid' => 'الأرباح غير المدفوعة',
    'total_paid_month' => 'تم دفعه هذا الشهر',
    'highest_earner' => 'الأعلى ربحاً',
    'employee' => 'الموظف',
    'assigned' => 'مستلم',
    'completed' => 'مؤكد',
    'returned' => 'مرتجع',
    'cancelled' => 'ملغى',
    'success_rate' => 'نسبة التأكيد',
    'commission' => 'العمولة (دج)',
    'unpaid_balance' => 'رصيد مستحق',
    'total_earnings' => 'إجمالي الأرباح',
    'last_order' => 'آخر طلب مؤكد',
    'last_payment' => 'آخر دفعة'
];

?>

<section class="content-header">
    <div class="content-header-left">
        <h1><?php echo $l['perf_dashboard']; ?></h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <!-- Filter Form -->
        <div class="col-md-12" style="margin-bottom: 20px;">
            <form method="get" class="form-inline" style="background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <div class="form-group">
                    <select name="filter" class="form-control" onchange="if(this.value=='custom'){document.getElementById('custom-dates').style.display='inline-block';}else{document.getElementById('custom-dates').style.display='none';}">
                        <option value="all" <?php echo $filter=='all'?'selected':''; ?>>جميع الأوقات</option>
                        <option value="today" <?php echo $filter=='today'?'selected':''; ?>>اليوم</option>
                        <option value="yesterday" <?php echo $filter=='yesterday'?'selected':''; ?>>الأمس</option>
                        <option value="week" <?php echo $filter=='week'?'selected':''; ?>>هذا الأسبوع</option>
                        <option value="month" <?php echo $filter=='month'?'selected':''; ?>>هذا الشهر</option>
                        <option value="custom" <?php echo $filter=='custom'?'selected':''; ?>>فترة مخصصة</option>
                    </select>
                </div>
                <span id="custom-dates" style="display:<?php echo $filter=='custom'?'inline-block':'none'; ?>;">
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                </span>
                <button type="submit" class="btn btn-primary" style="margin-right:10px;">تصفية</button>
            </form>
        </div>
    </div>

    <!-- Widgets -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php echo $l['total_employees']; ?></span>
                    <span class="info-box-number"><?php echo $total_employees; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php echo $l['total_unpaid']; ?></span>
                    <span class="info-box-number"><?php echo number_format($total_unpaid_balance, 2); ?> دج</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php echo $l['total_paid_month']; ?></span>
                    <span class="info-box-number"><?php echo number_format($total_paid_this_month, 2); ?> دج</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-star"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?php echo $l['highest_earner']; ?></span>
                    <span class="info-box-number"><?php echo htmlspecialchars($highest_earner_name); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th>#</th>
                                <th><?php echo $l['employee']; ?></th>
                                <th><?php echo $l['assigned']; ?></th>
                                <th><?php echo $l['completed']; ?></th>
                                <th><?php echo $l['returned']; ?></th>
                                <th><?php echo $l['cancelled']; ?></th>
                                <th><?php echo $l['success_rate']; ?></th>
                                <th><?php echo $l['commission']; ?></th>
                                <th style="color:#e11d48;"><?php echo $l['unpaid_balance']; ?></th>
                                <th style="color:#16a34a;"><?php echo $l['total_earnings']; ?></th>
                                <th><?php echo $l['last_order']; ?></th>
                                <th><?php echo $l['last_payment']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr><td colspan="12" class="text-center">لا يوجد بيانات.</td></tr>
                            <?php else: ?>
                                <?php $i=1; foreach($employees as $emp): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                    <td><span class="label label-default"><?php echo $emp['total_assigned']; ?></span></td>
                                    <td><span class="label label-success"><?php echo $emp['completed']; ?></span></td>
                                    <td><span class="label label-warning"><?php echo $emp['returned']; ?></span></td>
                                    <td><span class="label label-danger"><?php echo $emp['cancelled']; ?></span></td>
                                    <td>
                                        <div class="progress progress-xs" style="margin-bottom:5px;">
                                            <div class="progress-bar progress-bar-success" style="width: <?php echo $emp['success_rate']; ?>%"></div>
                                        </div>
                                        <small><?php echo $emp['success_rate']; ?>%</small>
                                    </td>
                                    <td><?php echo number_format($emp['commission_per_order'], 2); ?></td>
                                    <td style="font-weight:bold; color:#e11d48;"><?php echo number_format($emp['unpaid_balance'], 2); ?></td>
                                    <td style="font-weight:bold; color:#16a34a;"><?php echo number_format($emp['total_earnings'], 2); ?></td>
                                    <td style="font-size:12px;"><?php echo $emp['last_completed_order'] ? date('Y-m-d H:i', strtotime($emp['last_completed_order'])) : '-'; ?></td>
                                    <td style="font-size:12px;"><?php echo $emp['last_payment'] ? date('Y-m-d H:i', strtotime($emp['last_payment'])) : '-'; ?></td>
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
