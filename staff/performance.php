<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];
$page_title = 'أدائي';

$kpis = performance_get_kpis($pdo, $employee_id);
$monthly_stats = performance_get_monthly_stats($pdo, $employee_id, 6);

$ranking = performance_get_ranking($pdo, null);
$rank_position = 0;
$total_employees = count($ranking);
foreach ($ranking as $i => $r) {
    if ((int) $r['id'] === $employee_id) {
        $rank_position = $i + 1;
        break;
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_telegram_action_log WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$total_actions = (int) $stmt->fetchColumn();
?>

<div class="row g-3">
    <div class="col-6 col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">نسبة التوصيل</div>
            <div class="staff-card-value" style="color:var(--success);"><?php echo $kpis['delivery_success_rate']; ?>%</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">نسبة الإلغاء</div>
            <div class="staff-card-value" style="color:var(--danger);"><?php echo $kpis['cancellation_rate']; ?>%</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">نسبة الإرجاع</div>
            <div class="staff-card-value" style="color:var(--warning);"><?php echo $kpis['return_rate']; ?>%</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">متوسط وقت المعالجة</div>
            <div class="staff-card-value" style="font-size:22px;"><?php echo $kpis['avg_processing_hours']; ?> ساعة</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">الترتيب العام</div>
            <div class="staff-card-value" style="color:var(--accent);">#<?php echo $rank_position; ?></div>
            <div class="staff-card-label">من أصل <?php echo $total_employees; ?> موظف</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">مجموع النقاط</div>
            <div class="staff-card-value"><?php echo (int) $kpis['score']; ?></div>
        </div>
    </div>
</div>

<div class="staff-card">
    <div class="staff-card-title">ملخص الأداء</div>
    <table class="table staff-table">
        <tr><td style="width:160px;">إجمالي الطلبات المسندة</td><td><strong><?php echo (int) $kpis['total_assigned']; ?></strong></td></tr>
        <tr><td>مكتمل</td><td><strong style="color:var(--success);"><?php echo (int) $kpis['completed']; ?></strong></td></tr>
        <tr><td>مؤكد</td><td><strong style="color:var(--accent);"><?php echo (int) $kpis['confirmed']; ?></strong></td></tr>
        <tr><td>معلق</td><td><strong style="color:var(--warning);"><?php echo (int) $kpis['pending']; ?></strong></td></tr>
        <tr><td>ملغي</td><td><strong style="color:var(--danger);"><?php echo (int) $kpis['cancelled']; ?></strong></td></tr>
        <tr><td>مرتجع</td><td><strong style="color:var(--warning);"><?php echo (int) $kpis['returned']; ?></strong></td></tr>
        <tr><td>إجمالي الإجراءات</td><td><strong><?php echo $total_actions; ?></strong></td></tr>
    </table>
</div>

<div class="staff-card">
    <div class="staff-card-title">الإحصائيات الشهرية (آخر 6 أشهر)</div>
    <canvas id="perfChart" style="height:250px;max-width:100%;"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    var data = <?php echo json_encode($monthly_stats); ?>;
    if (data.length === 0) return;
    var ctx = document.getElementById('perfChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(function(d) { return d.month; }),
            datasets: [
                { label: 'مكتمل', data: data.map(function(d) { return d.completed; }), backgroundColor: 'rgba(16,185,129,0.7)', borderColor: '#10b981', borderWidth: 1 },
                { label: 'ملغي', data: data.map(function(d) { return d.cancelled; }), backgroundColor: 'rgba(239,68,68,0.7)', borderColor: '#ef4444', borderWidth: 1 },
                { label: 'مرتجع', data: data.map(function(d) { return d.returned; }), backgroundColor: 'rgba(245,158,11,0.7)', borderColor: '#f59e0b', borderWidth: 1 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { font: { size: 12 } } } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
