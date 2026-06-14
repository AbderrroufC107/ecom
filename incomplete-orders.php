<?php
require_once('header.php');
// تضمين ملف التشفير
require_once('inc/encryption.php');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['customer'])) {
    header('location: login.php');
    exit;
}

$customer_phone = $_SESSION['customer']['customer_phone'];
$stmt_incomplete = $pdo->prepare("SELECT * FROM incomplete_orders WHERE customer_phone = ? ORDER BY COALESCE(last_updated, created_at) DESC");
$stmt_incomplete->execute([$customer_phone]);
$incomplete_orders = $stmt_incomplete->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">الطلبات غير المكتملة</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($incomplete_orders)): ?>
                            <div class="alert alert-info">
                                لا توجد طلبات غير مكتملة
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>رقم الطلب</th>
                                            <th>اسم المنتج</th>
                                            <th>تاريخ آخر تحديث</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($incomplete_orders as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['id']) ?></td>
                                                <td><?= htmlspecialchars($order['product_name']) ?></td>
                                                <td><?= date('Y-m-d H:i:s', strtotime($order['last_updated'] ?: $order['created_at'])) ?></td>
                                                <td>
                                                    <?php
                                                    // جلب قالب المنتج
                                                    $stmt_template = $pdo->prepare("SELECT product_template FROM tbl_product WHERE p_id = ?");
                                                    $stmt_template->execute([$order['product_id']]);
                                                    $template = $stmt_template->fetchColumn();
                                                    $template = $template ? $template : 'buy-now.php';
                                                    ?>
                                                    <a href="<?php echo create_secure_product_link($order['product_id'], $template); ?>" class="btn btn-primary btn-sm">
                                                        إكمال الطلب
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.page {
    direction: rtl;
    text-align: right;
    padding: 2rem 0;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.card-header {
    background: #fff;
    border-bottom: 2px solid #f0f0f0;
    padding: 1.5rem;
}

.card-title {
    margin: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: 600;
}

.table {
    margin: 0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

.btn-primary {
    background: #7c3aed;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #6d28d9;
    transform: translateY(-2px);
}

.alert {
    border: none;
    border-radius: 10px;
    padding: 1rem;
    margin: 0;
}

.alert-info {
    background: #e0f2fe;
    color: #0369a1;
}
</style>

<?php require_once('footer.php'); ?> 