<?php
require_once('header.php');

// Dead Stock Report (30, 60, 90, 180 days)
$days = [30, 60, 90, 180];
$dead_stock = [];

foreach($days as $d) {
    $stmt = $dbRepo->prepare("
        SELECT p.p_name, p.p_qty as stock, ? as days_inactive
        FROM tbl_product p
        WHERE p.p_qty > 0 AND p.p_id NOT IN (
            SELECT DISTINCT product_id FROM tbl_order 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        )
        ORDER BY stock DESC LIMIT 10
    ");
    $stmt->execute([$d, $d]);
    $dead_stock[$d] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Exhaustion Prediction
// Calculate average daily sales over the last 30 days
$stmt = $dbRepo->query("
    SELECT p.p_id, p.p_name, p.p_qty, 
           SUM(o.quantity) as sold_last_30_days,
           (SUM(o.quantity) / 30) as avg_daily_sales
    FROM tbl_product p
    JOIN tbl_order o ON p.p_id = o.product_id
    WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.p_id
    HAVING avg_daily_sales > 0 AND p.p_qty > 0
    ORDER BY avg_daily_sales DESC
");
$predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <h1>تقارير التنبؤ ونفاد المخزون</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-line-chart"></i> توقع نفاد المخزون (بناءً على مبيعات آخر 30 يوم)</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th>المخزون الحالي</th>
                                <th>متوسط المبيعات اليومية</th>
                                <th>الأيام المتبقية لنفاد الكمية</th>
                                <th>التاريخ المتوقع للنفاد</th>
                                <th>الكمية المقترحة لإعادة الطلب (يكفي لـ 30 يوم)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($predictions as $p): 
                                $days_left = floor($p['p_qty'] / $p['avg_daily_sales']);
                                $exhaust_date = date('Y-m-d', strtotime("+$days_left days"));
                                $suggested_order = ceil($p['avg_daily_sales'] * 30);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($p['p_name']); ?></td>
                                <td><b><?= $p['p_qty']; ?></b></td>
                                <td><?= number_format($p['avg_daily_sales'], 2); ?> / يوم</td>
                                <td>
                                    <?php if($days_left <= 7): ?>
                                        <span class="label label-danger"><?= $days_left; ?> أيام</span>
                                    <?php elseif($days_left <= 15): ?>
                                        <span class="label label-warning"><?= $days_left; ?> أيام</span>
                                    <?php else: ?>
                                        <span class="label label-success"><?= $days_left; ?> يوم</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $exhaust_date; ?></td>
                                <td><span class="text-primary font-weight-bold"><?= $suggested_order; ?> وحدة</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($predictions)): ?>
                            <tr><td colspan="6">لا توجد بيانات كافية (لا مبيعات في آخر 30 يوم).</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <h2 class="page-header">تقارير المنتجات الراكدة</h2>
    <div class="row">
        <?php foreach($days as $d): ?>
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">لم تباع منذ أكثر من <?= $d; ?> يوم</h3>
                </div>
                <div class="box-body">
                    <table class="table table-condensed">
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية المتجمدة</th>
                        </tr>
                        <?php foreach($dead_stock[$d] as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['p_name']); ?></td>
                            <td><span class="badge bg-red"><?= $item['stock']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($dead_stock[$d])): ?>
                        <tr><td colspan="2" class="text-center">لا توجد منتجات مطابقة</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once('footer.php'); ?>
