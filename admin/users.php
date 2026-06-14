<?php
require_once('header.php');

$can_manage_users = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin';

if (!function_exists('admin_user_role_label')) {
    function admin_user_role_label($role)
    {
        if ($role === 'Super Admin') {
            return 'مدير رئيسي';
        }
        if ($role === 'Admin') {
            return 'مدير';
        }
        return 'محرر';
    }
}

if (!function_exists('admin_user_role_class')) {
    function admin_user_role_class($role)
    {
        if ($role === 'Super Admin') {
            return 'role-super';
        }
        if ($role === 'Admin') {
            return 'role-admin';
        }
        return 'role-editor';
    }
}

$error_message = '';
$success_messages = [
    'user_deleted' => 'تم حذف المستخدم بنجاح.'
];
$success_message = isset($_GET['msg'], $success_messages[$_GET['msg']]) ? $success_messages[$_GET['msg']] : '';

$form_username = trim($_POST['username'] ?? '');
$form_full_name = trim($_POST['full_name'] ?? '');
$form_email = trim($_POST['email'] ?? '');
$form_role = trim($_POST['role'] ?? '');

if (!$can_manage_users) {
    ?>
    <style>
    .users-access-page .users-access-box {
        max-width: 760px;
        margin: 30px auto;
        padding: 30px;
        border-top: 3px solid #c0392b;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 14px 30px rgba(23, 39, 56, 0.08);
        text-align: center;
    }

    .users-access-page .users-access-box i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 62px;
        height: 62px;
        margin-bottom: 16px;
        border-radius: 18px;
        background: #fdeceb;
        color: #c0392b;
        font-size: 28px;
    }

    .users-access-page .users-access-box h2 {
        margin: 0 0 10px;
        color: #24313f;
        font-size: 28px;
        font-weight: 700;
    }

    .users-access-page .users-access-box p {
        margin: 0 auto 18px;
        max-width: 560px;
        color: #6d7986;
        line-height: 1.9;
    }

    .users-access-page .users-access-actions {
        display: flex;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .users-access-page .users-access-actions .btn {
        border-radius: 12px;
        padding: 11px 18px;
        font-weight: 700;
    }
    </style>

    <section class="content-header">
        <div class="content-header-left">
            <h1>إدارة المستخدمين</h1>
        </div>
    </section>

    <section class="content users-access-page">
        <div class="users-access-box">
            <i class="fa fa-lock"></i>
            <h2>ليس لديك صلاحية الوصول</h2>
            <p>صفحة إدارة المستخدمين متاحة فقط للحساب الذي يملك صلاحية <code>Super Admin</code>. لهذا السبب لم يكن بالإمكان فتحها من حسابك الحالي.</p>
            <div class="users-access-actions">
                <a href="index.php" class="btn btn-default">
                    <i class="fa fa-arrow-right"></i> العودة إلى الرئيسية
                </a>
                <a href="profile-edit.php" class="btn btn-primary">
                    <i class="fa fa-user"></i> الملف الشخصي
                </a>
            </div>
        </div>
    </section>
    <?php
    require_once('footer.php');
    exit;
}

if (isset($_POST['change_password'])) {
    try {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            throw new Exception('جميع حقول كلمة المرور مطلوبة.');
        }

        $statement = $pdo->prepare('SELECT * FROM tbl_user WHERE id = ? LIMIT 1');
        $statement->execute([$_SESSION['user']['id']]);
        $current_user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$current_user || md5($current_password) !== $current_user['password']) {
            throw new Exception('كلمة المرور الحالية غير صحيحة.');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('كلمتا المرور الجديدتان غير متطابقتين.');
        }

        $statement = $pdo->prepare('UPDATE tbl_user SET password = ? WHERE id = ?');
        $statement->execute([md5($new_password), $_SESSION['user']['id']]);

        $success_message = 'تم تغيير كلمة المرور بنجاح.';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if (isset($_POST['add_user'])) {
    try {
        $username = $form_username;
        $full_name = $form_full_name;
        $email = $form_email;
        $password = trim($_POST['password'] ?? '');
        $role = $form_role;

        if ($username === '' || $full_name === '' || $email === '' || $password === '' || $role === '') {
            throw new Exception('جميع الحقول مطلوبة لإضافة مستخدم جديد.');
        }

        if (!in_array($role, ['Admin', 'Editor'], true)) {
            throw new Exception('الصلاحية المحددة غير صالحة.');
        }

        $statement = $pdo->prepare('SELECT id FROM tbl_user WHERE username = ? OR email = ? LIMIT 1');
        $statement->execute([$username, $email]);
        if ($statement->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('اسم المستخدم أو البريد الإلكتروني موجود مسبقاً.');
        }

        $statement = $pdo->prepare('
            INSERT INTO tbl_user (username, email, password, full_name, role, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $statement->execute([
            $username,
            $email,
            md5($password),
            $full_name,
            $role,
            1
        ]);

        $success_message = 'تم إضافة المستخدم بنجاح.';
        $form_username = '';
        $form_full_name = '';
        $form_email = '';
        $form_role = '';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    if ($delete_id > 0) {
        $statement = $pdo->prepare('SELECT * FROM tbl_user WHERE id = ? LIMIT 1');
        $statement->execute([$delete_id]);
        $target_user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($target_user && $target_user['role'] !== 'Super Admin') {
            $statement = $pdo->prepare('DELETE FROM tbl_user WHERE id = ?');
            $statement->execute([$delete_id]);
        }
    }

    header('location: users.php?msg=user_deleted');
    exit;
}

$statement = $pdo->prepare('SELECT * FROM tbl_user ORDER BY id DESC');
$statement->execute();
$users = $statement->fetchAll(PDO::FETCH_ASSOC);

$total_users = count($users);
$active_users = 0;
$super_admin_count = 0;
$admin_count = 0;
$editor_count = 0;

foreach ($users as $user_row) {
    if ((int) $user_row['status'] === 1) {
        $active_users++;
    }

    if ($user_row['role'] === 'Super Admin') {
        $super_admin_count++;
    } elseif ($user_row['role'] === 'Admin') {
        $admin_count++;
    } else {
        $editor_count++;
    }
}
?>

<style>
.admin-users-page {
    --users-primary: #1f6fb2;
    --users-primary-dark: #165785;
    --users-primary-soft: #eef6fd;
    --users-ink: #24313f;
    --users-muted: #6d7986;
    --users-line: #dde6ee;
    --users-bg: #f5f8fb;
    --users-success: #198754;
    --users-danger: #c0392b;
    --users-warning: #d97706;
}

.users-page-note {
    margin: 8px 0 0;
    color: var(--users-muted);
    font-size: 14px;
}

.admin-users-page .users-panel {
    border-top: 3px solid var(--users-primary);
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(23, 39, 56, 0.08);
    overflow: hidden;
}

.admin-users-page .users-panel .box-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--users-line);
    background: linear-gradient(180deg, #fbfdff 0%, #f4f8fc 100%);
}

.admin-users-page .users-panel .box-body {
    padding: 22px;
}

.admin-users-page .users-metric {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 18px;
    border: 1px solid var(--users-line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(19, 37, 55, 0.06);
}

.admin-users-page .users-metric i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: var(--users-primary-soft);
    color: var(--users-primary);
    font-size: 20px;
}

.admin-users-page .users-metric small {
    display: block;
    color: var(--users-muted);
    font-size: 12px;
    font-weight: 700;
}

.admin-users-page .users-metric strong {
    display: block;
    color: var(--users-ink);
    font-size: 21px;
    line-height: 1.4;
}

.admin-users-page .users-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--users-primary-soft);
    color: var(--users-primary);
    font-size: 12px;
    font-weight: 700;
}

.admin-users-page .users-title {
    margin: 12px 0 8px;
    color: var(--users-ink);
    font-size: 24px;
    font-weight: 700;
}

.admin-users-page .users-text {
    margin: 0;
    color: var(--users-muted);
    line-height: 1.8;
}

.admin-users-page .users-form-grid {
    display: grid;
    gap: 18px;
}

.admin-users-page .users-field label {
    display: block;
    margin-bottom: 8px;
    color: var(--users-ink);
    font-weight: 700;
}

.admin-users-page .users-field small {
    display: block;
    margin-top: 6px;
    color: var(--users-muted);
}

.admin-users-page .form-control {
    height: 48px;
    border: 1px solid #d6dfe7;
    border-radius: 12px;
    box-shadow: none;
    font-size: 14px;
}

.admin-users-page .form-control:focus {
    border-color: var(--users-primary);
    box-shadow: 0 0 0 3px rgba(31, 111, 178, 0.12);
}

.admin-users-page .users-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.admin-users-page .btn {
    padding: 11px 18px;
    border-radius: 12px;
    font-weight: 700;
}

.admin-users-page .btn-primary {
    background: var(--users-primary);
    border-color: var(--users-primary);
}

.admin-users-page .btn-primary:hover,
.admin-users-page .btn-primary:focus {
    background: var(--users-primary-dark);
    border-color: var(--users-primary-dark);
}

.admin-users-page .btn-default {
    border-color: #d7dee6;
    background: #fff;
    color: var(--users-ink);
}

.admin-users-page .btn-danger {
    border-radius: 10px;
}

.admin-users-page .table-wrap {
    border: 1px solid var(--users-line);
    border-radius: 14px;
    overflow: hidden;
}

.admin-users-page .table-wrap .table {
    margin-bottom: 0;
}

.admin-users-page .table-wrap thead th {
    background: #f8fafc;
    color: var(--users-ink);
    border-bottom: 1px solid var(--users-line);
    font-weight: 700;
    white-space: nowrap;
}

.admin-users-page .table-wrap tbody td {
    vertical-align: middle;
}

.admin-users-page .user-role-pill,
.admin-users-page .user-status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    min-width: 92px;
}

.admin-users-page .role-super {
    background: #f4ecff;
    color: #7b3fe4;
}

.admin-users-page .role-admin {
    background: #e8f4fd;
    color: var(--users-primary);
}

.admin-users-page .role-editor {
    background: #fff3e2;
    color: var(--users-warning);
}

.admin-users-page .status-active {
    background: #e8f7ef;
    color: var(--users-success);
}

.admin-users-page .status-inactive {
    background: #fceceb;
    color: var(--users-danger);
}

.admin-users-page .users-side-list {
    display: grid;
    gap: 14px;
}

.admin-users-page .users-side-item {
    padding: 15px;
    border: 1px solid var(--users-line);
    border-radius: 14px;
    background: #fff;
}

.admin-users-page .users-side-item strong {
    display: block;
    margin-bottom: 4px;
    color: var(--users-ink);
}

.admin-users-page .users-side-item span {
    color: var(--users-muted);
    line-height: 1.7;
}

@media (max-width: 767px) {
    .admin-users-page .users-panel .box-header,
    .admin-users-page .users-panel .box-body {
        padding: 16px;
    }

    .admin-users-page .users-actions {
        flex-direction: column;
    }

    .admin-users-page .users-actions .btn {
        width: 100%;
    }
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة المستخدمين</h1>
        <p class="users-page-note">واجهة أوضح لإضافة المستخدمين، تأمين الحساب الإداري، ومراجعة كل الحسابات الحالية.</p>
    </div>
</section>

<section class="content admin-users-page">
    <?php if ($error_message !== ''): ?>
        <div class="callout callout-danger"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($success_message !== ''): ?>
        <div class="callout callout-success"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="users-metric">
                <i class="fa fa-users"></i>
                <div>
                    <small>إجمالي المستخدمين</small>
                    <strong><?= number_format($total_users); ?></strong>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="users-metric">
                <i class="fa fa-check-circle"></i>
                <div>
                    <small>الحسابات النشطة</small>
                    <strong><?= number_format($active_users); ?></strong>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="users-metric">
                <i class="fa fa-shield"></i>
                <div>
                    <small>مديرون</small>
                    <strong><?= number_format($super_admin_count + $admin_count); ?></strong>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="users-metric">
                <i class="fa fa-pencil-square-o"></i>
                <div>
                    <small>محررون</small>
                    <strong><?= number_format($editor_count); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="box users-panel">
                <div class="box-header with-border">
                    <span class="users-badge"><i class="fa fa-user-plus"></i> إضافة مستخدم</span>
                    <h2 class="users-title">إنشاء حساب إداري جديد</h2>
                    <p class="users-text">أضف حساباً جديداً مع الصلاحية المناسبة. سيتم تفعيل الحساب مباشرة بعد الإنشاء.</p>
                </div>
                <div class="box-body">
                    <form action="" method="post" class="users-form-grid">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="users-field">
                                    <label for="username">اسم المستخدم</label>
                                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($form_username, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="users-field">
                                    <label for="full_name">الاسم الكامل</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($form_full_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="users-field">
                                    <label for="email">البريد الإلكتروني</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($form_email, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="users-field">
                                    <label for="password">كلمة المرور</label>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="users-field">
                            <label for="role">الصلاحية</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="">اختر الصلاحية</option>
                                <option value="Admin" <?= $form_role === 'Admin' ? 'selected' : ''; ?>>مدير</option>
                                <option value="Editor" <?= $form_role === 'Editor' ? 'selected' : ''; ?>>محرر</option>
                            </select>
                            <small>استخدم صلاحية المدير للحسابات الإدارية، والمحرر للحسابات التي تعمل على المحتوى فقط.</small>
                        </div>

                        <div class="users-actions">
                            <button type="submit" name="add_user" class="btn btn-primary">
                                <i class="fa fa-plus"></i> إضافة المستخدم
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="box users-panel">
                <div class="box-header with-border">
                    <span class="users-badge"><i class="fa fa-lock"></i> الحماية</span>
                    <h2 class="users-title">تغيير كلمة المرور</h2>
                    <p class="users-text">هذا القسم خاص بحسابك الحالي لتحديث كلمة المرور من نفس صفحة الإدارة.</p>
                </div>
                <div class="box-body">
                    <form action="" method="post" class="users-form-grid">
                        <div class="users-field">
                            <label for="current_password">كلمة المرور الحالية</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>

                        <div class="users-field">
                            <label for="new_password">كلمة المرور الجديدة</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>

                        <div class="users-field">
                            <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="users-actions">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fa fa-save"></i> تحديث كلمة المرور
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="box users-panel">
                <div class="box-header with-border">
                    <h3 class="box-title">ملاحظات الإدارة</h3>
                </div>
                <div class="box-body">
                    <div class="users-side-list">
                        <div class="users-side-item">
                            <strong>الحسابات الحساسة</strong>
                            <span>الحساب الرئيسي يبقى محمياً ولا يظهر له زر الحذف داخل الجدول.</span>
                        </div>
                        <div class="users-side-item">
                            <strong>الصلاحيات</strong>
                            <span>المدير يملك صلاحيات أوسع، بينما المحرر مناسب لإدارة المحتوى فقط.</span>
                        </div>
                        <div class="users-side-item">
                            <strong>أفضل ممارسة</strong>
                            <span>استخدم بريداً إلكترونياً صحيحاً لكل حساب لتسهيل الإدارة والمتابعة لاحقاً.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box users-panel">
        <div class="box-header with-border">
            <h3 class="box-title">قائمة المستخدمين</h3>
        </div>
        <div class="box-body">
            <div class="table-responsive table-wrap">
                <table id="users_table" class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width:70px;">#</th>
                            <th>اسم المستخدم</th>
                            <th>الاسم الكامل</th>
                            <th>البريد الإلكتروني</th>
                            <th style="width:130px;">الصلاحية</th>
                            <th style="width:120px;">الحالة</th>
                            <th style="width:120px;">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $user_row): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= htmlspecialchars($user_row['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($user_row['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($user_row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="user-role-pill <?= admin_user_role_class($user_row['role']); ?>">
                                        <?= htmlspecialchars(admin_user_role_label($user_row['role']), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="user-status-pill <?= ((int) $user_row['status'] === 1) ? 'status-active' : 'status-inactive'; ?>">
                                        <?= ((int) $user_row['status'] === 1) ? 'نشط' : 'معطل'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user_row['role'] !== 'Super Admin'): ?>
                                        <a href="users.php?delete=<?= (int) $user_row['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');">
                                            حذف
                                        </a>
                                    <?php else: ?>
                                        <span class="user-role-pill role-super">محمي</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    $('#users_table').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 10,
        "language": {
            "decimal": "",
            "emptyTable": "لا توجد بيانات متاحة في الجدول",
            "info": "عرض _START_ إلى _END_ من أصل _TOTAL_ مستخدم",
            "infoEmpty": "عرض 0 إلى 0 من أصل 0 مستخدم",
            "infoFiltered": "(تمت التصفية من أصل _MAX_ مستخدم)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "عرض _MENU_ مستخدم",
            "loadingRecords": "جارٍ التحميل...",
            "processing": "جارٍ المعالجة...",
            "search": "بحث:",
            "zeroRecords": "لم يتم العثور على نتائج مطابقة",
            "paginate": {
                "first": "الأول",
                "last": "الأخير",
                "next": "التالي",
                "previous": "السابق"
            },
            "aria": {
                "sortAscending": ": تفعيل الترتيب التصاعدي",
                "sortDescending": ": تفعيل الترتيب التنازلي"
            }
        }
    });
});
</script>

<?php require_once('footer.php'); ?>
