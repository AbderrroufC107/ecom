<?php
require_once('header.php');

if (!isset($_SESSION['user'])) {
    echo '<script>window.location.href="login.php";</script>';
    exit;
}

require_once('inc/stock_functions.php');

$is_manager = ($_SESSION['user']['role'] === 'Super Admin' || $_SESSION['user']['role'] === 'Admin');
$filters = [];
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $filters['stock_status'] = $_GET['status'];
}
$stock_data = stock_get_all($pdo, $filters);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة المخزون الشاملة</h1>
    </div>
    <?php if($is_manager): ?>
    <div class="content-header-right">
        <a href="stock-export.php" class="btn btn-default btn-sm" target="_blank"><i class="fa fa-download"></i> تصدير (CSV)</a>
        <a href="settings-stock.php" class="btn btn-primary btn-sm">إعدادات التنبيهات</a>
        <a href="stock-reports.php" class="btn btn-success btn-sm">تقارير المخزون</a>
        <a href="stock-adjustment.php" class="btn btn-warning btn-sm">تعديل المخزون (تسوية)</a>
    </div>
    <?php endif; ?>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            
            <div class="box box-info">
                <div class="box-body table-responsive">
                    <form method="get" action="" class="form-inline" style="margin-bottom: 20px;">
                        <div class="form-group">
                            <label>تصفية الحالة: </label>
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">جميع المنتجات</option>
                                <option value="in_stock" <?php if(isset($_GET['status']) && $_GET['status']=='in_stock') echo 'selected'; ?>>متوفرة</option>
                                <option value="low_stock" <?php if(isset($_GET['status']) && $_GET['status']=='low_stock') echo 'selected'; ?>>على وشك النفاد</option>
                                <option value="out_of_stock" <?php if(isset($_GET['status']) && $_GET['status']=='out_of_stock') echo 'selected'; ?>>نفدت</option>
                            </select>
                        </div>
                    </form>

                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="50">الصورة</th>
                                <th>المنتج / النوع</th>
                                <th>SKU</th>
                                <th>Barcode</th>
                                <th>المخزون الحالي</th>
                                <th>المحجوز</th>
                                <th>المتاح للبيع</th>
                                <th>الحالة</th>
                                <?php if($is_manager): ?>
                                <th>تحديث سريع</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stock_data as $p): ?>
                                <?php if (!$p['has_variants']): ?>
                                <tr>
                                    <td><img src="<?php echo get_admin_image_url($p['p_featured_photo']); ?>" width="50"></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['p_name']); ?></strong>
                                        <a href="product-timeline.php?id=<?php echo $p['p_id']; ?>" class="btn btn-default btn-xs pull-left" title="سجل حركة المنتج"><i class="fa fa-history"></i> سجل الحركة</a>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['p_sku'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['p_barcode'] ?? '-'); ?></td>
                                    <td><b class="current-qty"><?php echo $p['p_qty']; ?></b></td>
                                    <td style="color:#f39c12;"><?php echo $p['reserved']; ?></td>
                                    <td style="color:#00a65a; font-weight:bold;" class="available-qty"><?php echo $p['available']; ?></td>
                                    <td>
                                        <?php if($p['p_qty'] <= 0): ?>
                                            <span class="label label-danger">نفد</span>
                                        <?php elseif($p['p_qty'] <= 5): ?>
                                            <span class="label label-warning">منخفض</span>
                                        <?php else: ?>
                                            <span class="label label-success">متوفر</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if($is_manager): ?>
                                    <td>
                                        <div class="input-group input-group-sm" style="width:120px;">
                                            <input type="number" class="form-control stock-input" value="<?php echo $p['p_qty']; ?>" min="0">
                                            <span class="input-group-btn">
                                                <button class="btn btn-info btn-flat update-stock-btn" data-pid="<?php echo $p['p_id']; ?>" data-vid="">حفظ</button>
                                            </span>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php else: ?>
                                    <!-- Parent row for visual grouping -->
                                    <tr style="background:#f9fafb;">
                                        <td><img src="<?php echo get_admin_image_url($p['p_featured_photo']); ?>" width="50"></td>
                                        <td colspan="8">
                                            <strong><?php echo htmlspecialchars($p['p_name']); ?></strong> (متعدد المقاسات/الأنواع)
                                            <a href="product-timeline.php?id=<?php echo $p['p_id']; ?>" class="btn btn-default btn-xs pull-left" title="سجل حركة المنتج"><i class="fa fa-history"></i> سجل الحركة</a>
                                        </td>
                                    </tr>
                                    <?php foreach($p['variants'] as $v): ?>
                                    <tr>
                                        <td></td>
                                        <td style="padding-right: 30px;">↳ <?php echo htmlspecialchars($v['variant_name'] ?? $v['size_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($v['sku'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($v['barcode'] ?? '-'); ?></td>
                                        <td><b class="current-qty"><?php echo $v['qty']; ?></b></td>
                                        <td style="color:#f39c12;"><?php echo $v['reserved']; ?></td>
                                        <td style="color:#00a65a; font-weight:bold;" class="available-qty"><?php echo $v['available']; ?></td>
                                        <td>
                                            <?php if($v['qty'] <= 0): ?>
                                                <span class="label label-danger">نفد</span>
                                            <?php elseif($v['qty'] <= 5): ?>
                                                <span class="label label-warning">منخفض</span>
                                            <?php else: ?>
                                                <span class="label label-success">متوفر</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if($is_manager): ?>
                                        <td>
                                            <div class="input-group input-group-sm" style="width:120px;">
                                                <input type="number" class="form-control stock-input" value="<?php echo $v['qty']; ?>" min="0">
                                                <span class="input-group-btn">
                                                    <button class="btn btn-info btn-flat update-stock-btn" data-pid="<?php echo $p['p_id']; ?>" data-vid="<?php echo $v['variant_id']; ?>">حفظ</button>
                                                </span>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</section>

<?php if($is_manager): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $(document).on('click', '.update-stock-btn', function() {
        var btn = $(this);
        var input = btn.closest('.input-group').find('.stock-input');
        var newQty = input.val();
        var pid = btn.data('pid');
        var vid = btn.data('vid');

        btn.text('...');
        btn.prop('disabled', true);

        $.post('ajax-stock-update.php', {
            product_id: pid,
            variant_id: vid,
            qty: newQty
        }, function(res) {
            btn.text('حفظ');
            btn.prop('disabled', false);

            if(res.success) {
                var row = btn.closest('tr');
                row.find('.current-qty').text(res.new_qty);
                row.find('.available-qty').text(res.available);
                
                // Show a quick success toast
                if(typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false,
                        timer: 2000, icon: 'success', title: 'تم تحديث الكمية بنجاح'
                    });
                }
            } else {
                alert('خطأ: ' + (res.message || 'حدث خطأ غير متوقع'));
            }
        }).fail(function() {
            btn.text('حفظ');
            btn.prop('disabled', false);
            alert('فشل الاتصال بالخادم.');
        });
    });
});
</script>
<?php endif; ?>

<?php require_once('footer.php'); ?>
