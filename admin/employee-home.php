<?php
/**
 * employee-home.php
 * A simple, employee-scoped dashboard shown on index.php for logged-in employees
 * (instead of the full manager dashboard). Renders between header.php and footer.php.
 */
require_once __DIR__ . '/inc/employee_functions.php';

$__idRaw = (string) ($_SESSION['user']['id'] ?? '');
$empId   = strpos($__idRaw, 'emp_') === 0 ? (int) substr($__idRaw, 4) : (int) $__idRaw;
$empName = (string) ($_SESSION['user']['full_name'] ?? 'موظف');

$stats = function_exists('employee_get_stats') ? employee_get_stats($pdo, $empId) : [];
$total     = (int) ($stats['total_assigned'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);
$pending   = (int) ($stats['pending'] ?? 0);
$confirmed = (int) ($stats['confirmed'] ?? 0);
$cancelled = (int) ($stats['cancelled'] ?? 0);
$delivery  = $total > 0 ? round($completed / $total * 100, 1) : 0;
$rate      = (float) ($stats['commission_per_order'] ?? 0);
$unpaidBal = (float) ($stats['unpaid_balance'] ?? 0);

// Their pending orders that need follow-up
$pendingOrders = [];
try {
    $st = $dbRepo->prepare("
        SELECT o.id, o.customer_name, o.customer_phone, o.product_name, o.total_price,
               TIMESTAMPDIFF(HOUR, o.order_date, NOW()) hrs
        FROM tbl_order_assignment a
        INNER JOIN tbl_order o ON o.id = a.order_id
        WHERE a.employee_id = ? AND a.status = 'active' AND o.order_status = 'Pending'
        ORDER BY o.order_date ASC LIMIT 15");
    $st->execute([$empId]);
    $pendingOrders = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

$kpis = [
    ['label' => 'طلباتي المُسندة', 'value' => $total,     'icon' => 'fa-inbox',        'c1' => '#6366f1', 'c2' => '#4f46e5'],
    ['label' => 'مكتملة',          'value' => $completed, 'icon' => 'fa-check-circle',  'c1' => '#10b981', 'c2' => '#059669'],
    ['label' => 'معلّقة',          'value' => $pending,   'icon' => 'fa-clock-o',       'c1' => '#f59e0b', 'c2' => '#d97706'],
    ['label' => 'معدل التوصيل',    'value' => $delivery . '%', 'icon' => 'fa-line-chart', 'c1' => '#0ea5e9', 'c2' => '#0284c7'],
];
?>

<section class="content-header">
    <div class="content-header-left"><h1>👋 مرحباً، <?= htmlspecialchars($empName) ?></h1></div>
</section>

<section class="content">

    <!-- KPI cards (self-styled so the theme's CSS reset can't blank them) -->
    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px">
        <?php foreach ($kpis as $k): ?>
        <div style="flex:1 1 200px;min-width:190px;display:flex;align-items:center;gap:14px;
                    padding:18px 20px;border-radius:14px;color:#fff;
                    background:linear-gradient(135deg,<?= $k['c1'] ?>,<?= $k['c2'] ?>);
                    box-shadow:0 4px 14px rgba(0,0,0,.10)">
            <i class="fa <?= $k['icon'] ?>" style="font-size:34px;opacity:.9"></i>
            <div>
                <div style="font-size:26px;font-weight:800;line-height:1"><?= htmlspecialchars((string)$k['value']) ?></div>
                <div style="font-size:13px;opacity:.95;margin-top:4px"><?= htmlspecialchars($k['label']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Earnings summary -->
        <div class="col-md-4">
            <div class="box box-success">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-money"></i> عمولاتي</h3></div>
                <div class="box-body">
                    <p style="margin:0 0 8px">العمولة لكل طلب: <b><?= number_format($rate, 2) ?> دج</b></p>
                    <p style="margin:0 0 8px">الرصيد المستحق (غير مدفوع):
                        <b style="color:#059669;font-size:18px"><?= number_format($unpaidBal, 2) ?> دج</b></p>
                    <a href="my-earnings.php" class="btn btn-success btn-sm" style="margin-top:6px">
                        <i class="fa fa-list"></i> عمولاتي وسجل المدفوعات
                    </a>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-magic"></i> المساعد الذكي</h3></div>
                <div class="box-body">
                    <p style="margin:0 0 8px;color:#64748b">اسأل عن طلباتك، أداءك، أو أرباحك.</p>
                    <a href="ai-assistant.php" class="btn btn-primary btn-sm"><i class="fa fa-comments"></i> افتح المساعد</a>
                </div>
            </div>
        </div>

        <!-- Pending orders needing follow-up -->
        <div class="col-md-8">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-clock-o"></i> طلبات معلّقة تحتاج متابعة (<?= count($pendingOrders) ?>)</h3>
                </div>
                <div class="box-body p-0">
                    <table class="table table-hover" style="margin:0">
                        <thead><tr><th>#</th><th>العميل</th><th>الهاتف</th><th>المنتج</th><th>منذ</th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingOrders as $o): $hrs = (int)$o['hrs']; $age = $hrs >= 48 ? intdiv($hrs,24).' يوم' : $hrs.' ساعة'; ?>
                            <tr>
                                <td><?= (int)$o['id'] ?></td>
                                <td><?= htmlspecialchars($o['customer_name'] ?? '') ?></td>
                                <td dir="ltr"><?= htmlspecialchars($o['customer_phone'] ?? '') ?></td>
                                <td><small><?= htmlspecialchars(mb_substr((string)$o['product_name'], 0, 30)) ?></small></td>
                                <td><span class="label label-<?= $hrs >= 48 ? 'danger' : 'warning' ?>"><?= $age ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pendingOrders)): ?>
                            <tr><td colspan="5" class="text-center text-muted" style="padding:20px">
                                <i class="fa fa-check-circle" style="color:#10b981"></i> لا توجد طلبات معلّقة — عمل رائع!
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</section>
