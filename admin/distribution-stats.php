<?php
require_once('header.php');
require_once('inc/employee_functions.php');

if (!$is_manager && !$is_admin) {
    admin_orders_redirect('index.php');
}

$current_manager_id = (int)($_SESSION['user']['id'] ?? 0);
$is_super_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin');
$effective_manager_id = $is_super_admin ? null : $current_manager_id;

$manager_filter = '';
if ($effective_manager_id !== null && $effective_manager_id > 0) {
    $manager_filter = " WHERE e.manager_id = " . (int) $effective_manager_id;
} elseif ($effective_manager_id === 0) {
    $manager_filter = " WHERE (e.manager_id IS NULL OR e.manager_id = 0)";
}

// Fetch employees
$stmt1 = $dbRepo->query("SELECT 'employee' AS type, e.id, e.full_name, e.is_active, e.assignment_weight, e.availability_status, e.max_active_orders FROM tbl_employee e {$manager_filter}");
$emp_participants = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// Fetch admins
$stmt2 = $dbRepo->query("SELECT 'user' AS type, id, full_name, 1 AS is_active, assignment_weight, availability_status, max_active_orders FROM tbl_user WHERE participate_in_assignment = 1");
$user_participants = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$participants = array_merge($emp_participants, $user_participants);

// Calculate Totals for percentages
$stmtTotal = $dbRepo->query("SELECT COUNT(*) FROM tbl_order_assignment");
$total_ever = $stmtTotal->fetchColumn() ?: 1; // prevent div by zero

$stats = [];
foreach ($participants as $p) {
    $is_user = $p['type'] === 'user';
    $col = $is_user ? 'user_id' : 'employee_id';
    $id = $p['id'];

    // Today
    $stmtT = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE $col = ? AND DATE(assigned_at) = CURDATE()");
    $stmtT->execute([$id]);
    $p['assigned_today'] = $stmtT->fetchColumn();

    // This Week
    $stmtW = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE $col = ? AND YEARWEEK(assigned_at, 1) = YEARWEEK(CURDATE(), 1)");
    $stmtW->execute([$id]);
    $p['assigned_week'] = $stmtW->fetchColumn();

    // This Month
    $stmtM = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE $col = ? AND MONTH(assigned_at) = MONTH(CURDATE()) AND YEAR(assigned_at) = YEAR(CURDATE())");
    $stmtM->execute([$id]);
    $p['assigned_month'] = $stmtM->fetchColumn();

    // Total Assigned Ever
    $stmtAll = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE $col = ?");
    $stmtAll->execute([$id]);
    $p['total_assigned'] = $stmtAll->fetchColumn();

    // Open/Active Orders
    $stmtActive = $dbRepo->prepare("
        SELECT COUNT(oa.id) FROM tbl_order_assignment oa 
        JOIN tbl_order o ON o.id = oa.order_id 
        WHERE oa.$col = ? AND oa.status = 'active' AND o.order_status NOT IN ('Delivered', 'Returned', 'Cancelled')
    ");
    $stmtActive->execute([$id]);
    $p['active_orders'] = $stmtActive->fetchColumn();

    // Completed Orders
    $stmtComp = $dbRepo->prepare("
        SELECT COUNT(oa.id) FROM tbl_order_assignment oa 
        JOIN tbl_order o ON o.id = oa.order_id 
        WHERE oa.$col = ? AND o.order_status = 'Completed'
    ");
    $stmtComp->execute([$id]);
    $p['completed_orders'] = $stmtComp->fetchColumn();

    // Saturation and Completion Rate
    $weight = max(1, (int)$p['assignment_weight']);
    $p['saturation_ratio'] = round($p['total_assigned'] / $weight, 2);
    $p['completion_rate'] = $p['total_assigned'] > 0 ? round(($p['completed_orders'] / $p['total_assigned']) * 100, 1) : 0;
    $p['distribution_percent'] = round(($p['total_assigned'] / $total_ever) * 100, 1);

    $stats[] = $p;
}

// Sort by Saturation ASC
usort($stats, function($a, $b) {
    return $a['saturation_ratio'] <=> $b['saturation_ratio'];
});

?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إحصائيات التوزيع التلقائي (WRR)</h1>
    </div>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">المشاركون في التوزيع (مدراء وموظفين)</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>الاسم</th>
                        <th>الحالة / الوزن</th>
                        <th>مفتوحة (السعة)</th>
                        <th>مستلمة (اليوم)</th>
                        <th>مستلمة (هذا الأسبوع)</th>
                        <th>مستلمة (هذا الشهر)</th>
                        <th>الإجمالي الكلي</th>
                        <th>مكتملة (نسبة الإنجاز)</th>
                        <th>معامل التشبع (Saturation)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $s): ?>
                        <tr>
                            <td>
                                <?php if ($s['type'] == 'user'): ?>
                                    <span class="label label-danger">مدير النظام</span>
                                <?php else: ?>
                                    <span class="label label-info">موظف</span>
                                <?php endif; ?>
                            </td>
                            <td><b><?= htmlspecialchars($s['full_name']) ?></b></td>
                            <td>
                                <span class="label label-<?= $s['availability_status'] === 'Available' ? 'success' : 'warning' ?>"><?= $s['availability_status'] ?></span>
                                <br><small>الوزن: <b><?= $s['assignment_weight'] ?></b></small>
                            </td>
                            <td>
                                <?php $cap_class = $s['active_orders'] >= $s['max_active_orders'] ? 'text-danger' : 'text-success'; ?>
                                <b class="<?= $cap_class ?>"><?= $s['active_orders'] ?></b> / <?= $s['max_active_orders'] ?>
                            </td>
                            <td><?= $s['assigned_today'] ?></td>
                            <td><?= $s['assigned_week'] ?></td>
                            <td><?= $s['assigned_month'] ?></td>
                            <td>
                                <?= $s['total_assigned'] ?> <br>
                                <small class="text-muted"><?= $s['distribution_percent'] ?>% من إجمالي التوزيع</small>
                            </td>
                            <td>
                                <?= $s['completed_orders'] ?> <br>
                                <span class="label label-<?= $s['completion_rate'] > 70 ? 'success' : ($s['completion_rate'] > 40 ? 'warning' : 'danger') ?>">
                                    <?= $s['completion_rate'] ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-purple"><?= $s['saturation_ratio'] ?></span>
                                <?php if($s['availability_status'] === 'Available' && $s['active_orders'] < $s['max_active_orders']): ?>
                                    <br><small class="text-success">مؤهل للاستلام</small>
                                <?php else: ?>
                                    <br><small class="text-danger">مستبعد مؤقتاً</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="alert alert-info mt-3" style="margin-top:20px;">
                <h4><i class="icon fa fa-info"></i> كيف يعمل نظام Weighted Round Robin؟</h4>
                <p>يقوم النظام آلياً بحساب (معامل التشبع = الإجمالي الكلي للطلبات / الوزن). عندما يدخل طلب جديد للنظام، سيتم تحويله مباشرة للشخص صاحب <b>أقل معامل تشبع</b> بشرط أن يكون متاحاً ولم يتجاوز السعة القصوى للطلبات المفتوحة لديه. هذا يضمن توزيعاً دقيقاً بنسبة الأوزان بشكل رياضي مستدام.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
