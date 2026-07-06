<?php
require_once __DIR__ . '/../admin/inc/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['staff_employee_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../admin/inc/employee_functions.php';
require_once __DIR__ . '/../admin/inc/LoginThrottle.php';
require_once __DIR__ . '/../admin/inc/audit.php';

$error = '';
$throttle = new LoginThrottle($pdo);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        if ($throttle->is_locked_out($ip, $email)) {
            $remaining = $throttle->get_remaining_lockout_time($ip, $email);
            $minutes = ceil($remaining / 60);
            $error = "تم تأمين الحساب مؤقتاً. الرجاء المحاولة بعد {$minutes} دقيقة.";
        } else {
            $employee = employee_find_by_email($pdo, $email);
            if ($employee && !empty($employee['is_active']) && password_verify($password, $employee['password_hash'])) {
                $throttle->clear_attempts($ip, $email);
                audit_log_security($pdo, $employee['id'], 'login_success', null, ['email' => $email, 'role' => 'staff'], 'staff_portal');
                session_regenerate_id(true);
                $_SESSION['staff_employee_id'] = (int) $employee['id'];
                $_SESSION['staff_employee_name'] = $employee['full_name'];
                $_SESSION['staff_employee_email'] = $employee['email'];
                header('Location: index.php');
                exit;
            }
            $throttle->record_attempt($ip, $email, $ua, false);
            audit_log_security($pdo, 0, 'login_failed', null, ['email' => $email, 'reason' => 'wrong_credentials'], 'staff_portal');
            $error = 'بريد إلكتروني أو كلمة مرور غير صحيحة.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوابة الموظفين | متجر الثقة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f766e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }

        .login-card {
            background: #fff;
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
        }

        .login-card h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 6px;
            color: #1e293b;
        }

        .login-card p {
            color: #64748b;
            margin: 0 0 28px;
            font-size: 14px;
        }

        .login-card .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 20px;
            font-weight: 700;
            color: #0f766e;
        }

        .login-card .brand i { font-size: 28px; }

        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            font-size: 15px;
        }

        .form-control:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
        }

        .btn-login {
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
            font-size: 16px;
            background: #0f766e;
            border: none;
            color: #fff;
            width: 100%;
        }

        .btn-login:hover { background: #115e59; }

        .alert { border-radius: 12px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <i class="bi bi-shop"></i>
            متجر الثقة
        </div>
        <h1>بوابة الموظفين</h1>
        <p>تسجيل الدخول للاطلاع على طلباتك وإنجازاتك</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label fw-semibold">البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" required autofocus
                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-login">دخول</button>
        </form>
    </div>
</body>
</html>
