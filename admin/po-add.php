<?php
require_once('header.php');

if(isset($_POST['create_po'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $order_date = $_POST['order_date'];
    $note = trim($_POST['note']);
    $created_by = $_SESSION['user']['id'] ?? 0;
    
    $product_ids = $_POST['product_id'] ?? [];
    $variant_ids = $_POST['variant_id'] ?? [];
    $quantities = $_POST['qty'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    
    $total_amount = 0;
    for($i=0; $i<count($product_ids); $i++) {
        $qty = (int)$quantities[$i];
        $price = (float)$unit_prices[$i];
        if($qty > 0) {
            $total_amount += ($qty * $price);
        }
    }

    if($supplier_id > 0 && $total_amount > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $dbRepo->prepare("INSERT INTO tbl_purchase_order (supplier_id, order_date, total_amount, note, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $order_date, $total_amount, $note, $created_by]);
            $po_id = $dbRepo->lastInsertId();

            for($i=0; $i<count($product_ids); $i++) {
                $pid = (int)$product_ids[$i];
                $vid = !empty($variant_ids[$i]) ? (int)$variant_ids[$i] : null;
                $qty = (int)$quantities[$i];
                $price = (float)$unit_prices[$i];
                
                if($qty > 0) {
                    $item_total = $qty * $price;
                    $stmtItem = $dbRepo->prepare("INSERT INTO tbl_purchase_order_item (po_id, product_id, variant_id, qty, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtItem->execute([$po_id, $pid, $vid, $qty, $price, $item_total]);
                }
            }
            $pdo->commit();
            $success_message = "تم إنشاء طلب الشراء بنجاح.";
        } catch(Exception $e) {
            $pdo->rollBack();
            $error_message = "حدث خطأ: " . $e->getMessage();
        }
    } else {
        $error_message = "يرجى تحديد المورد وإضافة منتج واحد على الأقل بكمية صحيحة.";
    }
}

// Fetch Suppliers
$stmt = $dbRepo->prepare("SELECT * FROM tbl_supplier WHERE active = 1 ORDER BY name ASC");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Products
$stmt = $dbRepo->prepare("SELECT p_id, p_name FROM tbl_product ORDER BY p_name ASC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Variants for JS
$stmt = $dbRepo->prepare("
    SELECT v.variant_id, v.p_id, s.size_name, c.color_name 
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
        <h1>إنشاء طلب شراء جديد (PO)</h1>
    </div>
    <div class="content-header-right">
        <a href="purchase-orders.php" class="btn btn-primary btn-sm">العودة للطلبات</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if(isset($error_message)): ?><div class="callout callout-danger"><p><?= $error_message; ?></p></div><?php endif; ?>
            <?php if(isset($success_message)): ?><div class="callout callout-success"><p><?= $success_message; ?></p></div><?php endif; ?>

            <form action="" method="post">
                <div class="box box-info">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>المورد *</label>
                                    <select name="supplier_id" class="form-control select2" required>
                                        <option value="">-- اختر المورد --</option>
                                        <?php foreach($suppliers as $s): ?>
                                        <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>تاريخ الطلب *</label>
                                    <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>ملاحظات</label>
                                    <input type="text" name="note" class="form-control">
                                </div>
                            </div>
                        </div>

                        <h4>المنتجات</h4>
                        <table class="table table-bordered" id="po-items-table">
                            <thead>
                                <tr>
                                    <th>المنتج *</th>
                                    <th>النوع (إن وجد)</th>
                                    <th>الكمية *</th>
                                    <th>سعر الوحدة (شراء) *</th>
                                    <th>الإجمالي</th>
                                    <th>إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="po-row">
                                    <td>
                                        <select name="product_id[]" class="form-control product-select" required>
                                            <option value="">-- اختر المنتج --</option>
                                            <?php foreach($products as $p): ?>
                                            <option value="<?= $p['p_id']; ?>"><?= htmlspecialchars($p['p_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="variant_id[]" class="form-control variant-select">
                                            <option value="">-- رئيسي --</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control row-qty" min="1" value="1" required></td>
                                    <td><input type="number" step="0.01" name="unit_price[]" class="form-control row-price" min="0" value="0" required></td>
                                    <td><input type="text" class="form-control row-total" readonly value="0"></td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right"><strong>المجموع الكلي:</strong></td>
                                    <td><input type="text" id="grand_total" class="form-control" readonly value="0"></td>
                                    <td><button type="button" class="btn btn-success btn-sm" id="add-row"><i class="fa fa-plus"></i> سطر جديد</button></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="create_po" class="btn btn-primary pull-right">إنشاء الطلب وإرساله للمورد</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
const variantsData = <?= $variants_json; ?>;

document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('#po-items-table tbody');
    const addRowBtn = document.getElementById('add-row');

    function updateGrandTotal() {        let gt = 0;
        document.querySelectorAll('.row-total').forEach(el => {
            gt += parseFloat(el.value || 0);
        });
        document.getElementById('grand_total').value = gt.toFixed(2);
    }

    function calculateRow(row) { global $dbRepo;
    global $dbRepo;

        const qty = parseFloat(row.querySelector('.row-qty').value) || 0;
        const price = parseFloat(row.querySelector('.row-price').value) || 0;
        row.querySelector('.row-total').value = (qty * price).toFixed(2);
        updateGrandTotal();
    }

    function populateVariants(row) { global $dbRepo;
    global $dbRepo;

        const p_id = row.querySelector('.product-select').value;
        const vSelect = row.querySelector('.variant-select');
        vSelect.innerHTML = '<option value="">-- رئيسي --</option>';
        
        if(p_id) {
            const productVariants = variantsData.filter(v => v.p_id == p_id);
            productVariants.forEach(v => {
                let name = [];
                if (v.size_name) name.push(v.size_name);
                if (v.color_name) name.push(v.color_name);
                let label = name.join(' / ');
                if (!label) label = 'Variant #' + v.variant_id;
                
                let opt = document.createElement('option');
                opt.value = v.variant_id;
                opt.textContent = label;
                vSelect.appendChild(opt);
            });
        }
    }

    tableBody.addEventListener('change', function(e) {
        if(e.target.classList.contains('product-select')) {
            populateVariants(e.target.closest('tr'));
        }
        if(e.target.classList.contains('row-qty') || e.target.classList.contains('row-price')) {
            calculateRow(e.target.closest('tr'));
        }
    });

    tableBody.addEventListener('input', function(e) {
        if(e.target.classList.contains('row-qty') || e.target.classList.contains('row-price')) {
            calculateRow(e.target.closest('tr'));
        }
    });

    tableBody.addEventListener('click', function(e) {
        if(e.target.closest('.remove-row')) {
            if(tableBody.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
                updateGrandTotal();
            }
        }
    });

    addRowBtn.addEventListener('click', function() {
        const firstRow = tableBody.querySelector('tr');
        const newRow = firstRow.cloneNode(true);
        newRow.querySelector('.product-select').value = '';
        newRow.querySelector('.variant-select').innerHTML = '<option value="">-- رئيسي --</option>';
        newRow.querySelector('.row-qty').value = '1';
        newRow.querySelector('.row-price').value = '0';
        newRow.querySelector('.row-total').value = '0';
        tableBody.appendChild(newRow);
    });
});
</script>

<?php require_once('footer.php'); ?>
