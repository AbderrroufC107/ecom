<?php
require_once('header.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Super Admin') {
    echo '<script>window.location.href="login.php";</script>';
    exit;
}

// Analytics Queries
$total_products = $dbRepo->query("SELECT COUNT(*) FROM tbl_product WHERE p_is_active = 1")->fetchColumn();
$variants_count = $dbRepo->query("SELECT COUNT(*) FROM tbl_product_variant v JOIN tbl_product p ON v.p_id = p.p_id WHERE p.p_is_active = 1")->fetchColumn();

// Movements
$stmt = $dbRepo->prepare("
    SELECT m.*, p.p_name, s.size_name, c.color_name, a.full_name as admin_name 
    FROM tbl_stock_movements m
    LEFT JOIN tbl_product p ON m.product_id = p.p_id
    LEFT JOIN tbl_product_variant v ON m.variant_id = v.variant_id
    LEFT JOIN tbl_size s ON v.size_id = s.size_id
    LEFT JOIN tbl_color c ON v.color_id = c.color_id
    LEFT JOIN tbl_user a ON m.admin_id = a.id
    ORDER BY m.id DESC LIMIT 100
");
$stmt->execute();
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<section class="content-header">
    <div class="content-header-left">
        <h1>تقارير وحركة المخزون</h1>
    </div>
    <div class="content-header-right">
        <a href="stock.php" class="btn btn-primary btn-sm">العودة إلى المخزون</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">إجمالي المنتجات</span>
                    <span class="info-box-number"><?php echo $total_products; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-tags"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">إجمالي المتغيرات (Variants)</span>
                    <span class="info-box-number"><?php echo $variants_count; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">سجل حركة المخزون (أحدث 100 عملية)</h3>
                </div>
                <div class="box-body table-responsive">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>المنتج</th>
                                <th>النوع/المقاس</th>
                                <th>نوع العملية</th>
                                <th>السبب</th>
                                <th>الكمية السابقة</th>
                                <th>التغير</th>
                                <th>الكمية الجديدة</th>
                                <th>بواسطة</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($movements as $m): ?>
                            <tr>
                                <td dir="ltr" style="text-align:right;"><?php echo date('Y-m-d H:i', strtotime($m['created_at'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($m['p_name']); ?>
                                    <?php 
                                        $variant_label = [];
                                        if ($m['size_name']) $variant_label[] = $m['size_name'];
                                        if ($m['color_name']) $variant_label[] = $m['color_name'];
                                        
                                        if (!empty($variant_label)) {
                                            echo '<br><small class="text-muted">↳ ' . htmlspecialchars(implode(' / ', $variant_label)) . '</small>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['size_name'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                        if($m['type']=='in') echo '<span class="label label-success">إضافة <i class="fa fa-arrow-up"></i></span>';
                                        elseif($m['type']=='out') echo '<span class="label label-danger">خصم <i class="fa fa-arrow-down"></i></span>';
                                        else echo '<span class="label label-default">تحديد</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($m['reason']); ?></td>
                                <td><?php echo $m['quantity_before']; ?></td>
                                <td dir="ltr" style="text-align:right;">
                                    <?php echo ($m['quantity_change'] > 0 ? '+' : '') . $m['quantity_change']; ?>
                                </td>
                                <td><b><?php echo $m['quantity_after']; ?></b></td>
                                <td><?php echo htmlspecialchars($m['admin_name'] ?? 'النظام'); ?></td>
                                <td><?php echo htmlspecialchars($m['notes'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
