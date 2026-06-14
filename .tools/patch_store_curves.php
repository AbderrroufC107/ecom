<?php
$file = 'store.php';
$content = file_get_contents($file);

// 1. Add Ecotrack PHP Logic
$ecotrack_logic = <<<'EOF'
// Ecotrack Delivery Stats
\$eco_stats = ['delivered' => 0, 'returned' => 0, 'transit' => 0];
try {
    \$stmt = \$pdo->query("SELECT ecotrack_remote_status FROM tbl_order WHERE ecotrack_tracking IS NOT NULL AND ecotrack_tracking != ''");
    while(\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
        \$status = mb_strtolower((string)\$row['ecotrack_remote_status'], 'UTF-8');
        if(strpos(\$status, 'livré') !== false || strpos(\$status, 'delivered') !== false) {
            \$eco_stats['delivered']++;
        } elseif(strpos(\$status, 'retour') !== false || strpos(\$status, 'returned') !== false) {
            \$eco_stats['returned']++;
        } else {
            \$eco_stats['transit']++;
        }
    }
} catch(Exception \$e) {}
EOF;

// Insert PHP logic right before ?> 
$content = str_replace('?>', $ecotrack_logic . "\n?>", $content);

// 2. Add the Shipping Chart HTML
$shipping_html = <<<'EOF'
    <!-- Secondary Analytics Row -->
    <div class="grid grid-cols-2-1" style="margin-top: 24px;">
        <!-- Shipping Distribution (Ecotrack) -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-pie-chart" style="color: #ff9800; margin-left: 8px;"></i> إحصائيات التوصيل (ECOTRACK)</h3>
                <a href="order-statistics.php#ordersTable" class="btn-light-premium">متابعة الشحنات</a>
            </div>
            <div class="chart-container" style="height: 250px; display: flex; align-items: center; justify-content: center;">
                <canvas id="shippingChart"></canvas>
            </div>
        </div>

        <!-- Orders Curve -->
        <div class="glass-card">
            <div class="section-head">
                <h3><i class="fa fa-area-chart" style="color: #05cd99; margin-left: 8px;"></i> منحنى الطلبات اليومية</h3>
            </div>
            <div class="chart-container" style="height: 250px;">
                <canvas id="ordersCurveChart"></canvas>
            </div>
        </div>
    </div>
</div>
EOF;

// Replace the closing </div> of premium-dashboard with the new HTML
$content = str_replace('    </div>'."\n".'</div>', '    </div>'."\n".$shipping_html, $content);

// 3. Add JS for the new charts
$js_addition = <<<'EOF'
// Orders Data
const ordersData = [];
// Shipping Data
const shipData = [0, 0, 0];

// Orders Curve
const ctxOrders = document.getElementById('ordersCurveChart').getContext('2d');
let gradOrders = ctxOrders.createLinearGradient(0, 0, 0, 300);
gradOrders.addColorStop(0, 'rgba(5, 205, 153, 0.5)');
gradOrders.addColorStop(1, 'rgba(5, 205, 153, 0.0)');

new Chart(ctxOrders, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'عدد الطلبات',
            data: ordersData,
            backgroundColor: gradOrders,
            borderColor: '#05cd99',
            borderWidth: 4,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#05cd99',
            pointBorderWidth: 3,
            pointRadius: 4,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f4f7fe' } },
            x: { grid: { display: false } }
        }
    }
});

// Shipping Doughnut
const ctxShip = document.getElementById('shippingChart').getContext('2d');
new Chart(ctxShip, {
    type: 'doughnut',
    data: {
        labels: ['تم التسليم', 'مرتجع', 'قيد التوصيل'],
        datasets: [{
            data: shipData,
            backgroundColor: ['#05cd99', '#f44336', '#ff9800'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { position: 'right', labels: { font: { family: 'Cairo' } } }
        }
    }
});
EOF;

$content = str_replace('});'."\n".'</script>', "});\n\n" . $js_addition . "\n</script>", $content);

file_put_contents($file, $content);
echo "Added more curves and delivery stats!";
