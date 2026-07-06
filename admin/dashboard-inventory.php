<?php
require_once('header.php');

// Statistics Calculation

// 1. Total Inventory Value (Approximation using last PO price, or 0 if none)
$stmt = $dbRepo->query("
    SELECT SUM(v.qty * COALESCE((SELECT unit_price FROM tbl_purchase_order_item WHERE variant_id = v.variant_id ORDER BY id DESC LIMIT 1), 0)) as value
    FROM tbl_product_variant v
");
$inventory_value = (float)$stmt->fetchColumn();

// Fallback to parent product if no variants
$stmt = $dbRepo->query("
    SELECT SUM(p.p_qty * COALESCE((SELECT unit_price FROM tbl_purchase_order_item WHERE product_id = p.p_id AND variant_id IS NULL ORDER BY id DESC LIMIT 1), 0)) as value
    FROM tbl_product p
    WHERE NOT EXISTS (SELECT 1 FROM tbl_product_variant WHERE p_id = p.p_id)
");
$inventory_value += (float)$stmt->fetchColumn();

// 2. Out of stock / Low stock
$stmt = $dbRepo->query("SELECT stock_low_threshold FROM tbl_settings LIMIT 1");
$low_threshold = (int)$stmt->fetchColumn() ?: 5;

$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_product WHERE p_qty <= 0 AND NOT EXISTS (SELECT 1 FROM tbl_product_variant WHERE p_id = tbl_product.p_id)");
$out_of_stock_parents = (int)$stmt->fetchColumn();

$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_product_variant WHERE qty <= 0");
$out_of_stock_variants = (int)$stmt->fetchColumn();

$out_of_stock = $out_of_stock_parents + $out_of_stock_variants;

$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_product WHERE p_qty > 0 AND p_qty <= {$low_threshold} AND NOT EXISTS (SELECT 1 FROM tbl_product_variant WHERE p_id = tbl_product.p_id)");
$low_stock_parents = (int)$stmt->fetchColumn();

$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_product_variant WHERE qty > 0 AND qty <= {$low_threshold}");
$low_stock_variants = (int)$stmt->fetchColumn();

$low_stock = $low_stock_parents + $low_stock_variants;

// 3. Best Sellers (Last 30 Days)
$stmt = $dbRepo->query("
    SELECT p.p_name, SUM(o.quantity) as total_sold
    FROM tbl_order o
    JOIN tbl_product p ON o.product_id = p.p_id
    WHERE o.order_status = 'Delivered' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.p_id
    ORDER BY total_sold DESC
    LIMIT 5
");
$best_sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Dead Stock (No sales in 90 days, but we have stock)
$stmt = $dbRepo->query("
    SELECT p.p_name, p.p_qty as stock
    FROM tbl_product p
    WHERE p.p_qty > 0 AND p.p_id NOT IN (
        SELECT DISTINCT product_id FROM tbl_order 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    )
    LIMIT 10
");
$dead_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<section class="content-header">
    <h1>لوحة إحصائيات المخزون (ERP Dashboard)</h1>
</section>

<section class="content">
    <div class="row">
        <!-- Value -->
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?= number_format($inventory_value, 2); ?></h3>
                    <p>قيمة المخزون الإجمالية (دج)</p>
                </div>
                <div class="icon">
                    <i class="ion ion-social-usd"></i>
                </div>
            </div>
        </div>
        
        <!-- Out of stock -->
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3><?= $out_of_stock; ?></h3>
                    <p>منتجات منتهية</p>
                </div>
                <div class="icon">
                    <i class="ion ion-close-circled"></i>
                </div>
            </div>
        </div>

        <!-- Low stock -->
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3><?= $low_stock; ?></h3>
                    <p>منتجات قليلة الكمية</p>
                </div>
                <div class="icon">
                    <i class="ion ion-alert-circled"></i>
                </div>
            </div>
        </div>

        <!-- Health -->
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <?php
                        $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_product");
                        $total = $stmt->fetchColumn();
                        $health = $total > 0 ? round((($total - $out_of_stock - $low_stock) / $total) * 100) : 100;
                    ?>
                    <h3><?= $health; ?><sup style="font-size: 20px">%</sup></h3>
                    <p>مؤشر صحة المخزون</p>
                </div>
                <div class="icon">
                    <i class="ion ion-pie-graph"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Best Sellers -->
        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">الأكثر مبيعاً (آخر 30 يوم) - سريعة الحركة</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية المباعة</th>
                        </tr>
                        <?php foreach($best_sellers as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['p_name']); ?></td>
                            <td><span class="badge bg-green"><?= $b['total_sold']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($best_sellers)): ?>
                        <tr><td colspan="2">لا توجد بيانات مبيعات لآخر 30 يوم.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dead Stock -->
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">المنتجات الراكدة (لا مبيعات لآخر 90 يوم)</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>المنتج</th>
                            <th>المخزون المتجمد</th>
                        </tr>
                        <?php foreach($dead_stock as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['p_name']); ?></td>
                            <td><span class="badge bg-red"><?= $d['stock']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($dead_stock)): ?>
                        <tr><td colspan="2">ممتاز! لا توجد منتجات راكدة.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="box-footer">
                    <a href="stock-prediction.php" class="btn btn-default btn-sm btn-block">الذهاب لتقرير التنبؤ و المخزون الراكد بالتفصيل</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
