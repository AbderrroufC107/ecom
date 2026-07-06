<?php require_once('header.php'); ?>
<?php
use Ecom\Cache\CacheService;
$dashboard_cache = new CacheService($pdo);

if (!function_exists('admin_store_format_amount')) {
    function admin_store_format_amount($amount)
    {
        return number_format((float) $amount, 0, '.', ' ') . ' دج';
    }
}

if (!function_exists('admin_store_percent')) {
    function admin_store_percent($value, $total)
    {
        if ((float) $total <= 0) {
            return 0;
        }

        return (int) round(((float) $value / (float) $total) * 100);
    }
}

if (!function_exists('admin_store_trim')) {
    function admin_store_trim($text, $length = 54)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '-';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $length, '...', 'UTF-8');
        }

        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}

$admin_name = trim((string) ($_SESSION['user']['full_name'] ?? 'المدير'));
$refresh_time = date('d/m/Y - H:i');
$admin_auto_refresh = admin_build_live_refresh_config($pdo, 'store', ['interval_ms' => 25000]);

$store_summary = array(
    'total_products' => 0,
    'active_products' => 0,
    'featured_products' => 0,
    'inactive_products' => 0,
    'out_of_stock_products' => 0,
    'low_stock_products' => 0,
    'healthy_stock_products' => 0,
    'total_units' => 0,
    'inventory_cost' => 0,
    'inventory_value' => 0
);

$summary_raw = $dashboard_cache->getOrCompute('dashboard_product_summary', function() use ($pdo) {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_products,
            SUM(CASE WHEN p_is_active = 1 THEN 1 ELSE 0 END) AS active_products,
            SUM(CASE WHEN p_is_featured = 1 THEN 1 ELSE 0 END) AS featured_products,
            SUM(CASE WHEN p_is_active = 0 THEN 1 ELSE 0 END) AS inactive_products,
            SUM(CASE WHEN p_qty <= 0 THEN 1 ELSE 0 END) AS out_of_stock_products,
            SUM(CASE WHEN p_qty BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock_products,
            SUM(CASE WHEN p_qty > 5 THEN 1 ELSE 0 END) AS healthy_stock_products,
            COALESCE(SUM(p_qty), 0) AS total_units,
            COALESCE(SUM(COALESCE(purchase_price, 0) * p_qty), 0) AS inventory_cost,
            COALESCE(SUM(CAST(NULLIF(p_current_price, '') AS DECIMAL(12,2)) * p_qty), 0) AS inventory_value
        FROM tbl_product
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}, 300);
if ($summary_raw) {
    $store_summary = array_merge($store_summary, $summary_raw);
}

$eco_stats = $dashboard_cache->getOrCompute('dashboard_eco_stats', function() use ($pdo) {
    $stats = ['delivered' => 0, 'returned' => 0, 'transit' => 0, 'total_today' => 0];
    $stmt = $pdo->query("SELECT ecotrack_remote_status, order_date FROM tbl_order WHERE ecotrack_tracking IS NOT NULL AND ecotrack_tracking != ''");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = mb_strtolower((string)$row['ecotrack_remote_status'], 'UTF-8');
        if (strpos($status, 'livré') !== false || strpos($status, 'delivered') !== false) {
            $stats['delivered']++;
        } elseif (strpos($status, 'retour') !== false || strpos($status, 'returned') !== false) {
            $stats['returned']++;
        } else {
            $stats['transit']++;
        }
        if (date('Y-m-d', strtotime($row['order_date'])) == date('Y-m-d')) {
            $stats['total_today']++;
        }
    }
    return $stats;
}, 600);

$dashboard_cache->getOrCompute('dashboard_order_counts', function() use ($pdo, &$pending_orders, &$confirmed_orders, &$completed_today, &$incomplete_orders) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Pending'");
    $pending_orders = (int) $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Confirmed'");
    $confirmed_orders = (int) $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tbl_order WHERE order_status = 'Completed' AND DATE(order_date) = CURDATE()");
    $completed_today = (float) $stmt->fetchColumn();
    $incomplete_orders = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM incomplete_orders");
        $incomplete_orders = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $incomplete_orders = 0;
    }
    return true;
}, 300);

$low_stock_items = $dashboard_cache->getOrCompute('dashboard_low_stock', function() use ($pdo) {
    $stmt = $pdo->query("SELECT p_id, p_name, p_qty, p_current_price, p_is_active FROM tbl_product WHERE p_qty BETWEEN 0 AND 5 ORDER BY p_qty ASC, p_id DESC LIMIT 7");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, 300);

$sales_lookup = $dashboard_cache->getOrCompute('dashboard_sales_7day', function() use ($pdo) {
    $lookup = [];
    $stmt = $pdo->query("SELECT DATE(order_date) AS day_key, COUNT(*) AS order_count, COALESCE(SUM(CASE WHEN order_status IN ('Completed', 'Confirmed') THEN total_price ELSE 0 END), 0) AS revenue_total FROM tbl_order WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(order_date) ORDER BY DATE(order_date) ASC");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lookup[(string) $row['day_key']] = ['orders' => (int) ($row['order_count'] ?? 0), 'revenue' => (float) ($row['revenue_total'] ?? 0)];
    }
    return $lookup;
}, 600);

$sales_days = array();
for ($i = 6; $i >= 0; $i--) {
    $day_key = date('Y-m-d', strtotime("-$i days"));
    $sales_days[] = array(
        'key' => $day_key,
        'label' => date('d/m', strtotime($day_key)),
        'orders' => $sales_lookup[$day_key]['orders'] ?? 0,
        'revenue' => $sales_lookup[$day_key]['revenue'] ?? 0
    );
}

$total_products = (int) $store_summary['total_products'];
$out_of_stock_products = (int) $store_summary['out_of_stock_products'];
$low_stock_products = (int) $store_summary['low_stock_products'];
$healthy_stock_products = (int) $store_summary['healthy_stock_products'];
$inventory_value = (float) $store_summary['inventory_value'];
$inventory_cost = (float) $store_summary['inventory_cost'];
$inventory_margin = $inventory_value - $inventory_cost;
$attention_total = $out_of_stock_products + $low_stock_products + $pending_orders + $incomplete_orders;

$active_products = (int) $store_summary['active_products'];

?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* 
=====================================================
  PREMIUM DASHBOARD OVERHAUL - NEXT-GEN UI
===================================================== 
*/
.content-wrapper { background: #f4f7fe !important; }

.premium-dashboard {
    font-family: 'Cairo', 'Outfit', sans-serif;
    padding: 24px;
    direction: rtl;
    text-align: right;
    color: #1b2559;
}

/* Typography & Utilities */
.premium-dashboard h1, .premium-dashboard h2, .premium-dashboard h3, .premium-dashboard h4 { font-family: 'Cairo', sans-serif; }
.premium-text-muted { color: #a3aed1; font-size: 13px; font-weight: 600; }
.premium-title { font-size: 32px; font-weight: 800; color: #1b2559; letter-spacing: -0.5px; margin: 0 0 8px 0; }
.premium-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px 0; }

/* Flex & Grid */
.d-flex { display: flex; }
.align-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-3 { gap: 16px; }
.gap-4 { gap: 24px; }
.grid { display: grid; }
.grid-cols-4 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
.grid-cols-2-1 { grid-template-columns: 2fr 1fr; gap: 24px; }
.grid-cols-2 { grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 991px) { .grid-cols-2-1, .grid-cols-2 { grid-template-columns: 1fr; } }

/* Premium Cards */
.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 18px 40px rgba(112, 144, 176, 0.12);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
}
.glass-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 45px rgba(112, 144, 176, 0.18);
}

/* Stat Widgets */
.stat-widget { display: flex; align-items: center; justify-content: space-between; }
.stat-widget-icon {
    width: 64px; height: 64px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
}
.icon-purple { background: #f4f0ff; color: #4318ff; }
.icon-green { background: #e6f9ed; color: #05cd99; }
.icon-orange { background: #fff3e0; color: #ff9800; }
.icon-red { background: #ffebee; color: #f44336; }
.icon-blue { background: #e8f4ff; color: #2196f3; }

.stat-widget-info h4 { font-size: 14px; color: #a3aed1; font-weight: 600; margin: 0 0 4px; }
.stat-widget-info h2 { font-size: 28px; color: #1b2559; font-weight: 800; margin: 0; }

/* Gradient Hero Banner */
.hero-banner {
    background: linear-gradient(135deg, #4318ff 0%, #868cff 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: white;
    margin-bottom: 24px;
    box-shadow: 0 20px 40px rgba(67, 24, 255, 0.25);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}
.hero-banner::after {
    content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%; pointer-events: none;
}
.hero-content h2 { font-size: 32px; font-weight: 800; margin: 0 0 8px; color: white; }
.hero-content p { font-size: 16px; color: rgba(255,255,255,0.85); margin: 0; max-width: 600px; line-height: 1.6; }
.hero-stats { display: flex; gap: 24px; }
.hero-stat-item { background: rgba(255,255,255,0.15); padding: 16px 24px; border-radius: 16px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); }
.hero-stat-item span { display: block; font-size: 13px; color: rgba(255,255,255,0.8); margin-bottom: 4px; }
.hero-stat-item strong { display: block; font-size: 24px; font-weight: 800; }

/* Modern Lists */
.modern-list { list-style: none; padding: 0; margin: 0; }
.modern-list li {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 0; border-bottom: 1px dashed #e2e8f0;
}
.modern-list li:last-child { border-bottom: none; padding-bottom: 0; }
.list-item-left { display: flex; align-items: center; gap: 16px; }
.item-img-placeholder { width: 48px; height: 48px; border-radius: 12px; background: #f4f7fe; display: flex; align-items: center; justify-content: center; color: #4318ff; font-size: 20px; font-weight: 700; }
.item-details h4 { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #1b2559; }
.item-details p { margin: 0; font-size: 13px; color: #a3aed1; }

/* Buttons */
.btn-premium {
    background: #4318ff; color: white; border: none; padding: 12px 24px;
    border-radius: 12px; font-weight: 700; font-size: 14px;
    transition: all 0.3s; cursor: pointer; text-decoration: none; display: inline-block;
}
.btn-premium:hover { background: #3311db; box-shadow: 0 10px 20px rgba(67, 24, 255, 0.2); color: white; transform: translateY(-2px); }
.btn-light-premium {
    background: #f4f7fe; color: #4318ff; border: none; padding: 10px 20px;
    border-radius: 10px; font-weight: 700; font-size: 13px; text-decoration: none;
    transition: all 0.2s;
}
.btn-light-premium:hover { background: #e0e5f2; color: #4318ff; }

/* Badges */
.modern-badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; }
.badge-danger { background: #ffebee; color: #f44336; }
.badge-warning { background: #fff8e1; color: #ffb300; }

/* Chart Container */
.chart-container { position: relative; height: 300px; width: 100%; margin-top: 20px; }

/* Animated Pulse */
.pulse-ring { position: relative; display: inline-block; }
.pulse-ring::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    border-radius: 50%; border: 3px solid #ff9800;
    animation: pulsate 1.5s infinite ease-out; opacity: 0;
}
@keyframes pulsate { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(1.5); opacity: 0; } }

/* Section Title */
.section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-head h3 { font-size: 20px; font-weight: 800; color: #1b2559; margin: 0; }

</style>

<div class="premium-dashboard">
    <h1 class="premium-title">المتجر ومركز الشحن</h1>
    <p class="premium-subtitle">منحنيات المبيعات وإحصائيات التسليم (ECOTRACK) اليومية.</p>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-content">
            <h2>مرحباً بك، <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?>! 👋</h2>
            <p>أداء المتجر اليوم يبدو رائعاً. لديك <?php echo $attention_total; ?> عناصر تتطلب انتباهك، و <?php echo $confirmed_orders; ?> طلبات مؤكدة جاهزة للشحن.</p>
            <div class="d-flex gap-3" style="margin-top: 24px;">
                <a href="order.php" class="btn-premium"><i class="fa fa-shopping-cart"></i> معالجة الطلبات</a>
                <a href="order-statistics.php#ordersTable" class="btn-premium" style="background: rgba(255,255,255,0.2);"><i class="fa fa-truck"></i> تتبع شحنات اليوم</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat-item">
                <span>إيراد اليوم المكتمل</span>
                <strong><?php echo admin_store_format_amount($completed_today); ?></strong>
            </div>
            <div class="hero-stat-item">
                <span>تم إرسالها اليوم لـ Ecotrack</span>
                <strong><?php echo $eco_stats['total_today']; ?> شحنة</strong>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-4 gap-4" style="margin-bottom: 24px;">
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>شحنات تم تسليمها (حي)</h4>
                <h2><?php echo $eco_stats['delivered']; ?></h2>
            </div>
            <div class="stat-widget-icon icon-green">
                <i class="fa fa-check-circle"></i>
            </div>
        </div>
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>شحنات راجعة</h4>
                <h2><?php echo $eco_stats['returned']; ?></h2>
            </div>
            <div class="stat-widget-icon icon-red">
                <i class="fa fa-undo"></i>
            </div>
        </div>
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>شحنات قيد التوصيل <span class="pulse-ring"></span></h4>
                <h2><?php echo $eco_stats['transit']; ?></h2>
            </div>
            <div class="stat-widget-icon icon-orange">
                <i class="fa fa-truck"></i>
            </div>
        </div>
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>إجمالي المنتجات النشطة</h4>
                <h2><?php echo $active_products; ?></h2>
            </div>
            <div class="stat-widget-icon icon-purple">
                <i class="fa fa-cubes"></i>
            </div>
        </div>
    </div>

    <!-- Charts Row 1: Dual Curves -->
    <div class="grid grid-cols-2 gap-4" style="margin-bottom: 24px;">
        <!-- Sales Revenue Curve -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-line-chart" style="color: #4318ff; margin-left: 8px;"></i> منحنى الإيرادات (7 أيام)</h3>
            </div>
            <div class="chart-container" style="height: 240px;">
                <canvas id="revenueCurveChart"></canvas>
            </div>
        </div>

        <!-- Orders Count Curve -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-area-chart" style="color: #05cd99; margin-left: 8px;"></i> منحنى حجم الطلبات (7 أيام)</h3>
            </div>
            <div class="chart-container" style="height: 240px;">
                <canvas id="ordersCurveChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 & Alerts -->
    <div class="grid grid-cols-2-1">
        <!-- Ecotrack Distribution Doughnut -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-pie-chart" style="color: #ff9800; margin-left: 8px;"></i> توزيع شحنات التوصيل</h3>
                <a href="order-statistics.php#ordersTable" class="btn-light-premium">سجل الشحنات</a>
            </div>
            <div class="chart-container" style="height: 280px; display: flex; align-items: center; justify-content: center;">
                <canvas id="shippingDoughnutChart"></canvas>
            </div>
        </div>

        <!-- Attention Needed List -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-bell" style="color: #f44336; margin-left: 8px;"></i> مخزون حرج</h3>
            </div>
            <?php if (!empty($low_stock_items)): ?>
                <ul class="modern-list">
                    <?php foreach ($low_stock_items as $product): ?>
                    <li>
                        <div class="list-item-left">
                            <div class="item-img-placeholder">
                                <i class="fa fa-box"></i>
                            </div>
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars(admin_store_trim($product['p_name'], 30), ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p><?php echo admin_store_format_amount($product['p_current_price']); ?></p>
                            </div>
                        </div>
                        <div>
                            <span class="modern-badge <?php echo (int)$product['p_qty'] <= 0 ? 'badge-danger' : 'badge-warning'; ?>">
                                <?php echo (int)$product['p_qty'] <= 0 ? 'نفد' : 'متبقي ' . (int)$product['p_qty']; ?>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: #a3aed1;">
                    <i class="fa fa-check-circle" style="font-size: 48px; color: #05cd99; margin-bottom: 16px; opacity: 0.8;"></i>
                    <h4 style="color: #1b2559;">المخزون ممتاز</h4>
                    <p>لا توجد منتجات تعاني من نقص.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Data
const chartLabels = <?php echo json_encode(array_column($sales_days, 'label')); ?>;
const revenueData = <?php echo json_encode(array_column($sales_days, 'revenue')); ?>;
const ordersData = <?php echo json_encode(array_column($sales_days, 'orders')); ?>;
const shipData = [<?php echo $eco_stats['delivered']; ?>, <?php echo $eco_stats['returned']; ?>, <?php echo $eco_stats['transit']; ?>];

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Revenue Curve Chart
    const ctxRev = document.getElementById('revenueCurveChart').getContext('2d');
    let gradRev = ctxRev.createLinearGradient(0, 0, 0, 300);
    gradRev.addColorStop(0, 'rgba(67, 24, 255, 0.4)');   
    gradRev.addColorStop(1, 'rgba(67, 24, 255, 0.0)');

    new Chart(ctxRev, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'الإيرادات (دج)',
                data: revenueData,
                backgroundColor: gradRev,
                borderColor: '#4318ff',
                borderWidth: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#4318ff',
                pointBorderWidth: 3,
                pointRadius: 4,
                fill: true,
                tension: 0.4 // Curve
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f4f7fe', drawBorder: false }, ticks: { font: { family: 'Outfit' }, color: '#a3aed1' } },
                x: { grid: { display: false }, ticks: { font: { family: 'Cairo' }, color: '#a3aed1' } }
            }
        }
    });

    // 2. Orders Curve Chart
    const ctxOrd = document.getElementById('ordersCurveChart').getContext('2d');
    let gradOrd = ctxOrd.createLinearGradient(0, 0, 0, 300);
    gradOrd.addColorStop(0, 'rgba(5, 205, 153, 0.4)');   
    gradOrd.addColorStop(1, 'rgba(5, 205, 153, 0.0)');

    new Chart(ctxOrd, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'عدد الطلبات',
                data: ordersData,
                backgroundColor: gradOrd,
                borderColor: '#05cd99',
                borderWidth: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#05cd99',
                pointBorderWidth: 3,
                pointRadius: 4,
                fill: true,
                tension: 0.4 // Curve
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f4f7fe', drawBorder: false }, ticks: { font: { family: 'Outfit' }, color: '#a3aed1' } },
                x: { grid: { display: false }, ticks: { font: { family: 'Cairo' }, color: '#a3aed1' } }
            }
        }
    });

    // 3. Shipping Doughnut Chart
    const ctxShip = document.getElementById('shippingDoughnutChart').getContext('2d');
    new Chart(ctxShip, {
        type: 'doughnut',
        data: {
            // هذه الإحصائيات خاصة بحالة الشحنة داخل ECOTRACK وليست حالة الطلب داخل المتجر.
            labels: ['مكتملة ومُسلمة', 'شحنات راجعة (Retour)', 'جاري التوصيل'],
            datasets: [{
                data: shipData,
                backgroundColor: ['#05cd99', '#f44336', '#ff9800'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '75%',
            plugins: {
                legend: { position: 'right', labels: { font: { family: 'Cairo', size: 14 }, padding: 20, color: '#1b2559' } }
            }
        }
    });

});
</script>

<?php require_once('footer.php'); ?>
