<?php require_once('header.php'); ?>

<?php
$store_id = $current_store_id;
$store = store_get($pdo, $store_id);

if (!$store) {
    echo '<div class="alert alert-danger">المتجر غير موجود</div>';
    require_once('footer.php');
    exit;
}

$store_user = $_SESSION['store_user'] ?? null;

// Stats
$stats = store_get_stats($pdo, $store_id);
$plan_limits = store_get_plan_limits($store['plan_type'] ?? 'starter');
$subscription = store_get_subscription($pdo, $store_id);
$theme = store_get_theme($pdo, $store_id);

// Employee limit check
$emp_check = store_check_employee_limit($pdo, $store_id);

// Recent orders (scoped)
$stmt = $dbRepo->prepare("
    SELECT id, order_no, total_price, order_status, order_date, payment_method
    FROM tbl_order
    WHERE store_id = ?
    ORDER BY id DESC
    LIMIT 10
");
$stmt->execute([$store_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products (scoped)
$stmt = $dbRepo->prepare("
    SELECT p_id, p_name, p_qty, p_current_price
    FROM tbl_product
    WHERE store_id = ? AND p_qty BETWEEN 0 AND 5
    ORDER BY p_qty ASC
    LIMIT 5
");
$stmt->execute([$store_id]);
$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent recovery tasks (scoped)
$recovery_tasks = [];
try {
    $stmt = $dbRepo->prepare("
        SELECT rt.*, o.order_no
        FROM tbl_recovery_tasks rt
        LEFT JOIN tbl_order o ON o.id = rt.order_id AND o.store_id = ?
        WHERE rt.store_id = ?
        ORDER BY rt.id DESC
        LIMIT 10
    ");
    $stmt->execute([$store_id, $store_id]);
    $recovery_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recovery_tasks = [];
}

// Sales last 7 days (scoped)
$stmt = $dbRepo->prepare("
    SELECT
        DATE(order_date) AS day_key,
        COUNT(*) AS order_count,
        COALESCE(SUM(CASE WHEN order_status IN ('Completed', 'Confirmed') THEN total_price ELSE 0 END), 0) AS revenue
    FROM tbl_order
    WHERE store_id = ? AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(order_date)
    ORDER BY DATE(order_date) ASC
");
$stmt->execute([$store_id]);
$sales_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sales_lookup = [];
foreach ($sales_raw as $row) {
    $sales_lookup[(string)$row['day_key']] = $row;
}

$sales_days = [];
for ($i = 6; $i >= 0; $i--) {
    $day_key = date('Y-m-d', strtotime("-$i days"));
    $sales_days[] = [
        'key' => $day_key,
        'label' => date('d/m', strtotime($day_key)),
        'orders' => (int)($sales_lookup[$day_key]['order_count'] ?? 0),
        'revenue' => (float)($sales_lookup[$day_key]['revenue'] ?? 0),
    ];
}

function sd_format_amount($amount) { global $dbRepo;
    global $dbRepo;

    return number_format((float)$amount, 0, '.', ' ') . ' دج';
}
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.store-dash {
    font-family: 'Cairo', 'Outfit', sans-serif;
    padding: 24px;
    direction: rtl;
    text-align: right;
    color: #1b2559;
}
.sd-card {
    background: rgba(255,255,255,0.95);
    border: 1px solid rgba(226,232,240,0.92);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 18px 40px rgba(112,144,176,0.12);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.sd-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 22px 45px rgba(112,144,176,0.18);
}
.sd-grid {
    display: grid;
    gap: 24px;
}
.sd-grid-4 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.sd-grid-2 { grid-template-columns: 1fr 1fr; }
.sd-grid-3 { grid-template-columns: 2fr 1fr; }
@media (max-width: 991px) {
    .sd-grid-2, .sd-grid-3 { grid-template-columns: 1fr; }
}
.sd-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.sd-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.sd-stat { display: flex; align-items: center; justify-content: space-between; }
.sd-stat-info h4 { font-size: 14px; color: #a3aed1; font-weight: 600; margin: 0 0 4px; }
.sd-stat-info h2 { font-size: 26px; font-weight: 800; margin: 0; }
.sd-stat-icon {
    width: 56px; height: 56px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
}
.sd-icon-green { background: #e6f9ed; color: #05cd99; }
.sd-icon-purple { background: #f4f0ff; color: #4318ff; }
.sd-icon-orange { background: #fff3e0; color: #ff9800; }
.sd-icon-red { background: #ffebee; color: #f44336; }
.sd-icon-blue { background: #e8f4ff; color: #2196f3; }
.sd-badge { padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
.sd-badge-green { background: #e6f9ed; color: #05cd99; }
.sd-badge-red { background: #ffebee; color: #f44336; }
.sd-badge-warning { background: #fff8e1; color: #ffb300; }
.sd-plan-badge {
    display: inline-block; padding: 8px 20px; border-radius: 12px;
    font-weight: 800; font-size: 14px;
    background: linear-gradient(135deg, #4318ff, #868cff);
    color: white;
}
.sd-progress {
    height: 8px; border-radius: 4px; background: #f4f7fe; margin: 8px 0;
}
.sd-progress-bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #05cd99, #4318ff); }
.sd-table { width: 100%; border-collapse: collapse; }
.sd-table th { text-align: right; padding: 12px 8px; font-size: 13px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.sd-table td { padding: 12px 8px; font-size: 14px; border-bottom: 1px solid #f4f7fe; }
.sd-table tr:hover td { background: #f8faff; }
.sd-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.sd-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.sd-hero {
    background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
    border-radius: 24px; padding: 32px 40px; color: white; margin-bottom: 24px;
    display: flex; justify-content: space-between; align-items: center;
    box-shadow: 0 20px 40px rgba(15,118,110,0.25);
}
.sd-hero h2 { font-size: 28px; font-weight: 800; margin: 0 0 8px; color: white; }
.sd-hero p { font-size: 15px; color: rgba(255,255,255,0.85); margin: 0; }
.sd-hero-info { text-align: left; }
.sd-hero-info span { display: block; font-size: 13px; color: rgba(255,255,255,0.8); }
.sd-hero-info strong { display: block; font-size: 20px; font-weight: 800; }
.sd-btn {
    display: inline-block; padding: 10px 20px; border-radius: 12px;
    font-weight: 700; font-size: 13px; text-decoration: none; transition: all 0.2s;
    border: none; cursor: pointer;
}
.sd-btn-primary { background: #4318ff; color: white; }
.sd-btn-primary:hover { background: #3311db; color: white; text-decoration: none; }
.sd-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.sd-btn-outline:hover { background: #4318ff; color: white; text-decoration: none; }
</style>

<div class="store-dash">
    <h1 class="sd-title">لوحة المتجر</h1>
    <p class="sd-subtitle"><?php echo htmlspecialchars($store['store_name'] ?? 'المتجر', ENT_QUOTES, 'UTF-8'); ?> — نظرة عامة على أداء متجرك</p>

    <!-- Hero Banner -->
    <div class="sd-hero">
        <div>
            <h2>مرحباً، <?php echo htmlspecialchars($store_user['name'] ?? $store['owner_name'] ?? 'المدير', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p>باقة <strong><?php echo htmlspecialchars($plan_limits['label_ar'] ?? $store['plan_type'], ENT_QUOTES, 'UTF-8'); ?></strong> —
               لديك <?php echo $stats['total_orders']; ?> طلب و <?php echo $stats['total_employees']; ?> موظف و <?php echo $stats['total_products']; ?> منتج.</p>
        </div>
        <div class="sd-hero-info">
            <span>الطلبات المعلقة</span>
            <strong><?php echo $stats['pending_orders']; ?></strong>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="sd-grid sd-grid-4" style="margin-bottom: 24px;">
        <div class="sd-card sd-stat">
            <div class="sd-stat-info">
                <h4>إجمالي الطلبات</h4>
                <h2><?php echo $stats['total_orders']; ?></h2>
            </div>
            <div class="sd-stat-icon sd-icon-purple"><i class="fa fa-shopping-cart"></i></div>
        </div>
        <div class="sd-card sd-stat">
            <div class="sd-stat-info">
                <h4>الموظفين</h4>
                <h2><?php echo $stats['total_employees']; ?> / <?php echo $plan_limits['max_employees'] == 999999 ? '∞' : $plan_limits['max_employees']; ?></h2>
            </div>
            <div class="sd-stat-icon sd-icon-green"><i class="fa fa-users"></i></div>
        </div>
        <div class="sd-card sd-stat">
            <div class="sd-stat-info">
                <h4>المنتجات</h4>
                <h2><?php echo $stats['total_products']; ?></h2>
            </div>
            <div class="sd-stat-icon sd-icon-orange"><i class="fa fa-cubes"></i></div>
        </div>
        <div class="sd-card sd-stat">
            <div class="sd-stat-info">
                <h4>إجمالي الإيرادات</h4>
                <h2><?php echo sd_format_amount($stats['total_revenue']); ?></h2>
            </div>
            <div class="sd-stat-icon sd-icon-blue"><i class="fa fa-line-chart"></i></div>
        </div>
    </div>

    <!-- Plan & Limits -->
    <div class="sd-grid sd-grid-3" style="margin-bottom: 24px;">
        <div class="sd-card">
            <div class="sd-section-head">
                <h3>الاشتراك والحدود</h3>
                <span class="sd-plan-badge"><?php echo htmlspecialchars($plan_limits['label_ar'] ?? $store['plan_type'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <table class="sd-table">
                <tr>
                    <td>الموظفون المستخدمون</td>
                    <td>
                        <?php echo $emp_check['current']; ?> / <?php echo $emp_check['max'] == 999999 ? '∞' : $emp_check['max']; ?>
                        <div class="sd-progress">
                            <div class="sd-progress-bar" style="width: <?php echo $emp_check['max'] > 0 ? min(100, round(($emp_check['current']/$emp_check['max'])*100)) : 0; ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php if ($subscription): ?>
                <tr>
                    <td>تاريخ البدء</td>
                    <td><?php echo date('Y-m-d', strtotime($subscription['starts_at'] ?? $subscription['created_at'])); ?></td>
                </tr>
                <tr>
                    <td>تاريخ الانتهاء</td>
                    <td><?php echo $subscription['expires_at'] ? date('Y-m-d', strtotime($subscription['expires_at'])) : 'غير محدد'; ?></td>
                </tr>
                <tr>
                    <td>الحالة</td>
                    <td><span class="sd-badge sd-badge-green"><?php echo htmlspecialchars($subscription['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="sd-card">
            <div class="sd-section-head">
                <h3>الميزات المتاحة</h3>
            </div>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php
                $feature_labels = [
                    'basic_reports' => 'التقارير الأساسية',
                    'telegram_notifications' => 'إشعارات التليجرام',
                    'ai_insights' => 'ذكاء الأعمال (AI)',
                    'advanced_reports' => 'التقارير المتقدمة',
                    'recovery_engine' => 'محرك الاسترداد',
                    'api_access' => 'API',
                    'custom_domain' => 'نطاق مخصص',
                    'priority_support' => 'دعم ذو أولوية',
                    'white_label' => 'علامة بيضاء',
                ];
                foreach ($plan_limits['features'] as $feat):
                    $label = $feature_labels[$feat] ?? $feat;
                ?>
                <li style="padding: 8px 0; border-bottom: 1px dashed #f4f7fe; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle" style="color: #05cd99;"></i> <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Recent Orders & Low Stock -->
    <div class="sd-grid sd-grid-2" style="margin-bottom: 24px;">
        <div class="sd-card">
            <div class="sd-section-head">
                <h3>آخر الطلبات</h3>
                <a href="order.php" class="sd-btn sd-btn-primary">عرض الكل</a>
            </div>
            <table class="sd-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #a3aed1; padding: 24px;">لا توجد طلبات بعد</td></tr>
                    <?php else: ?>
                    <?php foreach ($recent_orders as $o): ?>
                    <tr>
                        <td><a href="order.php?edit=<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                        <td><?php echo sd_format_amount($o['total_price']); ?></td>
                        <td><span class="sd-badge <?php echo $o['order_status'] === 'Completed' ? 'sd-badge-green' : ($o['order_status'] === 'Cancelled' ? 'sd-badge-red' : 'sd-badge-warning'); ?>"><?php echo htmlspecialchars($o['order_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo date('Y-m-d', strtotime($o['order_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sd-card">
            <div class="sd-section-head">
                <h3>مخزون منخفض</h3>
                <a href="product.php" class="sd-btn sd-btn-outline">إدارة المنتجات</a>
            </div>
            <table class="sd-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>الكمية</th>
                        <th>السعر</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($low_stock)): ?>
                    <tr><td colspan="3" style="text-align: center; color: #a3aed1; padding: 24px;">جميع المنتجات متوفرة بكميات جيدة</td></tr>
                    <?php else: ?>
                    <?php foreach ($low_stock as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(mb_strimwidth($p['p_name'], 0, 30, '...', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="sd-badge <?php echo (int)$p['p_qty'] <= 0 ? 'sd-badge-red' : 'sd-badge-warning'; ?>"><?php echo (int)$p['p_qty']; ?></span></td>
                        <td><?php echo sd_format_amount($p['p_current_price']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recovery Tasks & Sales Chart -->
    <div class="sd-grid sd-grid-2" style="margin-bottom: 24px;">
        <div class="sd-card">
            <div class="sd-section-head">
                <h3>مهام الاسترداد</h3>
                <a href="order.php" class="sd-btn sd-btn-outline">عرض الطلبات</a>
            </div>
            <table class="sd-table">
                <thead>
                    <tr>
                        <th>الطلب</th>
                        <th>النوع</th>
                        <th>الحالة</th>
                        <th>الموعد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recovery_tasks)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #a3aed1; padding: 24px;">لا توجد مهام استرداد نشطة</td></tr>
                    <?php else: ?>
                    <?php foreach ($recovery_tasks as $rt): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rt['order_no'] ?? '#'.$rt['order_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($rt['task_type'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="sd-badge sd-badge-warning"><?php echo htmlspecialchars($rt['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo $rt['scheduled_at'] ? date('Y-m-d', strtotime($rt['scheduled_at'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sd-card">
            <div class="sd-section-head">
                <h3>المبيعات (7 أيام)</h3>
            </div>
            <div style="position: relative; height: 240px; width: 100%;">
                <canvas id="sdSalesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Audit Trail -->
    <div class="sd-card">
        <div class="sd-section-head">
            <h3>نشاط التدقيق (آخر 24 ساعة)</h3>
            <a href="audit-log.php" class="sd-btn sd-btn-outline">سجل التدقيق</a>
        </div>
        <p style="color: #a3aed1; font-size: 14px;"><?php echo $stats['audit_24h']; ?> حدث خلال الـ 24 ساعة الماضية</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var labels = <?php echo json_encode(array_column($sales_days, 'label')); ?>;
    var revenues = <?php echo json_encode(array_column($sales_days, 'revenue')); ?>;
    var orders = <?php echo json_encode(array_column($sales_days, 'orders')); ?>;

    var ctx = document.getElementById('sdSalesChart').getContext('2d');
    var grad = ctx.createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, 'rgba(15, 118, 110, 0.35)');
    grad.addColorStop(1, 'rgba(15, 118, 110, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'الإيرادات (دج)',
                    data: revenues,
                    backgroundColor: grad,
                    borderColor: '#0f766e',
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0f766e',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y',
                },
                {
                    label: 'الطلبات',
                    data: orders,
                    backgroundColor: 'rgba(67, 24, 255, 0.1)',
                    borderColor: '#4318ff',
                    borderWidth: 2,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4318ff',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { font: { family: 'Cairo' }, color: '#1b2559' } } },
            scales: {
                y: { beginAtZero: true, position: 'right', grid: { color: '#f4f7fe' }, ticks: { font: { family: 'Outfit' }, color: '#a3aed1' } },
                y1: { beginAtZero: true, position: 'left', grid: { display: false }, ticks: { font: { family: 'Outfit' }, color: '#a3aed1' } },
                x: { grid: { display: false }, ticks: { font: { family: 'Cairo' }, color: '#a3aed1' } }
            }
        }
    });
});
</script>

<?php require_once('footer.php'); ?>
