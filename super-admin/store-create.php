<?php
session_start();
if (!isset($_SESSION['super_admin'])) {
    header('location: login.php');
    exit;
}
require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/store.php';
store_ensure_tables($pdo);
store_migrate_all_tables($pdo);

define('SUPER_ADMIN', true);

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'store_name' => trim((string) ($_POST['store_name'] ?? '')),
        'store_slug' => trim((string) ($_POST['store_slug'] ?? '')),
        'store_domain' => trim((string) ($_POST['store_domain'] ?? '')),
        'owner_name' => trim((string) ($_POST['owner_name'] ?? '')),
        'owner_email' => trim((string) ($_POST['owner_email'] ?? '')),
        'owner_password' => $_POST['owner_password'] ?? '',
        'plan_type' => trim((string) ($_POST['plan_type'] ?? 'starter')),
        'monthly_price' => (float) ($_POST['monthly_price'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 1,
    ];

    if ($data['store_name'] === '') {
        $error = 'اسم المتجر مطلوب.';
    } elseif ($data['store_slug'] === '') {
        $data['store_slug'] = store_generate_slug($data['store_name']);
    } elseif ($data['owner_email'] !== '' && $data['owner_password'] === '') {
        $error = 'كلمة مرور المالك مطلوبة عند إدخال البريد.';
    } else {
        $existing = store_get_by_slug($pdo, $data['store_slug']);
        if ($existing) {
            $error = 'الرابط المختصر مستخدم بالفعل.';
        } else {
            $result = store_create($pdo, $data);
            if ($result['store_id'] > 0) {
                $success = 'تم إنشاء المتجر بنجاح!';
            } else {
                $error = 'فشل في إنشاء المتجر.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Super Admin - إضافة متجر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../admin/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; }
        .sa-header { background: linear-gradient(135deg, #1e293b, #0f172a); border-bottom: 1px solid #334155; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .sa-header h1 { margin: 0; font-size: 20px; font-weight: 800; color: #f8fafc; }
        .sa-header a { color: #94a3b8; text-decoration: none; margin-right: 16px; }
        .sa-container { max-width: 700px; margin: 24px auto; padding: 0 24px; }
        .form-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 12px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.2); }
        label { font-size: 13px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; display: block; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-default { background: #334155; color: #e2e8f0; text-decoration: none; display: inline-block; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-danger { background: #7f1d1d; color: #fecaca; }
        .alert-success { background: #166534; color: #bbf7d0; }
    </style>
</head>
<body>
    <div class="sa-header">
        <h1><i class="fa fa-plus-circle"></i> إضافة متجر جديد</h1>
        <div><a href="index.php"><i class="fa fa-arrow-right"></i> العودة</a></div>
    </div>
    <div class="sa-container">
        <div class="form-card">
            <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>اسم المتجر *</label>
                            <input type="text" name="store_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>الرابط المختصر</label>
                            <input type="text" name="store_slug" class="form-control" placeholder="سيتم توليده تلقائياً">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>النطاق (Domain)</label>
                    <input type="text" name="store_domain" class="form-control" placeholder="store.example.com">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>اسم المالك</label>
                            <input type="text" name="owner_name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>بريد المالك</label>
                            <input type="email" name="owner_email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>كلمة مرور المالك</label>
                    <input type="text" name="owner_password" class="form-control" placeholder="اتركه فارغاً إذا لم تضف بريداً">
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>الخطة</label>
                            <select name="plan_type" class="form-control">
                                <option value="starter">Starter</option>
                                <option value="professional">Professional</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>السعر الشهري</label>
                            <input type="number" name="monthly_price" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div style="padding-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;color:#e2e8f0;">
                                    <input type="checkbox" name="is_active" value="1" checked> نشط
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> إنشاء المتجر</button>
                    <a href="index.php" class="btn btn-default"><i class="fa fa-times"></i> إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

