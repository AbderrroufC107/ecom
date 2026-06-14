<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");
include("inc/store.php");
include("inc/LoginThrottle.php");
include("inc/audit.php");

store_ensure_tables($pdo);

$throttle = new LoginThrottle($pdo);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if(isset($_POST['form1'])) {
    try {
        if(empty($_POST['email']) || empty($_POST['password'])) {
            throw new Exception("الرجاء إدخال البريد الإلكتروني وكلمة المرور");
        }

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if ($throttle->is_locked_out($ip, $email)) {
            $remaining = $throttle->get_remaining_lockout_time($ip, $email);
            $minutes = ceil($remaining / 60);
            throw new Exception("تم تأمين الحساب مؤقتاً. الرجاء المحاولة بعد {$minutes} دقيقة.");
        }

        // 1. Try existing admin (tbl_user with bcrypt, legacy MD5 fallback)
        $statement = $pdo->prepare("SELECT * FROM tbl_user WHERE email=? AND status=1");
        $statement->execute(array($email));
        $admin_result = $statement->fetchAll(PDO::FETCH_ASSOC);

        if(!empty($admin_result)) {
            $stored_hash = $admin_result[0]['password'];

            if (password_verify($password, $stored_hash)) {
                // bcrypt match — modern flow
            } elseif (strlen($stored_hash) === 32 && md5($password) === $stored_hash) {
                // Legacy MD5 match — rehash to bcrypt and update
                $bcrypt_hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE tbl_user SET password = ? WHERE id = ?");
                $update->execute([$bcrypt_hash, $admin_result[0]['id']]);
                $admin_result[0]['password'] = $bcrypt_hash;
            } else {
                $throttle->record_attempt($ip, $email, $ua, false);
                audit_log_security($pdo, 0, 'login_failed', null, ['email' => $email, 'reason' => 'wrong_password'], 'admin_panel');
                throw new Exception("كلمة المرور غير صحيحة");
            }

            $throttle->clear_attempts($ip, $email);
            audit_log_security($pdo, $admin_result[0]['id'], 'login_success', null, ['email' => $email, 'role' => 'admin'], 'admin_panel');
            $_SESSION['user'] = $admin_result[0];
            session_regenerate_id(true);
            header("location: index.php");
            exit;
        }

        // 2. Try store owner (tbl_store_user with bcrypt)
        $store_user = store_authenticate($pdo, $email, $password);
        if ($store_user) {
            $throttle->clear_attempts($ip, $email);
            audit_log_security($pdo, $store_user['id'], 'login_success', null, ['email' => $email, 'role' => 'store_owner'], 'admin_panel');
            $_SESSION['store_user'] = $store_user;
            $_SESSION['store_id'] = (int) $store_user['store_id'];
            if (!defined('STORE_ID')) {
                define('STORE_ID', (int) $store_user['store_id']);
            }
            session_regenerate_id(true);
            header("location: store-dashboard.php");
            exit;
        }

        $throttle->record_attempt($ip, $email, $ua, false);
        audit_log_security($pdo, 0, 'login_failed', null, ['email' => $email, 'reason' => 'no_account'], 'admin_panel');
        throw new Exception("البريد الإلكتروني أو كلمة المرور غير صحيحة");

    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>تسجيل الدخول - لوحة التحكم</title>
	<!-- Bootstrap 5 CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- Font Awesome -->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
		body {
			background:
				linear-gradient(180deg, rgba(15, 118, 110, 0.08) 0%, rgba(248, 250, 252, 1) 46%, rgba(245, 158, 11, 0.07) 100%),
				#f8fafc;
			min-height: 100vh;
			display: flex;
			align-items: center;
			font-family: "Cairo", "Segoe UI", Tahoma, sans-serif;
		}
		.container {
			position: relative;
			z-index: 1;
		}
		.login-card {
			position: relative;
			overflow: hidden;
			background: rgba(255, 255, 255, 0.94);
			border: 1px solid rgba(226, 232, 240, 0.92);
			border-radius: 18px;
			box-shadow: 0 28px 70px rgba(15, 23, 42, 0.14);
			padding: 2.25rem;
			backdrop-filter: blur(18px);
		}
		.login-card:before {
			content: "MT";
			display: flex;
			align-items: center;
			justify-content: center;
			width: 48px;
			height: 48px;
			margin: 0 auto 1.25rem;
			border-radius: 12px;
			background: linear-gradient(135deg, #0f766e 0%, #14b8a6 55%, #f59e0b 100%);
			color: #ffffff;
			font-weight: 800;
			box-shadow: 0 16px 32px rgba(20, 184, 166, 0.28);
		}
		.login-title {
			color: #0f172a;
			font-size: 2rem;
			font-weight: 800;
			margin-bottom: 0.5rem;
		}
		.login-subtitle {
			color: #64748b;
			margin-bottom: 2rem;
		}
		.form-control {
			min-height: 50px;
			padding: 0.75rem 1rem;
			font-size: 1rem;
			border-color: #dbe3ef;
			border-radius: 0.75rem;
			box-shadow: none;
		}
		.form-control:focus {
			border-color: #14b8a6;
			box-shadow: 0 0 0 0.25rem rgba(20, 184, 166, 0.12);
		}
		.btn-login {
			min-height: 50px;
			padding: 0.75rem;
			font-size: 1rem;
			font-weight: 800;
			border-radius: 0.75rem;
			background-color: #0f766e;
			border-color: #0f766e;
			transition: all 0.3s ease;
			box-shadow: 0 16px 28px rgba(15, 118, 110, 0.2);
		}
		.btn-login:hover {
			background-color: #115e59;
			border-color: #115e59;
			transform: translateY(-2px);
		}
		.alert {
			border-radius: 0.75rem;
		}
		.input-group-text {
			min-width: 50px;
			justify-content: center;
			background-color: #f8fafc;
			border-color: #dbe3ef;
			color: #0f766e;
			border-radius: 0.75rem 0 0 0.75rem;
		}
		.input-group .form-control {
			border-radius: 0 0.75rem 0.75rem 0;
		}
		@media (max-width: 575px) {
			.login-card {
				padding: 1.5rem;
				border-radius: 14px;
			}
		}
	</style>
</head>

<body>
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-5 col-md-7">
				<div class="login-card">
					<div class="text-center mb-4">
						<h1 class="login-title">مرحباً بك</h1>
						<p class="text-muted">قم بتسجيل الدخول للوصول إلى لوحة التحكم</p>
					</div>

					<?php if(isset($error_message)): ?>
					<div class="alert alert-danger text-center mb-4">
						<i class="fas fa-exclamation-circle me-2"></i>
						<?php echo $error_message; ?>
					</div>
					<?php endif; ?>

					<form action="" method="post">
						<div class="mb-4">
							<div class="input-group">
								<span class="input-group-text">
									<i class="fas fa-envelope"></i>
								</span>
								<input type="email" class="form-control" name="email" 
									placeholder="البريد الإلكتروني" required>
							</div>
						</div>
						<div class="mb-4">
							<div class="input-group">
								<span class="input-group-text">
									<i class="fas fa-lock"></i>
								</span>
								<input type="password" class="form-control" name="password"
									placeholder="كلمة المرور" required>
							</div>
						</div>
						<button type="submit" name="form1" class="btn btn-primary btn-login w-100">
							تسجيل الدخول
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Bootstrap 5 JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
