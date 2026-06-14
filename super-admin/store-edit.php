<?php
session_start();
if (!isset($_SESSION['super_admin'])) {
    header('location: login.php');
    exit;
}
require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/store.php';
store_ensure_tables($pdo);

define('SUPER_ADMIN', true);

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$store_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($store_id <= 0) {
    header('location: index.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && $store_id > 1) {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        store_delete($pdo, $store_id);
    }
    header('location: index.php');
    exit;
}

$store = store_get($pdo, $store_id);
if (!$store) {
    header('location: index.php');
    exit;
}

$users = store_get_store_users($pdo, $store_id);
$subscription = store_get_subscription($pdo, $store_id);
$stats = store_get_stats($pdo, $store_id);
$theme = store_get_theme($pdo, $store_id);
$invoices = store_get_invoices($pdo, $store_id);
$usage_summary = store_get_usage_summary($pdo, $store_id);
$sub_status = store_get_subscription_status($pdo, $store_id);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_store'])) {
        $update = [
            'store_name' => trim((string) ($_POST['store_name'] ?? '')),
            'store_slug' => trim((string) ($_POST['store_slug'] ?? '')),
            'store_domain' => trim((string) ($_POST['store_domain'] ?? '')),
            'owner_name' => trim((string) ($_POST['owner_name'] ?? '')),
            'owner_email' => trim((string) ($_POST['owner_email'] ?? '')),
            'plan_type' => trim((string) ($_POST['plan_type'] ?? 'starter')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        store_update($pdo, $store_id, $update);
        $success = 'تم تحديث المتجر بنجاح.';
        $store = store_get($pdo, $store_id);
    }

    if (isset($_POST['add_user'])) {
        $name = trim((string) ($_POST['user_name'] ?? ''));
        $email = trim((string) ($_POST['user_email'] ?? ''));
        $password = $_POST['user_password'] ?? '';
        $role = trim((string) ($_POST['user_role'] ?? 'owner'));
        if ($name !== '' && $email !== '' && $password !== '') {
            store_create_user($pdo, $store_id, $name, $email, $password, $role);
            $success = 'تم إضافة المستخدم.';
            $users = store_get_store_users($pdo, $store_id);
        } else {
            $error = 'جميع الحقول مطلوبة.';
        }
    }

    if (isset($_POST['save_theme'])) {
        store_save_theme($pdo, $store_id, [
            'primary_color' => $_POST['primary_color'] ?? '#2563eb',
            'secondary_color' => $_POST['secondary_color'] ?? '#0f172a',
            'logo' => $_POST['logo'] ?? '',
            'favicon' => $_POST['favicon'] ?? '',
        ]);
        $success = 'تم حفظ التصميم.';
        $theme = store_get_theme($pdo, $store_id);
    }

    if (isset($_POST['update_subscription'])) {
        $expires = !empty($_POST['sub_expires']) ? $_POST['sub_expires'] : null;
        $stmt = $pdo->prepare("UPDATE tbl_store_subscription SET plan_name = ?, monthly_price = ?, status = ?, expires_at = ? WHERE id = ?");
        $stmt->execute([
            $_POST['sub_plan'] ?? 'starter',
            (float) ($_POST['sub_price'] ?? 0),
            $_POST['sub_status'] ?? 'active',
            $expires,
            (int) ($_POST['sub_id'] ?? 0),
        ]);
        $success = 'تم تحديث الاشتراك.';
        $subscription = store_get_subscription($pdo, $store_id);
    }

    if (isset($_POST['create_invoice'])) {
        $amount = (float) ($_POST['inv_amount'] ?? 0);
        $notes = trim((string) ($_POST['inv_notes'] ?? ''));
        if ($amount > 0) {
            store_create_invoice($pdo, $store_id, $amount, 'DZD', $notes);
            $success = 'تم إنشاء الفاتورة.';
            $invoices = store_get_invoices($pdo, $store_id);
        } else {
            $error = 'المبلغ يجب أن يكون أكبر من صفر.';
        }
    }

    if (isset($_POST['mark_paid'])) {
        $invoice_id = (int) ($_POST['inv_id'] ?? 0);
        $amount = (float) ($_POST['pay_amount'] ?? 0);
        $method = trim((string) ($_POST['pay_method'] ?? 'manual'));
        $reference = trim((string) ($_POST['pay_reference'] ?? ''));
        if ($invoice_id > 0 && $amount > 0) {
            store_record_payment($pdo, $invoice_id, $amount, $method, $reference);
            // Audit
            if (function_exists('audit_log')) {
                audit_log($pdo, [
                    'entity_type' => 'store',
                    'entity_id' => $store_id,
                    'action_type' => 'payment_recorded',
                    'performed_by_type' => 'admin_panel',
                    'performed_by_id' => 0,
                    'new_value' => "فاتورة #{$invoice_id} - {$amount} دج - {$method}",
                    'source' => 'admin_panel',
                ]);
            }
            $success = 'تم تسجيل الدفعة.';
            $invoices = store_get_invoices($pdo, $store_id);
        } else {
            $error = 'بيانات الدفع غير صحيحة.';
        }
    }

    if (isset($_POST['change_plan_sa'])) {
        $new_plan = trim((string) ($_POST['sa_plan'] ?? ''));
        $valid = ['starter', 'professional', 'enterprise'];
        if (in_array($new_plan, $valid, true)) {
            store_change_plan($pdo, $store_id, $new_plan);
            $success = "تم تغيير الخطة إلى {$new_plan}.";
            $store = store_get($pdo, $store_id);
            $subscription = store_get_subscription($pdo, $store_id);
        } else {
            $error = 'خطة غير صالحة.';
        }
    }

    if (isset($_POST['toggle_suspend'])) {
        $new_active = isset($_POST['suspend_store']) ? 0 : 1;
        store_update($pdo, $store_id, ['is_active' => $new_active]);
        $action_label = $new_active ? 'تم إلغاء الإيقاف' : 'تم إيقاف المتجر';
        $success = $action_label;
        if (function_exists('audit_log')) {
            audit_log($pdo, [
                'entity_type' => 'store',
                'entity_id' => $store_id,
                'action_type' => $new_active ? 'store_activated' : 'store_suspended',
                'performed_by_type' => 'admin_panel',
                'performed_by_id' => 0,
                'old_value' => $new_active ? 'suspended' : 'active',
                'new_value' => $new_active ? 'active' : 'suspended',
                'source' => 'admin_panel',
            ]);
        }
        $store = store_get($pdo, $store_id);
        $sub_status = store_get_subscription_status($pdo, $store_id);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Super Admin - تعديل المتجر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../admin/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; }
        .sa-header { background: linear-gradient(135deg, #1e293b, #0f172a); border-bottom: 1px solid #334155; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .sa-header h1 { margin: 0; font-size: 20px; font-weight: 800; color: #f8fafc; }
        .sa-header a { color: #94a3b8; text-decoration: none; margin-right: 16px; }
        .sa-container { max-width: 1000px; margin: 24px auto; padding: 0 24px; }
        .form-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
        .form-card h4 { margin: 0 0 16px; font-size: 16px; font-weight: 800; color: #f8fafc; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 10px; }
        .form-control:focus { border-color: #2563eb; }
        label { font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 4px; display: block; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-default { background: #334155; color: #e2e8f0; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-danger { background: #7f1d1d; color: #fecaca; }
        .alert-success { background: #166534; color: #bbf7d0; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat-box { background: #0f172a; border-radius: 8px; padding: 14px; text-align: center; }
        .stat-box .num { font-size: 22px; font-weight: 800; color: #f8fafc; }
        .stat-box .lbl { font-size: 11px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; color: #94a3b8; font-size: 11px; font-weight: 700; padding: 8px 12px; text-align: right; }
        td { padding: 8px 12px; border-bottom: 1px solid #334155; font-size: 13px; }
    </style>
</head>
<body>
    <div class="sa-header">
        <h1><i class="fa fa-pencil"></i> <?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <div><a href="index.php"><i class="fa fa-arrow-right"></i> العودة</a></div>
    </div>
    <div class="sa-container">
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

        <div class="stats-grid">
            <div class="stat-box"><div class="num"><?php echo number_format($stats['total_orders']); ?></div><div class="lbl">الطلبات</div></div>
            <div class="stat-box"><div class="num"><?php echo number_format($stats['total_employees']); ?></div><div class="lbl">الموظفين</div></div>
            <div class="stat-box"><div class="num"><?php echo number_format($stats['total_products']); ?></div><div class="lbl">المنتجات</div></div>
            <div class="stat-box"><div class="num"><?php echo number_format($stats['total_revenue'], 2); ?></div><div class="lbl">الإيرادات</div></div>
            <div class="stat-box"><div class="num"><?php echo number_format($stats['pending_orders']); ?></div><div class="lbl">قيد الانتظار</div></div>
        </div>

        <div class="form-card">
            <h4><i class="fa fa-cog"></i> معلومات المتجر</h4>
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>اسم المتجر</label>
                            <input type="text" name="store_name" class="form-control" value="<?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>الرابط المختصر</label>
                            <input type="text" name="store_slug" class="form-control" value="<?php echo htmlspecialchars($store['store_slug'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>النطاق</label>
                    <input type="text" name="store_domain" class="form-control" value="<?php echo htmlspecialchars($store['store_domain'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>اسم المالك</label>
                            <input type="text" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($store['owner_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>البريد</label>
                            <input type="email" name="owner_email" class="form-control" value="<?php echo htmlspecialchars($store['owner_email'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>الخطة</label>
                            <select name="plan_type" class="form-control">
                                <option value="starter" <?php echo $store['plan_type'] === 'starter' ? 'selected' : ''; ?>>Starter</option>
                                <option value="professional" <?php echo $store['plan_type'] === 'professional' ? 'selected' : ''; ?>>Professional</option>
                                <option value="enterprise" <?php echo $store['plan_type'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <label style="display:flex;align-items:center;gap:6px;padding-top:6px;color:#e2e8f0;">
                                <input type="checkbox" name="is_active" value="1" <?php echo $store['is_active'] ? 'checked' : ''; ?>> نشط
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="update_store" class="btn btn-primary"><i class="fa fa-save"></i> حفظ</button>
            </form>
        </div>

        <div class="form-card">
            <h4><i class="fa fa-users"></i> مستخدمي المتجر</h4>
            <table>
                <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr><td><?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo $u['is_active'] ? 'نشط' : 'موقوف'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <hr style="border-color:#334155;margin:16px 0;">
            <form method="post" class="row">
                <div class="col-md-3"><input type="text" name="user_name" class="form-control" placeholder="الاسم"></div>
                <div class="col-md-3"><input type="email" name="user_email" class="form-control" placeholder="البريد"></div>
                <div class="col-md-2"><input type="text" name="user_password" class="form-control" placeholder="كلمة المرور"></div>
                <div class="col-md-2">
                    <select name="user_role" class="form-control">
                        <option value="owner">مالك</option>
                        <option value="admin">مدير</option>
                        <option value="viewer">مشاهد</option>
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" name="add_user" class="btn btn-success btn-block"><i class="fa fa-plus"></i> إضافة</button></div>
            </form>
        </div>

        <div class="form-card">
            <h4><i class="fa fa-paint-brush"></i> التصميم (Theme)</h4>
            <form method="post" class="row">
                <div class="col-md-3">
                    <label>اللون الأساسي</label>
                    <input type="color" name="primary_color" class="form-control" value="<?php echo htmlspecialchars($theme['primary_color'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label>اللون الثانوي</label>
                    <input type="color" name="secondary_color" class="form-control" value="<?php echo htmlspecialchars($theme['secondary_color'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label>الشعار (URL)</label>
                    <input type="text" name="logo" class="form-control" value="<?php echo htmlspecialchars($theme['logo'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label>الأيقونة (URL)</label>
                    <input type="text" name="favicon" class="form-control" value="<?php echo htmlspecialchars($theme['favicon'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-12" style="margin-top:12px;">
                    <button type="submit" name="save_theme" class="btn btn-primary"><i class="fa fa-save"></i> حفظ التصميم</button>
                </div>
            </form>
        </div>

        <div class="form-card">
            <h4><i class="fa fa-credit-card"></i> الاشتراك</h4>
            <?php if ($subscription): ?>
            <form method="post" class="row">
                <input type="hidden" name="sub_id" value="<?php echo (int) $subscription['id']; ?>">
                <div class="col-md-2">
                    <label>الخطة</label>
                    <select name="sub_plan" class="form-control">
                        <option value="starter" <?php echo $subscription['plan_name'] === 'starter' ? 'selected' : ''; ?>>Starter</option>
                        <option value="professional" <?php echo $subscription['plan_name'] === 'professional' ? 'selected' : ''; ?>>Professional</option>
                        <option value="enterprise" <?php echo $subscription['plan_name'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>السعر</label>
                    <input type="number" name="sub_price" class="form-control" step="0.01" value="<?php echo htmlspecialchars((string) $subscription['monthly_price'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label>الحالة</label>
                    <select name="sub_status" class="form-control">
                        <option value="active" <?php echo $subscription['status'] === 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="trial" <?php echo $subscription['status'] === 'trial' ? 'selected' : ''; ?>>تجريبي</option>
                        <option value="expired" <?php echo $subscription['status'] === 'expired' ? 'selected' : ''; ?>>منتهي</option>
                        <option value="cancelled" <?php echo $subscription['status'] === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" name="sub_expires" class="form-control" value="<?php echo $subscription['expires_at'] ? date('Y-m-d', strtotime($subscription['expires_at'])) : ''; ?>">
                </div>
                <div class="col-md-2" style="padding-top:18px;">
                    <button type="submit" name="update_subscription" class="btn btn-primary"><i class="fa fa-save"></i> حفظ</button>
                </div>
            </form>
            <hr style="border-color:#334155;margin:16px 0;">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="color:#94a3b8;font-size:13px;">حالة الاشتراك: </span>
                <span class="badge <?php echo $sub_status['read_only'] ? 'badge-danger' : 'badge-success'; ?>">
                    <?php echo $sub_status['read_only'] ? 'مقيد (قراءة فقط)' : 'نشط'; ?>
                </span>
                <span style="color:#94a3b8;font-size:13px;margin-right:12px;"><?php echo htmlspecialchars($sub_status['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php else: ?>
            <p class="text-muted">لا يوجد اشتراك مسجل.</p>
            <?php endif; ?>
        </div>

        <!-- Billing: Quick Plan Change & Suspend -->
        <div class="form-card">
            <h4><i class="fa fa-exchange"></i> تغيير الخطة (مع تسجيل التدقيق)</h4>
            <div class="row" style="display:flex;gap:12px;flex-wrap:wrap;">
                <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                    <div>
                        <label style="font-size:12px;color:#94a3b8;">الخطة الجديدة</label>
                        <select name="sa_plan" class="form-control" style="width:auto;">
                            <option value="starter" <?php echo $store['plan_type'] === 'starter' ? 'selected' : ''; ?>>Starter</option>
                            <option value="professional" <?php echo $store['plan_type'] === 'professional' ? 'selected' : ''; ?>>Professional</option>
                            <option value="enterprise" <?php echo $store['plan_type'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                    <button type="submit" name="change_plan_sa" class="btn btn-warning"><i class="fa fa-arrows-h"></i> تغيير الخطة</button>
                </form>
                <form method="post" style="display:inline;">
                    <button type="submit" name="toggle_suspend" class="btn btn-danger" onclick="return confirm('<?php echo $store['is_active'] ? 'إيقاف' : 'إعادة تفعيل'; ?> هذا المتجر؟')">
                        <i class="fa <?php echo $store['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                        <?php echo $store['is_active'] ? 'إيقاف المتجر' : 'إعادة التفعيل'; ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Billing: Usage Stats -->
        <div class="form-card">
            <h4><i class="fa fa-bar-chart"></i> إحصائيات الاستخدام (الشهر الحالي)</h4>
            <div class="stats-grid">
                <?php foreach ($usage_summary['metrics'] as $key => $m): ?>
                <div class="stat-box">
                    <div class="num"><?php echo $m['current']; ?> <?php echo $m['unlimited'] ? '' : '/ ' . $m['max']; ?></div>
                    <div class="lbl"><?php echo htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!$m['unlimited']): ?>
                        <span style="font-size:10px;color:<?php echo $m['percent'] >= 90 ? '#f44336' : ($m['percent'] >= 80 ? '#ff9800' : '#64748b'); ?>;">
                            (<?php echo $m['percent']; ?>%)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="stat-box">
                    <div class="num"><?php echo number_format($usage_summary['usage']['api_calls'] ?? 0); ?></div>
                    <div class="lbl">استدعاءات API</div>
                </div>
                <div class="stat-box">
                    <div class="num"><?php echo number_format($usage_summary['usage']['ai_reports'] ?? 0); ?></div>
                    <div class="lbl">تقارير AI</div>
                </div>
            </div>
        </div>

        <!-- Billing: Invoices -->
        <div class="form-card">
            <h4><i class="fa fa-file-text-o"></i> الفواتير (<?php echo count($invoices); ?>)</h4>
            <?php if (!empty($invoices)): ?>
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>المبلغ</th>
                        <th>المدفوع</th>
                        <th>الحالة</th>
                        <th>تاريخ الإصدار</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format($inv['amount'], 2); ?> دج</td>
                        <td><?php echo number_format($inv['paid_amount'] ?? 0, 2); ?> دج</td>
                        <td><span class="badge <?php echo $inv['status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $inv['status'] === 'paid' ? 'مدفوعة' : 'معلقة'; ?></span></td>
                        <td><?php echo $inv['issued_at'] ? date('Y-m-d', strtotime($inv['issued_at'])) : '-'; ?></td>
                        <td>
                            <?php if ($inv['status'] !== 'paid'): ?>
                            <form method="post" style="display:flex;gap:4px;flex-wrap:wrap;">
                                <input type="hidden" name="inv_id" value="<?php echo (int) $inv['id']; ?>">
                                <input type="number" name="pay_amount" class="form-control" style="width:80px;padding:4px;font-size:12px;" placeholder="المبلغ" step="0.01" required>
                                <input type="text" name="pay_method" class="form-control" style="width:80px;padding:4px;font-size:12px;" placeholder="الوسيلة" value="manual">
                                <input type="text" name="pay_reference" class="form-control" style="width:100px;padding:4px;font-size:12px;" placeholder="المرجع">
                                <button type="submit" name="mark_paid" class="btn btn-success" style="padding:4px 10px;font-size:12px;"><i class="fa fa-check"></i> دفع</button>
                            </form>
                            <?php else: ?>
                            <span style="color:#16a34a;">مدفوعة</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">لا توجد فواتير بعد.</p>
            <?php endif; ?>
            <hr style="border-color:#334155;margin:16px 0;">
            <h5 style="color:#f8fafc;">إنشاء فاتورة جديدة</h5>
            <form method="post" class="row">
                <div class="col-md-3">
                    <input type="number" name="inv_amount" class="form-control" step="0.01" placeholder="المبلغ" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="inv_notes" class="form-control" placeholder="ملاحظات">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="create_invoice" class="btn btn-primary"><i class="fa fa-plus"></i> إنشاء فاتورة</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

