<?php
require_once('header.php');

$stmt = $dbRepo->prepare("
    SELECT po.*, s.name as supplier_name, u.full_name as creator_name
    FROM tbl_purchase_order po
    LEFT JOIN tbl_supplier s ON po.supplier_id = s.id
    LEFT JOIN tbl_user u ON po.created_by = u.id
    ORDER BY po.id DESC
");
$stmt->execute();
$purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(isset($_GET['cancel_id'])) {
    $id = (int)$_GET['cancel_id'];
    $dbRepo->prepare("UPDATE tbl_purchase_order SET status = 'Cancelled' WHERE id = ?")->execute([$id]);
    $success_message = "تم إلغاء طلب الشراء بنجاح.";
    header("Refresh:1; url=purchase-orders.php");
}

if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة طلبات الشراء (Purchase Orders)</h1>
    </div>
    <div class="content-header-right">
        <a href="po-add.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> إنشاء طلب شراء جديد</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if(isset($success_message)): ?><div class="callout callout-success"><p><?= $success_message; ?></p></div><?php endif; ?>

            <div class="box box-info">
                <div class="box-body table-responsive">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th># PO</th>
                                <th>المورد</th>
                                <th>تاريخ الطلب</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th>بواسطة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($purchase_orders as $row): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['supplier_name']); ?></td>
                                <td><?= date('Y-m-d', strtotime($row['order_date'])); ?></td>
                                <td><?= number_format($row['total_amount'], 2); ?> دج</td>
                                <td>
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <span class="label label-warning">قيد الانتظار</span>
                                    <?php elseif($row['status'] == 'Received'): ?>
                                        <span class="label label-success">مستلم</span>
                                    <?php elseif($row['status'] == 'Cancelled'): ?>
                                        <span class="label label-danger">ملغي</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['creator_name']); ?></td>
                                <td>
                                    <!-- <a href="po-view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-xs">عرض التفاصيل</a> -->
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <a href="po-receive.php?id=<?= $row['id']; ?>" class="btn btn-success btn-xs"><i class="fa fa-check"></i> استلام البضاعة</a>
                                        <a href="purchase-orders.php?cancel_id=<?= $row['id']; ?>" class="btn btn-danger btn-xs" onClick="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟');"><i class="fa fa-times"></i> إلغاء</a>
                                    <?php endif; ?>
                                </td>
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
