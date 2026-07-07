<?php
require_once('header.php');

$can_manage_users = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin';

if (!function_exists('admin_user_role_label')) {
    function admin_user_role_label($role)
    { global $dbRepo;
    global $dbRepo;

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
    { global $dbRepo;
    global $dbRepo;

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

        $statement = $dbRepo->prepare('SELECT * FROM tbl_user WHERE id = ? LIMIT 1');
        $statement->execute([$_SESSION['user']['id']]);
        $current_user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$current_user || md5($current_password) !== $current_user['password']) {
            throw new Exception('كلمة المرور الحالية غير صحيحة.');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('كلمتا المرور الجديدتان غير متطابقتين.');
        }

        $statement = $dbRepo->prepare('UPDATE tbl_user SET password = ? WHERE id = ?');
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

        if ($role !== 'Admin') {
            throw new Exception('الصلاحية المحددة غير صالحة.');
        }

        $statement = $dbRepo->prepare('SELECT id FROM tbl_user WHERE username = ? OR email = ? LIMIT 1');
        $statement->execute([$username, $email]);
        if ($statement->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('اسم المستخدم أو البريد الإلكتروني موجود مسبقاً.');
        }

        $statement = $dbRepo->prepare('
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
        $statement = $dbRepo->prepare('SELECT * FROM tbl_user WHERE id = ? LIMIT 1');
        $statement->execute([$delete_id]);
        $target_user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($target_user && $target_user['role'] !== 'Super Admin') {
            $statement = $dbRepo->prepare('DELETE FROM tbl_user WHERE id = ?');
            $statement->execute([$delete_id]);
        }
    }

    header('location: users.php?msg=user_deleted');
    exit;
}

$statement = $dbRepo->prepare('SELECT * FROM tbl_user ORDER BY id DESC');
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
    --users-primary: #111827;
    --users-primary-dark: #374151;
    --users-primary-soft: #f3f4f6;
    --users-ink: #111827;
    --users-muted: #6b7280;
    --users-line: #e5e7eb;
    --users-bg: #f9fafb;
    --users-success: #059669;
    --users-danger: #dc2626;
    --users-warning: #d97706;
}

.users-page-note {
    margin: 8px 0 0;
    color: var(--users-muted);
    font-size: 14px;
}

.users-layout-container {
    width: 100%;
}

.users-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.admin-users-page .users-panel {
    background: #ffffff;
    border: 1px solid var(--users-line);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
    overflow: hidden;
    margin-bottom: 24px;
}

.admin-users-page .users-panel .box-header {
    padding: 24px 32px;
    border-bottom: 1px solid var(--users-line);
    background: #ffffff;
}

.admin-users-page .users-panel .box-body {
    padding: 32px;
}

.admin-users-page .users-metric {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 20px;
    padding: 24px;
    border: 1px solid var(--users-line);
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    height: 100%;
}

.admin-users-page .users-metric .metric-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--users-primary-soft);
    color: var(--users-primary);
    font-size: 20px;
    flex-shrink: 0;
}

.admin-users-page .users-metric small {
    display: block;
    color: var(--users-muted);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.admin-users-page .users-metric strong {
    display: block;
    color: var(--users-ink);
    font-size: 32px;
    line-height: 1.1;
    font-weight: 700;
}

.admin-users-page .users-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--users-primary-soft);
    color: var(--users-primary);
    font-size: 13px;
    font-weight: 600;
}

.admin-users-page .users-title {
    margin: 16px 0 8px;
    color: var(--users-ink);
    font-size: 20px;
    font-weight: 700;
}

.admin-users-page .users-text {
    margin: 0;
    color: var(--users-muted);
    line-height: 1.6;
    font-size: 14px;
}

.admin-users-page .users-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}

.admin-users-page .form-col {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.admin-users-page .users-form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.admin-users-page .users-field label {
    display: block;
    margin-bottom: 10px;
    color: var(--users-ink);
    font-weight: 600;
    font-size: 14px;
}

.admin-users-page .users-field small {
    display: block;
    margin-top: 6px;
    color: var(--users-muted);
    font-size: 13px;
}

.admin-users-page .form-control {
    height: 48px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    font-size: 15px;
    padding: 0 16px;
    color: #111827;
    transition: all 0.2s;
    width: 100%;
}

.admin-users-page .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
    outline: none;
}

.admin-users-page .users-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--users-line);
}

.admin-users-page .btn {
    height: 48px;
    padding: 0 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.admin-users-page .btn-primary {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.admin-users-page .btn-primary:hover,
.admin-users-page .btn-primary:focus {
    background: #374151;
    border-color: #374151;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
}

.admin-users-page .btn-primary:active {
    transform: translateY(0);
}

.admin-users-page .btn-default {
    border-color: #d1d5db;
    background: transparent;
    color: #374151;
}

.admin-users-page .btn-default:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #111827;
}

.admin-users-page .btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #ffffff;
}

.admin-users-page .btn-danger:hover {
    background: #b91c1c;
    border-color: #b91c1c;
    color: #ffffff;
}

.admin-users-page .table-wrap {
    border: none;
    border-radius: 0;
    overflow-y: auto;
    background: #fff;
    max-height: 600px;
}

.admin-users-page .table-wrap .table {
    margin-bottom: 0;
}

.admin-users-page .table-wrap thead th {
    background: #f9fafb;
    color: var(--users-muted);
    border-bottom: 2px solid var(--users-line);
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    padding: 16px 24px;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.admin-users-page .table-wrap tbody td {
    vertical-align: middle;
    padding: 20px 24px;
    color: var(--users-ink);
    font-size: 14px;
    border-bottom: 1px solid var(--users-line);
}

.admin-users-page .table-hover>tbody>tr:hover {
    background-color: #f3f4f6;
}

.admin-users-page .table-wrap tbody tr:last-child td {
    border-bottom: none;
}

.admin-users-page .user-role-pill,
.admin-users-page .user-status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    min-width: 80px;
}

.admin-users-page .role-super {
    background: #f3f4f6;
    color: #111827;
    border: 1px solid #e5e7eb;
}

.admin-users-page .role-admin {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.admin-users-page .role-editor {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.admin-users-page .status-active {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.admin-users-page .status-inactive {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.admin-users-page .users-side-list {
    display: grid;
    gap: 16px;
}

.admin-users-page .users-side-item {
    padding: 16px;
    border: 1px solid var(--users-line);
    border-radius: 12px;
    background: #f9fafb;
}

.admin-users-page .users-side-item strong {
    display: block;
    margin-bottom: 6px;
    color: var(--users-ink);
    font-weight: 600;
    font-size: 14px;
}

.admin-users-page .users-side-item span {
    display: block;
    color: var(--users-muted);
    line-height: 1.6;
    font-size: 13px;
}

@media (max-width: 767px) {
    .admin-users-page .users-panel .box-header,
    .admin-users-page .users-panel .box-body {
        padding: 20px;
    }
    .admin-users-page .users-form-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    .admin-users-page .users-form-grid-3 {
        grid-template-columns: 1fr;
    }
    .admin-users-page .users-actions {
        flex-direction: column;
    }

    .admin-users-page .users-actions .btn {
        width: 100%;
    }
}
</style>

<section class="content-header" style="padding-top: 20px;">
    <div class="content-header-left">
        <h1>إدارة المستخدمين</h1>
        <p class="users-page-note">واجهة مركزية لإدارة المستخدمين، والصلاحيات، وتأمين الحسابات.</p>
    </div>
</section>

<section class="content admin-users-page">
    <div class="users-layout-container">
        <?php if ($error_message !== ''): ?>
            <div class="callout callout-danger" style="border-radius:8px;"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($success_message !== ''): ?>
            <div class="callout callout-success" style="border-radius:8px;"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="users-metrics-grid">
            <div class="users-metric">
                <div class="metric-icon">
                    <i class="fa fa-users"></i>
                </div>
                <div>
                    <small>إجمالي المستخدمين</small>
                    <strong><?= number_format($total_users); ?></strong>
                </div>
            </div>
            <div class="users-metric">
                <div class="metric-icon" style="color:var(--users-success);background:#ecfdf5;">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div>
                    <small>نشط</small>
                    <strong><?= number_format($active_users); ?></strong>
                </div>
            </div>
            <div class="users-metric">
                <div class="metric-icon" style="color:#1d4ed8;background:#eff6ff;">
                    <i class="fa fa-shield"></i>
                </div>
                <div>
                    <small>مديرون</small>
                    <strong><?= number_format($super_admin_count + $admin_count); ?></strong>
                </div>
            </div>
            <div class="users-metric">
                <div class="metric-icon" style="color:#b45309;background:#fef3c7;">
                    <i class="fa fa-pencil-square-o"></i>
                </div>
                <div>
                    <small>محررون</small>
                    <strong><?= number_format($editor_count); ?></strong>
                </div>
            </div>
        </div>

        <div class="users-panel">
            <div class="box-header with-border">
                <h2 class="users-title" style="font-size: 20px; margin: 0;">إنشاء حساب جديد</h2>
            </div>
            <div class="box-body">
                <form action="" method="post">
                    <div class="users-form-grid">
                        <div class="form-col">
                            <div class="users-field">
                                <label for="full_name">الاسم الكامل</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($form_full_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="users-field">
                                <label for="username">اسم المستخدم</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($form_username, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="users-field">
                                <label for="role">الصلاحية</label>
                                <select name="role" id="role" class="form-control" required>
                                    <option value="">اختر الصلاحية</option>
                                    <option value="Admin" <?= $form_role === 'Admin' ? 'selected' : ''; ?>>مدير</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="users-field">
                                <label for="email">البريد الإلكتروني</label>
                                <input type="email" id="email" name="email" class="form-control" dir="ltr" style="text-align: left;" value="<?= htmlspecialchars($form_email, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="users-field">
                                <label for="password">كلمة المرور</label>
                                <input type="password" id="password" name="password" class="form-control" dir="ltr" style="text-align: left;" required>
                            </div>
                            <div class="users-field">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control" disabled>
                                    <option value="1" selected>نشط (تلقائي)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="users-actions">
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fa fa-user-plus"></i> إضافة المستخدم
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="users-panel">
            <div class="box-header with-border">
                <h3 class="box-title" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--users-ink);">قائمة المستخدمين</h3>
            </div>
            <div class="table-responsive table-wrap" style="border: none; border-radius: 0;">
                <table id="users_table" class="table table-hover" style="margin:0;">
                    <thead>
                        <tr>
                            <th style="width:50px; padding-left: 24px;">#</th>
                            <th>المستخدم</th>
                            <th>الصلاحية</th>
                            <th>الحالة</th>
                            <th style="width:80px; padding-right: 24px;">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $user_row): ?>
                            <tr>
                                <td style="padding-left: 24px;"><?= $index + 1; ?></td>
                                <td>
                                    <strong style="color:var(--users-ink); font-weight:600;"><?= htmlspecialchars($user_row['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <span style="color:var(--users-muted); font-size: 13px;"><?= htmlspecialchars($user_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
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
                                <td style="padding-right: 24px;">
                                    <?php if ($user_row['role'] !== 'Super Admin'): ?>
                                        <a href="users.php?delete=<?= (int) $user_row['id']; ?>" class="btn btn-danger btn-xs" style="height:32px; padding:0 12px; font-size:12px; border-radius:6px;" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');">حذف</a>
                                    <?php else: ?>
                                        <span style="color:#9ca3af; font-size:13px; font-weight:600;"><i class="fa fa-lock"></i> محمي</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="users-panel">
            <div class="box-header with-border">
                <h2 class="users-title" style="font-size: 20px; margin: 0;">تغيير كلمة المرور</h2>
            </div>
            <div class="box-body">
                <form action="" method="post">
                    <div class="users-form-grid-3">
                        <div class="users-field">
                            <label for="current_password">كلمة المرور الحالية</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" dir="ltr" style="text-align: left;" required>
                        </div>
                        <div class="users-field">
                            <label for="new_password">الجديدة</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" dir="ltr" style="text-align: left;" required>
                        </div>
                        <div class="users-field">
                            <label for="confirm_password">تأكيد الجديدة</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" dir="ltr" style="text-align: left;" required>
                        </div>
                    </div>
                    <div class="users-actions">
                        <button type="submit" name="change_password" class="btn btn-default">
                            <i class="fa fa-save"></i> تحديث كلمة المرور
                        </button>
                    </div>
                </form>
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
