<?php require_once('header.php'); ?>
<?php
require_once('inc/employee_functions.php');
if (file_exists('inc/telegram_bot.php')) { require_once('inc/telegram_bot.php'); }
require_once('inc/performance_functions.php');
require_once('inc/ai_functions.php');

ai_ensure_tables($pdo);
performance_ensure_tables($pdo);

$action = trim((string) ($_GET['action'] ?? ''));

$report_sent = false;
if ($action === 'run_all') {
    ai_analyze_cancellations($pdo);
    ai_analyze_product_risk($pdo);
    ai_analyze_employee_performance($pdo);
    ai_analyze_wilayas($pdo);
    ai_analyze_offers($pdo);
    ai_analyze_response_time($pdo);
    ai_forecast_revenue($pdo);
    ai_predict_returns($pdo);
    $report_sent = true;
}

if ($action === 'send_report') {
    $result = ai_send_morning_report($pdo);
}

// Load latest cached reports
$cancellations = ai_get_last_report($pdo, 'cancellation_analysis');
$product_risk = ai_get_last_report($pdo, 'product_risk');
$employee_perf = ai_get_last_report($pdo, 'employee_performance');
$wilaya = ai_get_last_report($pdo, 'wilaya_analysis');
$offers = ai_get_last_report($pdo, 'offer_analysis');
$response_time = ai_get_last_report($pdo, 'response_time');
$forecast = ai_get_last_report($pdo, 'revenue_forecast');
$returns = ai_get_last_report($pdo, 'return_prediction');

$c_data = $cancellations ? json_decode($cancellations['report_data'], true) : null;
$p_data = $product_risk ? json_decode($product_risk['report_data'], true) : null;
$e_data = $employee_perf ? json_decode($employee_perf['report_data'], true) : null;
$w_data = $wilaya ? json_decode($wilaya['report_data'], true) : null;
$o_data = $offers ? json_decode($offers['report_data'], true) : null;
$r_data = $response_time ? json_decode($response_time['report_data'], true) : null;
$f_data = $forecast ? json_decode($forecast['report_data'], true) : null;
$ret_data = $returns ? json_decode($returns['report_data'], true) : null;

function ai_section_header(string $title, string $icon = 'fa-line-chart'): void
{
    echo '<h3 style="margin:32px 0 16px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;"><i class="fa ' . $icon . '" style="margin-right:8px;color:#0f766e;"></i> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';
}
?>
<section class="content-header">
    <h1>الذكاء الاصطناعي وتحليل الأعمال</h1>
</section>

<section class="content">
    <div class="row" style="margin-bottom:20px;">
        <div class="col-xs-12">
            <a href="?action=run_all" class="btn btn-primary"><i class="fa fa-cogs"></i> تشغيل جميع التحليلات</a>
            <a href="?action=send_report" class="btn btn-success"><i class="fa fa-telegram"></i> إرسال التقرير الصباحي</a>
            <?php if ($report_sent): ?>
                <span style="color:#15803d;font-weight:700;margin-right:12px;">✓ تم تحديث جميع التحليلات</span>
            <?php endif; ?>
            <?php if (isset($result)): ?>
                <span style="color:#15803d;font-weight:700;margin-right:12px;">✓ <?php echo $result['success'] ? 'تم إرسال التقرير' : 'فشل الإرسال: ' . htmlspecialchars($result['error'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ANALYSIS 1: Cancellations -->
    <?php ai_section_header('تحليل أسباب الإلغاء', 'fa-times-circle'); ?>
    <div class="row">
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header"><h3 class="box-title">أهم أسباب الإلغاء</h3></div>
                <div class="box-body">
                    <?php if ($c_data && !empty($c_data['by_reason'])): ?>
                        <table class="table table-striped">
                            <thead><tr><th>السبب</th><th>العدد</th><th>النسبة</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($c_data['by_reason'], 0, 8) as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $r['count']; ?></td>
                                        <td>
                                            <div class="progress" style="margin:0;height:16px;">
                                                <div class="progress-bar progress-bar-danger" style="width:<?php echo $r['pct']; ?>%;">
                                                    <?php echo $r['pct']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-muted">إجمالي الإلغاءات: <?php echo (int) ($c_data['total_cancellations'] ?? 0); ?></p>
                    <?php else: ?>
                        <p class="text-muted">لا توجد بيانات كافية.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header"><h3 class="box-title">الإلغاء حسب المنتج</h3></div>
                <div class="box-body">
                    <?php if ($c_data && !empty($c_data['by_product'])): ?>
                        <table class="table table-striped">
                            <thead><tr><th>المنتج</th><th>الإلغاءات</th><th>النسبة</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($c_data['by_product'], 0, 8) as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $r['count']; ?></td>
                                        <td><?php echo $r['pct']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">لا توجد بيانات كافية.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ANALYSIS 2: Product Risk -->
    <?php ai_section_header('مخاطر المنتجات', 'fa-shield'); ?>
    <div class="row">
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner"><h3><?php echo (int) ($p_data['summary']['low'] ?? 0); ?></h3><p>منخفضة المخاطر</p></div>
                <div class="icon"><i class="fa fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner"><h3><?php echo (int) ($p_data['summary']['medium'] ?? 0); ?></h3><p>متوسطة المخاطر</p></div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner"><h3><?php echo (int) ($p_data['summary']['high'] ?? 0); ?></h3><p>عالية المخاطر</p></div>
                <div class="icon"><i class="fa fa-times-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped">
                <thead>
                    <tr><th>المنتج</th><th>الإجمالي</th><th>مكتمل</th><th>ملغي</th><th>مرتجع</th><th>توصيل</th><th>مخاطر</th></tr>
                </thead>
                <tbody>
                    <?php if ($p_data && !empty($p_data['products'])): ?>
                        <?php foreach (array_slice($p_data['products'], 0, 20) as $p): ?>
                            <?php $badge = $p['risk_level'] === 'high' ? 'bg-red' : ($p['risk_level'] === 'medium' ? 'bg-yellow' : 'bg-green'); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $p['total']; ?></td>
                                <td><?php echo (int) $p['completed']; ?></td>
                                <td><?php echo (int) $p['cancelled']; ?></td>
                                <td><?php echo (int) $p['returned']; ?></td>
                                <td><?php echo $p['delivery_rate']; ?>%</td>
                                <td><span class="label <?php echo $badge; ?>"><?php echo $p['risk_level'] === 'high' ? 'عالي' : ($p['risk_level'] === 'medium' ? 'متوسط' : 'منخفض'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">لا توجد بيانات كافية.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ANALYSIS 3: Employee Performance -->
    <?php ai_section_header('تحليل أداء الموظفين', 'fa-users'); ?>
    <div class="row">
        <div class="col-md-8">
            <div class="box">
                <div class="box-header"><h3 class="box-title">مقارنة الأداء</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>الموظف</th><th>الإجمالي</th><th>مكتمل</th><th>ملغي</th><th>توصيل</th><th>إلغاء</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($e_data && !empty($e_data['employees'])): ?>
                                <?php foreach ($e_data['employees'] as $e): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($e['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $e['total']; ?></td>
                                        <td><?php echo (int) $e['completed']; ?></td>
                                        <td><?php echo (int) $e['cancelled']; ?></td>
                                        <td><?php echo $e['delivery_rate']; ?>%</td>
                                        <td><?php echo $e['cancel_rate']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">لا توجد بيانات كافية.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-success">
                <div class="box-header"><h3 class="box-title">التوصيات</h3></div>
                <div class="box-body">
                    <?php if ($e_data && !empty($e_data['recommendations'])): ?>
                        <ul>
                            <?php foreach ($e_data['recommendations'] as $rec): ?>
                                <li style="margin-bottom:10px;line-height:1.8;"><?php echo htmlspecialchars($rec, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">لا توجد توصيات.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="box box-warning">
                <div class="box-header"><h3 class="box-title">متوسط الفريق</h3></div>
                <div class="box-body">
                    <?php if ($e_data): ?>
                        <p>معدل التوصيل: <strong><?php echo $e_data['averages']['delivery_rate']; ?>%</strong></p>
                        <p>معدل الإلغاء: <strong><?php echo $e_data['averages']['cancel_rate']; ?>%</strong></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ANALYSIS 4: Wilaya Analysis -->
    <?php ai_section_header('تحليل الولايات', 'fa-map-marker'); ?>
    <div class="row">
        <div class="col-md-4">
            <div class="box box-success">
                <div class="box-header"><h3 class="box-title">أفضل 5 ولايات توصيل</h3></div>
                <div class="box-body">
                    <?php if ($w_data && !empty($w_data['best'])): ?>
                        <table class="table">
                            <thead><tr><th>الولاية</th><th>التوصيل</th><th>الإلغاء</th></tr></thead>
                            <tbody>
                                <?php foreach ($w_data['best'] as $w): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($w['wilaya'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="label label-success"><?php echo $w['delivery_rate']; ?>%</span></td>
                                        <td><span class="label label-danger"><?php echo $w['cancel_rate']; ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">لا توجد بيانات كافية.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-danger">
                <div class="box-header"><h3 class="box-title">أسوأ 5 ولايات توصيل</h3></div>
                <div class="box-body">
                    <?php if ($w_data && !empty($w_data['worst'])): ?>
                        <table class="table">
                            <thead><tr><th>الولاية</th><th>التوصيل</th><th>الإلغاء</th></tr></thead>
                            <tbody>
                                <?php foreach ($w_data['worst'] as $w): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($w['wilaya'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="label label-success"><?php echo $w['delivery_rate']; ?>%</span></td>
                                        <td><span class="label label-danger"><?php echo $w['cancel_rate']; ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">لا توجد بيانات كافية.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-warning">
                <div class="box-header"><h3 class="box-title">أعلى نسبة إرجاع</h3></div>
                <div class="box-body">
                    <?php if ($w_data && !empty($w_data['highest_return'])): ?>
                        <table class="table">
                            <thead><tr><th>الولاية</th><th>الإرجاع</th><th>الإجمالي</th></tr></thead>
                            <tbody>
                                <?php foreach ($w_data['highest_return'] as $w): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($w['wilaya'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="label label-warning"><?php echo $w['return_rate']; ?>%</span></td>
                                        <td><?php echo (int) $w['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">لا توجد بيانات كافية.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ANALYSIS 5: Offer Analysis -->
    <?php ai_section_header('تحليل العروض', 'fa-gift'); ?>
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped">
                <thead>
                    <tr><th>المنتج/العرض</th><th>الإجمالي</th><th>مكتمل</th><th>نسبة التحويل</th><th>التوصيل</th><th>الإيراد</th><th>متوسط الإيراد</th></tr>
                </thead>
                <tbody>
                    <?php if ($o_data && !empty($o_data['offers'])): ?>
                        <?php foreach (array_slice($o_data['offers'], 0, 20) as $o): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($o['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $o['total']; ?></td>
                                <td><?php echo (int) $o['completed']; ?></td>
                                <td><span class="label label-primary"><?php echo $o['conversion_rate']; ?>%</span></td>
                                <td><span class="label label-success"><?php echo $o['delivery_rate']; ?>%</span></td>
                                <td><?php echo number_format((float) ($o['revenue'] ?? 0), 0); ?> دج</td>
                                <td><?php echo number_format((float) ($o['avg_revenue_per_completed'] ?? 0), 0); ?> دج</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">لا توجد بيانات كافية.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ANALYSIS 6: Response Time -->
    <?php ai_section_header('زمن الاستجابة', 'fa-clock-o'); ?>
    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3><?php echo $r_data ? $r_data['average_all'] : '--'; ?> ساعة</h3>
                    <p>متوسط زمن الاستجابة</p>
                </div>
                <div class="icon"><i class="fa fa-hourglass-half"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?php echo $r_data && $r_data['fastest'] ? htmlspecialchars($r_data['fastest']['full_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></h3>
                    <p>أسرع موظف استجابة (<?php echo $r_data && $r_data['fastest'] ? $r_data['fastest']['avg_response_hours'] . ' س' : ''; ?>)</p>
                </div>
                <div class="icon"><i class="fa fa-rocket"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3><?php echo count($r_data['slow_employees'] ?? []); ?></h3>
                    <p>موظفون بطيئون (&gt;24 ساعة)</p>
                </div>
                <div class="icon"><i class="fa fa-clock-o"></i></div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped">
                <thead><tr><th>الموظف</th><th>متوسط وقت الاستجابة</th><th>عدد التأكيدات</th></tr></thead>
                <tbody>
                    <?php if ($r_data && !empty($r_data['employees'])): ?>
                        <?php foreach ($r_data['employees'] as $e): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($e['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo $e['avg_response_hours']; ?> ساعة</td>
                                <td><?php echo (int) $e['confirmed_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center">لا توجد بيانات كافية.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ANALYSIS 7: Revenue Forecast -->
    <?php ai_section_header('توقع الإيرادات', 'fa-line-chart'); ?>
    <div class="row">
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner"><h3><?php echo number_format((float) ($f_data['forecast']['7_days'] ?? 0), 0); ?> دج</h3><p>متوقع 7 أيام</p></div>
                <div class="icon"><i class="fa fa-calendar"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner"><h3><?php echo number_format((float) ($f_data['forecast']['30_days'] ?? 0), 0); ?> دج</h3><p>متوقع 30 يوم</p></div>
                <div class="icon"><i class="fa fa-calendar-check-o"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner"><h3><?php echo number_format((float) ($f_data['forecast']['90_days'] ?? 0), 0); ?> دج</h3><p>متوقع 90 يوم</p></div>
                <div class="icon"><i class="fa fa-calendar-plus-o"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-purple">
                <div class="inner">
                    <h3><?php echo $f_data ? ($f_data['trend'] === 'up' ? 'صاعد ↑' : ($f_data['trend'] === 'down' ? 'هابط ↓' : 'مستقر →')) : '--'; ?></h3>
                    <p>اتجاه الإيرادات</p>
                </div>
                <div class="icon"><i class="fa fa-trend"></i></div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-header"><h3 class="box-title">الإيرادات الشهرية (آخر 12 شهر)</h3></div>
        <div class="box-body">
            <canvas id="revenueChart" style="height:250px;"></canvas>
        </div>
    </div>

    <!-- ANALYSIS 8: Return Prediction -->
    <?php ai_section_header('توقع الإرجاع', 'fa-retweet'); ?>
    <div class="row">
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner"><h3><?php echo $ret_data ? $ret_data['overall_return_rate'] . '%' : '--'; ?></h3><p>معدل الإرجاع العام</p></div>
                <div class="icon"><i class="fa fa-percent"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="small-box bg-orange">
                <div class="inner"><h3><?php echo count($ret_data['high_risk'] ?? []); ?></h3><p>مجموعات عالية الخطورة</p></div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped">
                <thead>
                    <tr><th>المنتج</th><th>الولاية</th><th>الإجمالي</th><th>مرتجع</th><th>نسبة الإرجاع</th><th>مستوى الخطورة</th></tr>
                </thead>
                <tbody>
                    <?php if ($ret_data && !empty($ret_data['predictions'])): ?>
                        <?php foreach (array_slice($ret_data['predictions'], 0, 30) as $p): ?>
                            <?php $badge = $p['risk'] === 'high' ? 'bg-red' : ($p['risk'] === 'medium' ? 'bg-yellow' : 'bg-green'); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($p['wilaya'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $p['total']; ?></td>
                                <td><?php echo (int) $p['returned']; ?></td>
                                <td><?php echo $p['return_rate']; ?>%</td>
                                <td><span class="label <?php echo $badge; ?>"><?php echo $p['risk'] === 'high' ? 'عالي' : ($p['risk'] === 'medium' ? 'متوسط' : 'منخفض'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">لا توجد بيانات كافية.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    var hist = <?php echo $f_data ? json_encode($f_data['historical']) : '[]'; ?>;
    if (hist.length > 0) {
        var ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: hist.map(function(d) { return d.month; }),
                datasets: [{
                    label: 'الإيراد (دج)',
                    data: hist.map(function(d) { return d.revenue; }),
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return v.toLocaleString() + ' دج'; } }
                    }
                }
            }
        });
    }
})();
</script>
<?php require_once('footer.php'); ?>
