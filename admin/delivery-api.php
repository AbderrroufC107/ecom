<?php
require_once('header.php');

// Fetch all delivery companies
$stmt = $dbRepo->prepare("SELECT * FROM tbl_delivery_company ORDER BY id ASC");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['form1'])) {
    $id = (int)$_POST['company_id'];
    $api_enabled = isset($_POST['api_enabled']) ? 1 : 0;
    $api_type = $_POST['api_type'] ?? '';
    $api_key = $_POST['api_key'] ?? '';
    $api_token = $_POST['api_token'] ?? '';
    $api_username = $_POST['api_username'] ?? '';
    $api_password = $_POST['api_password'] ?? '';
    $api_base_url = $_POST['api_base_url'] ?? '';

    $stmt = $dbRepo->prepare("
        UPDATE tbl_delivery_company 
        SET api_enabled=?, api_type=?, api_key=?, api_token=?, api_username=?, api_password=?, api_base_url=?
        WHERE id=?
    ");
    $stmt->execute([
        $api_enabled,
        $api_type,
        $api_key,
        $api_token,
        $api_username,
        $api_password,
        $api_base_url,
        $id
    ]);

    $success_message = 'تم تحديث إعدادات API بنجاح.';
    
    // Refresh data
    $stmt = $dbRepo->prepare("SELECT * FROM tbl_delivery_company ORDER BY id ASC");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>ربط شركات التوصيل (API Integrations)</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if(isset($error_message) && $error_message): ?>
            <div class="callout callout-danger"><p><?= $error_message; ?></p></div>
            <?php endif; ?>
            <?php if(isset($success_message) && $success_message): ?>
            <div class="callout callout-success"><p><?= $success_message; ?></p></div>
            <?php endif; ?>

            <div class="box box-info">
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم الشركة</th>
                                <th>حالة الربط</th>
                                <th>نوع الـ API</th>
                                <th>إعدادات API</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($companies as $row): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td>
                                    <?php if($row['api_enabled'] == 1): ?>
                                        <span class="label label-success">مفعل</span>
                                    <?php else: ?>
                                        <span class="label label-danger">معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['api_type']); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-xs" data-toggle="modal" data-target="#apiModal<?= $row['id']; ?>">تعديل الإعدادات</button>
                                </td>
                            </tr>

                            <!-- Modal for API Settings -->
                            <div class="modal fade" id="apiModal<?= $row['id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                            <h4 class="modal-title">إعدادات API لشركة: <?= htmlspecialchars($row['name']); ?></h4>
                                        </div>
                                        <form method="post" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="company_id" value="<?= $row['id']; ?>">
                                                
                                                <div class="form-group">
                                                    <label>تفعيل الربط (API Enabled)</label>
                                                    <div class="checkbox">
                                                        <label>
                                                            <input type="checkbox" name="api_enabled" value="1" <?= $row['api_enabled'] == 1 ? 'checked' : ''; ?>>
                                                            مفعل
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>مزود الخدمة (API Type)</label>
                                                    <select name="api_type" class="form-control">
                                                        <option value="">-- اختر المزود --</option>
                                                        <option value="yalidine" <?= $row['api_type'] == 'yalidine' ? 'selected' : ''; ?>>Yalidine</option>
                                                        <option value="zrexpress" <?= $row['api_type'] == 'zrexpress' ? 'selected' : ''; ?>>ZR Express</option>
                                                        <option value="noest" <?= $row['api_type'] == 'noest' ? 'selected' : ''; ?>>Noest</option>
                                                        <option value="ems" <?= $row['api_type'] == 'ems' ? 'selected' : ''; ?>>EMS</option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label>API Key</label>
                                                    <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($row['api_key']); ?>">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>API Token / Secret</label>
                                                    <input type="text" name="api_token" class="form-control" value="<?= htmlspecialchars($row['api_token']); ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label>اسم المستخدم (إن وجد)</label>
                                                    <input type="text" name="api_username" class="form-control" value="<?= htmlspecialchars($row['api_username']); ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label>كلمة المرور (إن وجدت)</label>
                                                    <input type="password" name="api_password" class="form-control" value="<?= htmlspecialchars($row['api_password']); ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label>الرابط الأساسي (Base URL)</label>
                                                    <input type="text" name="api_base_url" class="form-control" value="<?= htmlspecialchars($row['api_base_url']); ?>" placeholder="https://api.example.com">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                                                <button type="submit" name="form1" class="btn btn-success">حفظ الإعدادات</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
