<?php
require_once('header.php');
require_once('inc/stock_functions.php');

if (isset($_POST['adjust_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $variant_id = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $new_qty = (int)$_POST['new_qty'];
    $reason = $_POST['reason'];
    $note = trim($_POST['note']);
    $admin_id = $_SESSION['user']['id'] ?? 0;

    if ($product_id > 0 && $new_qty >= 0 && !empty($reason)) {
        try {
            stock_update_quantity($pdo, $product_id, $variant_id, $new_qty, $reason, 'manual_adjust', 0, $admin_id, $note);
            $success_message = 'تم تعديل المخزون بنجاح وتسجيل العملية في سجل التدقيق.';
        } catch (Exception $e) {
            $error_message = 'حدث خطأ أثناء تعديل المخزون: ' . $e->getMessage();
        }
    } else {
        $error_message = 'الرجاء تعبئة جميع الحقول بشكل صحيح.';
    }
}

// Fetch all products
$stmt = $dbRepo->prepare("SELECT p_id, p_name, p_qty FROM tbl_product ORDER BY p_id DESC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch variants for JS
$stmt = $dbRepo->prepare("
    SELECT v.variant_id, v.p_id, v.qty, s.size_name, c.color_name 
    FROM tbl_product_variant v
    LEFT JOIN tbl_size s ON v.size_id = s.size_id
    LEFT JOIN tbl_color c ON v.color_id = c.color_id
");
$stmt->execute();
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$variants_json = json_encode($variants);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>تعديل المخزون (Stock Adjustment)</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-6">
            <?php if(isset($error_message)): ?>
                <div class="callout callout-danger"><p><?= $error_message; ?></p></div>
            <?php endif; ?>
            <?php if(isset($success_message)): ?>
                <div class="callout callout-success"><p><?= $success_message; ?></p></div>
            <?php endif; ?>

            <div class="box box-info">
                <form method="post" action="">
                    <div class="box-body">
                        <div class="form-group">
                            <label>المنتج *</label>
                            <select name="product_id" id="product_id" class="form-control select2" required>
                                <option value="">-- اختر المنتج --</option>
                                <?php foreach($products as $p): ?>
                                    <option value="<?= $p['p_id']; ?>" data-qty="<?= $p['p_qty']; ?>">
                                        <?= htmlspecialchars($p['p_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" id="variant_group" style="display:none;">
                            <label>النوع (المقاس/اللون)</label>
                            <select name="variant_id" id="variant_id" class="form-control">
                                <option value="">-- لا يوجد أنواع فرعية (استخدم المنتج الرئيسي) --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>الكمية الحالية</label>
                            <input type="text" id="current_qty" class="form-control" readonly value="0">
                        </div>

                        <div class="form-group">
                            <label>الكمية الجديدة *</label>
                            <input type="number" name="new_qty" class="form-control" min="0" required>
                        </div>

                        <div class="form-group">
                            <label>سبب التعديل *</label>
                            <select name="reason" class="form-control" required>
                                <option value="">-- اختر السبب --</option>
                                <option value="Inventory Count">جرد المخزن</option>
                                <option value="Damaged">منتج تالف</option>
                                <option value="Missing">منتج مفقود</option>
                                <option value="Expired">منتج منتهي الصلاحية</option>
                                <option value="Manual Edit">تعديل يدوي</option>
                                <option value="Transfer">نقل بين المخازن</option>
                                <option value="Other">سبب آخر</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>ملاحظات إضافية (مطلوبة للتدقيق) *</label>
                            <textarea name="note" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="adjust_stock" class="btn btn-success">تأكيد التعديل</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="callout callout-warning">
                <h4><i class="icon fa fa-warning"></i> تنبيه هام!</h4>
                <p>جميع عمليات تعديل المخزون من هذه الصفحة يتم تسجيلها في <b>سجل التدقيق الدائم (Audit Log)</b>. لا يمكن حذف هذه السجلات لاحقاً.</p>
                <p>سيتم تسجيل اسم الحساب وعنوان الـ IP ووقت العملية.</p>
            </div>
        </div>
    </div>
</section>

<script>
const variants = <?= $variants_json; ?>;

document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const variantSelect = document.getElementById('variant_id');
    const variantGroup = document.getElementById('variant_group');
    const currentQtyInput = document.getElementById('current_qty');

    productSelect.addEventListener('change', function() {
        const p_id = this.value;
        const p_qty = this.options[this.selectedIndex].getAttribute('data-qty');
        
        // Reset variant options
        variantSelect.innerHTML = '<option value="">-- لا يوجد أنواع فرعية (استخدم المنتج الرئيسي) --</option>';
        variantSelect.value = '';
        
        if (p_id) {
            currentQtyInput.value = p_qty;
            
            // Filter variants
            const productVariants = variants.filter(v => v.p_id == p_id);
            if (productVariants.length > 0) {
                variantGroup.style.display = 'block';
                productVariants.forEach(v => {
                    let name = [];
                    if (v.size_name) name.push(v.size_name);
                    if (v.color_name) name.push(v.color_name);
                    let label = name.join(' / ');
                    if (!label) label = 'Variant #' + v.variant_id;
                    
                    let opt = document.createElement('option');
                    opt.value = v.variant_id;
                    opt.textContent = label;
                    opt.setAttribute('data-qty', v.qty);
                    variantSelect.appendChild(opt);
                });
            } else {
                variantGroup.style.display = 'none';
            }
        } else {
            variantGroup.style.display = 'none';
            currentQtyInput.value = '0';
        }
    });

    variantSelect.addEventListener('change', function() {
        if (this.value) {
            const qty = this.options[this.selectedIndex].getAttribute('data-qty');
            currentQtyInput.value = qty;
        } else {
            // Fallback to product qty
            const p_qty = productSelect.options[productSelect.selectedIndex].getAttribute('data-qty');
            currentQtyInput.value = p_qty;
        }
    });
});
</script>

<?php require_once('footer.php'); ?>
