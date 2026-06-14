<?php require_once('header.php'); ?>
<?php
require_once('inc/employee_functions.php');
require_once('inc/telegram_bot.php');
require_once('inc/performance_functions.php');

performance_ensure_tables($pdo);

$employee_id = (int) ($_GET['id'] ?? 0);
$employee = employee_get_by_id($pdo, $employee_id);
if (!$employee) {
    header('location: employees.php');
    exit;
}

$kpis = performance_get_kpis($pdo, $employee_id);
$monthly_stats = performance_get_monthly_stats($pdo, $employee_id, 6);
$commission_summary = performance_get_commission_summary($pdo, $employee_id);
$assigned_orders = employee_get_assigned_orders($pdo, $employee_id);
$telegram_status = telegram_get_status_html($employee);

$stmt = $pdo->prepare("
    SELECT a.*, o.order_status, o.total_price, o.product_name, o.customer_name, o.customer_phone
    FROM tbl_telegram_action_log a
    LEFT JOIN tbl_order o ON o.id = a.order_id
    WHERE a.employee_id = ?
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt->execute([$employee_id]);
$recent_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="content-header">
    <h1><?php echo htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <ol class="breadcrumb">
        <li><a href="employees.php">الموظفين</a></li>
        <li class="active">ملف الموظف</li>
    </ol>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-trophy"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">مجموع النقاط</span>
                    <span class="info-box-number"><?php echo (int) $kpis['score']; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-tasks"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">إجمالي الطلبات</span>
                    <span class="info-box-number"><?php echo (int) $kpis['total_assigned']; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">نسبة التوصيل</span>
                    <span class="info-box-number"><?php echo $kpis['delivery_success_rate']; ?>%</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-times-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">نسبة الإلغاء</span>
                    <span class="info-box-number"><?php echo $kpis['cancellation_rate']; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">ملخص الأداء</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <td>البريد الإلكتروني</td>
                            <td><?php echo htmlspecialchars($employee['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <td>حالة التلغرام</td>
                            <td><?php echo $telegram_status; ?></td>
                        </tr>
                        <tr>
                            <td>متوسط وقت المعالجة</td>
                            <td><?php echo $kpis['avg_processing_hours']; ?> ساعة</td>
                        </tr>
                        <tr>
                            <td>مكتمل</td>
                            <td><span class="label label-success"><?php echo (int) $kpis['completed']; ?></span></td>
                        </tr>
                        <tr>
                            <td>مؤكد</td>
                            <td><span class="label label-info"><?php echo (int) $kpis['confirmed']; ?></span></td>
                        </tr>
                        <tr>
                            <td>معلق</td>
                            <td><span class="label label-warning"><?php echo (int) $kpis['pending']; ?></span></td>
                        </tr>
                        <tr>
                            <td>ملغي</td>
                            <td><span class="label label-danger"><?php echo (int) $kpis['cancelled']; ?></span></td>
                        </tr>
                        <tr>
                            <td>مرتجع</td>
                            <td><span class="label label-danger"><?php echo (int) $kpis['returned']; ?></span></td>
                        </tr>
                        <tr>
                            <td>نسبة الإرجاع</td>
                            <td><?php echo $kpis['return_rate']; ?>%</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">ملخص العمولة</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <td>أرباح اليوم</td>
                            <td><?php echo number_format($commission_summary['today'], 2); ?> دج</td>
                        </tr>
                        <tr>
                            <td>أرباح هذا الأسبوع</td>
                            <td><?php echo number_format($commission_summary['this_week'], 2); ?> دج</td>
                        </tr>
                        <tr>
                            <td>أرباح هذا الشهر</td>
                            <td><?php echo number_format($commission_summary['this_month'], 2); ?> دج</td>
                        </tr>
                        <tr>
                            <td>إجمالي الغير مدفوع</td>
                            <td><?php echo number_format($commission_summary['total_unpaid'], 2); ?> دج</td>
                        </tr>
                        <tr>
                            <td>إجمالي المدفوع</td>
                            <td><?php echo number_format($commission_summary['total_paid'], 2); ?> دج</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">الإحصائيات الشهرية (آخر 6 أشهر)</h3>
                </div>
                <div class="box-body">
                    <canvas id="monthlyChart" style="height:250px;"></canvas>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">آخر الإجراءات</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الإجراء</th>
                                <th>الطلب</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_actions)): ?>
                                <tr><td colspan="3" class="text-center">لا توجد إجراءات</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_actions as $act): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($act['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($act['action_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $act['order_id'] > 0 ? '#'.$act['order_id'] : '--'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">الطلبات المسندة</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th>المنتج</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>تاريخ الطلب</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assigned_orders)): ?>
                                <tr><td colspan="7" class="text-center">لا توجد طلبات</td></tr>
                            <?php else: ?>
                                <?php foreach ($assigned_orders as $o): ?>
                                    <tr>
                                        <td><?php echo (int) $o['id']; ?></td>
                                        <td><?php echo htmlspecialchars($o['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($o['product_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) ($o['total_price'] ?? 0), 0); ?> دج</td>
                                        <td><?php echo htmlspecialchars($o['order_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($o['order_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><a href="order-details.php?id=<?php echo (int) $o['id']; ?>" class="btn btn-default btn-xs"><i class="fa fa-eye"></i></a></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    var ctx = document.getElementById('monthlyChart').getContext('2d');
    var monthlyData = <?php echo json_encode($monthly_stats); ?>;
    var labels = monthlyData.map(function(d) { return d.month; });
    var completed = monthlyData.map(function(d) { return d.completed; });
    var cancelled = monthlyData.map(function(d) { return d.cancelled; });
    var returned = monthlyData.map(function(d) { return d.returned; });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'مكتمل', data: completed, backgroundColor: 'rgba(60,118,61,0.7)', borderColor: '#3c763d', borderWidth: 1 },
                { label: 'ملغي', data: cancelled, backgroundColor: 'rgba(169,68,66,0.7)', borderColor: '#a94442', borderWidth: 1 },
                { label: 'مرتجع', data: returned, backgroundColor: 'rgba(138,109,59,0.7)', borderColor: '#8a6d3b', borderWidth: 1 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
})();
</script>
<?php require_once('footer.php'); ?>
