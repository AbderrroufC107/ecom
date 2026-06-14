<?php
$file = 'store.php';
$content = file_get_contents($file);

// Split the file into PHP logic and HTML view
$parts = explode('?>', $content, 2);
$php_logic = $parts[0] . '?>';

$premium_view = <<<EOF

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
@media (max-width: 991px) { .grid-cols-2-1 { grid-template-columns: 1fr; } }

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
.item-price { font-weight: 800; color: #1b2559; font-size: 15px; }

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
.badge-success { background: #e6f9ed; color: #05cd99; }

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
    <h1 class="premium-title">لوحة التحكم التنفيذية</h1>
    <p class="premium-subtitle">نظرة شاملة وعصرية لأداء متجرك، مبيعاتك، ومخزونك الحي.</p>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-content">
            <h2>مرحباً بك، <?php echo htmlspecialchars(\$admin_name, ENT_QUOTES, 'UTF-8'); ?>! 👋</h2>
            <p>أداء المتجر اليوم يبدو رائعاً. لديك <?php echo \$attention_total; ?> عناصر تتطلب انتباهك، و <?php echo \$confirmed_orders; ?> طلبات مؤكدة جاهزة للشحن.</p>
            <div class="d-flex gap-3" style="margin-top: 24px;">
                <a href="order.php" class="btn-premium"><i class="fa fa-shopping-cart"></i> معالجة الطلبات</a>
                <a href="product-add.php" class="btn-premium" style="background: rgba(255,255,255,0.2);"><i class="fa fa-plus"></i> إضافة منتج</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat-item">
                <span>إيراد اليوم</span>
                <strong><?php echo admin_store_format_amount(\$completed_today); ?></strong>
            </div>
            <div class="hero-stat-item">
                <span>الطلبات المعلقة</span>
                <strong><?php echo \$pending_orders; ?></strong>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-4 gap-4" style="margin-bottom: 24px;">
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>إجمالي المنتجات النشطة</h4>
                <h2><?php echo \$active_products; ?></h2>
            </div>
            <div class="stat-widget-icon icon-purple">
                <i class="fa fa-cubes"></i>
            </div>
        </div>
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>قيمة المخزون</h4>
                <h2><?php echo admin_store_format_amount(\$inventory_value); ?></h2>
            </div>
            <div class="stat-widget-icon icon-green">
                <i class="fa fa-money"></i>
            </div>
        </div>
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>نفاد المخزون <span class="pulse-ring"></span></h4>
                <h2><?php echo \$out_of_stock_products; ?></h2>
            </div>
            <div class="stat-widget-icon icon-red">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
        </div>
        <div class="glass-card stat-widget">
            <div class="stat-widget-info">
                <h4>طلبات غير مكتملة</h4>
                <h2><?php echo \$incomplete_orders; ?></h2>
            </div>
            <div class="stat-widget-icon icon-orange">
                <i class="fa fa-cart-arrow-down"></i>
            </div>
        </div>
    </div>

    <!-- Charts & Analytics -->
    <div class="grid grid-cols-2-1">
        <!-- Sales Chart -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-line-chart" style="color: #4318ff; margin-left: 8px;"></i> المبيعات (آخر 7 أيام)</h3>
                <a href="order-statistics.php" class="btn-light-premium">عرض التقرير المفصل</a>
            </div>
            <div class="chart-container">
                <canvas id="premiumSalesChart"></canvas>
            </div>
        </div>

        <!-- Attention Needed List -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-bell" style="color: #f44336; margin-left: 8px;"></i> تتطلب التدخل السريع</h3>
            </div>
            <?php if (!empty(\$low_stock_items)): ?>
                <ul class="modern-list">
                    <?php foreach (\$low_stock_items as \$product): ?>
                    <li>
                        <div class="list-item-left">
                            <div class="item-img-placeholder">
                                <i class="fa fa-box"></i>
                            </div>
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars(admin_store_trim(\$product['p_name'], 30), ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p><?php echo admin_store_format_amount(\$product['p_current_price']); ?></p>
                            </div>
                        </div>
                        <div>
                            <span class="modern-badge <?php echo (int)\$product['p_qty'] <= 0 ? 'badge-danger' : 'badge-warning'; ?>">
                                <?php echo (int)\$product['p_qty'] <= 0 ? 'نفد' : 'متبقي ' . (int)\$product['p_qty']; ?>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: #a3aed1;">
                    <i class="fa fa-check-circle" style="font-size: 48px; color: #05cd99; margin-bottom: 16px; opacity: 0.8;"></i>
                    <h4 style="color: #1b2559;">المخزون ممتاز</h4>
                    <p>لا توجد أي منتجات تعاني من نقص في الكمية.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Format PHP data for Chart.js
const chartLabels = <?php echo json_encode(array_column(\$sales_days, 'label')); ?>;
const chartData = <?php echo json_encode(array_column(\$sales_days, 'revenue')); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('premiumSalesChart').getContext('2d');
    
    // Create gradient
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(67, 24, 255, 0.5)');   
    gradient.addColorStop(1, 'rgba(67, 24, 255, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'الإيرادات (دج)',
                data: chartData,
                backgroundColor: gradient,
                borderColor: '#4318ff',
                borderWidth: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#4318ff',
                pointBorderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4 // Smooth curves
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1b2559',
                    titleFont: { family: 'Cairo', size: 14 },
                    bodyFont: { family: 'Cairo', size: 14 },
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y.toLocaleString() + ' دج';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f4f7fe', drawBorder: false },
                    ticks: { font: { family: 'Outfit' }, color: '#a3aed1' }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { family: 'Cairo' }, color: '#a3aed1' }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });
});
</script>

<?php require_once('footer.php'); ?>
EOF;

file_put_contents($file, $php_logic . $premium_view);
echo "Premium dashboard installed!";
