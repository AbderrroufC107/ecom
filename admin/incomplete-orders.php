<?php require_once('header.php'); ?>
<?php
require_once __DIR__ . '/inc/config.php';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM incomplete_orders WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $_SESSION['incomplete_flash'] = ['type' => 'success', 'message' => 'تم حذف السجل غير المكتمل بنجاح.'];
    header('Location: incomplete-orders.php');
    exit;
}

$flash = $_SESSION['incomplete_flash'] ?? null;
unset($_SESSION['incomplete_flash']);

$stmt = $pdo->query("SELECT * FROM incomplete_orders ORDER BY COALESCE(last_updated, created_at) DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.incomplete-wrap { direction: rtl; text-align: right; }
.incomplete-hero { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; }
.incomplete-title { margin: 0; font-weight: 800; color: #0f172a; }
.incomplete-sub { color: #64748b; margin-top: 6px; }
.incomplete-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(15,23,42,.06); padding: 14px; }
.incomplete-table th { white-space: nowrap; font-weight: 700; background: #f8fafc; }
.incomplete-table td { vertical-align: middle !important; }
.incomplete-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.incomplete-empty { text-align: center; padding: 26px; color: #64748b; }
</style>

<section class="content incomplete-wrap">
    <div class="incomplete-hero">
        <div>
            <h3 class="incomplete-title"><i class="fa fa-exclamation-triangle"></i> السلات المتروكة (طلبات غير مكتملة)</h3>
            <div class="incomplete-sub">تحويل السجل إلى طلب متاح فقط إذا رقم الهاتف غير مسجل مسبقًا في جدول الطلبات.</div>
        </div>
        <a href="order.php" class="btn btn-default"><i class="fa fa-shopping-cart"></i> إدارة الطلبات</a>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="alert alert-<?php echo ($flash['type'] ?? 'info') === 'danger' ? 'danger' : 'success'; ?>">
            <?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="incomplete-card">
        <form method="post" action="incomplete-orders-bulk.php" onsubmit="return confirm('تأكيد تنفيذ العملية على المحدد؟');">
            <div class="table-responsive">
                <table class="table table-bordered table-hover incomplete-table">
                    <thead>
                        <tr>
                            <th style="width: 48px;"><input type="checkbox" id="select_all_incomplete"></th>
                            <th>العميل</th>
                            <th>الهاتف</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>الإجمالي</th>
                            <th>نوع التوصيل</th>
                            <th>الولاية</th>
                            <th>البلدية</th>
                            <th>آخر تحديث</th>
                            <th style="min-width: 220px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="11" class="incomplete-empty">لا توجد طلبات غير مكتملة حالياً.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="<?= (int)$order['id'] ?>" class="incomplete_cb"></td>
                                    <td><?= htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($order['quantity'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($order['total_price'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?php
                                        $delivery_type = $order['delivery_type'] ?? '';
                                        if (function_exists('admin_normalize_delivery_type_text')) {
                                            $delivery_type = admin_normalize_delivery_type_text($delivery_type);
                                        }
                                        echo htmlspecialchars((string) $delivery_type, ENT_QUOTES, 'UTF-8');
                                    ?></td>
                                    <td><?= htmlspecialchars((string)($order['wilaya'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($order['commune'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php
                                        $last = !empty($order['last_updated']) ? $order['last_updated'] : ($order['created_at'] ?? '');
                                        echo $last !== '' ? date('d/m/Y H:i', strtotime((string)$last)) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="incomplete-actions">
                                            <a href="?delete=<?= (int)$order['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل تريد حذف هذا السجل؟');">
                                                <i class="fa fa-trash"></i> حذف
                                            </a>
                                            <a href="order-add-from-incomplete.php?id=<?= (int)$order['id'] ?>" class="btn btn-success btn-xs" onclick="return confirm('تحويل هذا السجل إلى طلب؟');">
                                                <i class="fa fa-check"></i> تحويل إلى طلب
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:10px;">
                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">
                    <i class="fa fa-trash"></i> حذف المحدد
                </button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('select_all_incomplete');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.incomplete_cb').forEach(function (checkbox) {
            checkbox.checked = !!selectAll.checked;
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
