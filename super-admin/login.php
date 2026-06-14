<?php
session_start();
if (isset($_SESSION['super_admin'])) {
    header('location: index.php');
    exit;
}

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/LoginThrottle.php';
require_once __DIR__ . '/../admin/inc/audit.php';

$error = '';
$throttle = new LoginThrottle($pdo);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    if ($throttle->is_locked_out($ip, $username)) {
        $remaining = $throttle->get_remaining_lockout_time($ip, $username);
        $minutes = ceil($remaining / 60);
        $error = "تم تأمين الحساب مؤقتاً. الرجاء المحاولة بعد {$minutes} دقيقة.";
    } else {
        $admin_user = defined('SUPER_ADMIN_USER') ? SUPER_ADMIN_USER : 'admin';
        $admin_pass = defined('SUPER_ADMIN_PASS') ? SUPER_ADMIN_PASS : '';

        $hashed = defined('SUPER_ADMIN_HASH') ? SUPER_ADMIN_HASH : '';

        if ($admin_pass !== '' && $admin_pass === 'SuperAdmin123!' && $username === $admin_user) {
            if ($hashed === '') {
                $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
            }
        }

        if ($hashed !== '' && $username === $admin_user && password_verify($password, $hashed)) {
            $throttle->clear_attempts($ip, $username);
            audit_log_security($pdo, 0, 'login_success', null, ['username' => $username, 'role' => 'super_admin'], 'admin_panel');
            session_regenerate_id(true);
            $_SESSION['super_admin'] = true;
            $_SESSION['super_admin_user'] = $username;
            header('location: index.php');
            exit;
        }

        $throttle->record_attempt($ip, $username, $ua, false);
        audit_log_security($pdo, 0, 'login_failed', null, ['username' => $username, 'reason' => 'wrong_credentials'], 'admin_panel');
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Super Admin - تسجيل الدخول</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../admin/css/bootstrap.min.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-box { width: 380px; background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; }
        .login-box h1 { font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 24px; color: #f8fafc; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 12px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.2); }
        .btn { width: 100%; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 15px; }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .error { background: #7f1d1d; color: #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 16px; text-align: center; font-size: 13px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1><i class="fa fa-shield"></i> Super Admin</h1>
        <?php if ($error !== ''): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>اسم المستخدم</label>
                <input type="text" name="username" class="form-control" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-sign-in"></i> دخول</button>
        </form>
    </div>
</body>
</html>
