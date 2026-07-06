<?php
require_once('header.php');

if(isset($_POST['add_supplier'])) {
    $name = trim($_POST['name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    if(empty($name)) {
        $error_message = 'اسم المورد مطلوب.';
    } else {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_supplier (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact_person, $phone, $email, $address]);
        $success_message = 'تم إضافة المورد بنجاح.';
    }
}

if(isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $dbRepo->prepare("DELETE FROM tbl_supplier WHERE id = ?");
    $stmt->execute([$id]);
    $success_message = 'تم حذف المورد بنجاح.';
}

$stmt = $dbRepo->prepare("SELECT * FROM tbl_supplier ORDER BY id DESC");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة الموردين</h1>
    </div>
    <div class="content-header-right">
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal">إضافة مورد جديد</button>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if(isset($error_message)): ?><div class="callout callout-danger"><p><?= $error_message; ?></p></div><?php endif; ?>
            <?php if(isset($success_message)): ?><div class="callout callout-success"><p><?= $success_message; ?></p></div><?php endif; ?>

            <div class="box box-info">
                <div class="box-body table-responsive">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم المورد</th>
                                <th>مسؤول التواصل</th>
                                <th>الهاتف</th>
                                <th>البريد الإلكتروني</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($suppliers as $row): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['contact_person']); ?></td>
                                <td><?= htmlspecialchars($row['phone']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <!-- Edit could be added via a modal or separate page later -->
                                    <a href="#" class="btn btn-primary btn-xs" onclick="alert('تعديل المورد غير متوفر في هذه النسخة السريعة'); return false;">تعديل</a>
                                    <a href="supplier.php?delete_id=<?= $row['id']; ?>" class="btn btn-danger btn-xs" onClick="return confirm('هل أنت متأكد من الحذف؟');">حذف</a>
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">إضافة مورد جديد</h4>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>اسم المورد *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>مسؤول التواصل</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>العنوان</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_supplier" class="btn btn-success">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
