<?php
require_once('header.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');

site_security_ensure_tables($pdo);

$error_message = '';
$success_message = '';

$status_labels = [
    'warning' => 'تحذير',
    'review' => 'مراجعة يدوية',
    'high_risk' => 'عالي الخطورة',
    'deposit_required' => 'عربون مطلوب',
    'banned' => 'منع كامل'
];

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $statement = $dbRepo->prepare("DELETE FROM site_security_blacklist WHERE id = ?");
        $statement->execute([$id]);
        $success_message = 'تم حذف قاعدة المخاطر.';
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id > 0) {
        $statement = $dbRepo->prepare("UPDATE site_security_blacklist SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?");
        $statement->execute([$id]);
        $success_message = 'تم تحديث حالة القاعدة.';
    }
}

$editing_rule = null;
if (isset($_GET['edit'])) {
    $statement = $dbRepo->prepare("SELECT * FROM site_security_blacklist WHERE id = ? LIMIT 1");
    $statement->execute([(int)$_GET['edit']]);
    $editing_rule = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (isset($_POST['save_security_rule'])) {
    $rule_id = (int)($_POST['rule_id'] ?? 0);
    $phone = trim((string)($_POST['phone'] ?? ''));
    $customer_name = trim((string)($_POST['customer_name'] ?? ''));
    $wilaya = trim((string)($_POST['wilaya'] ?? ''));
    $commune = trim((string)($_POST['commune'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $ip_address = trim((string)($_POST['ip_address'] ?? ''));
    $device_id = trim((string)($_POST['device_id'] ?? ''));
    $status = (string)($_POST['status'] ?? 'banned');
    $notes = trim((string)($_POST['notes'] ?? ''));
    $rejected_count = max(0, (int)($_POST['rejected_orders_count'] ?? 0));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!isset($status_labels[$status])) {
        $status = 'banned';
    }

    $normalized_phone = site_security_normalize_phone($phone);
    $normalized_name = site_security_normalize_text($customer_name);
    $normalized_address = site_security_normalize_address($address);

    $has_signal = ($normalized_phone !== '' || $ip_address !== '' || $device_id !== '' || $normalized_address !== '' || ($normalized_name !== '' && ($wilaya !== '' || $commune !== '')));

    if (!$has_signal) {
        $error_message = 'يجب إدخال إشارة واحدة على الأقل (رقم الهاتف، العنوان، IP، بصمة الجهاز، أو الاسم مع الولاية/البلدية).';
    } else {
        if ($rule_id > 0) {
            $statement = $dbRepo->prepare("
                UPDATE site_security_blacklist
                SET phone = ?, normalized_phone = ?, 
                    customer_name = ?, normalized_name = ?,
                    wilaya = ?, commune = ?, 
                    address = ?, normalized_address = ?,
                    ip_address = ?, device_id = ?,
                    status = ?, notes = ?,
                    rejected_orders_count = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $statement->execute([
                $phone !== '' ? $phone : null,
                $normalized_phone !== '' ? $normalized_phone : null,
                $customer_name !== '' ? $customer_name : null,
                $normalized_name !== '' ? $normalized_name : null,
                $wilaya !== '' ? $wilaya : null,
                $commune !== '' ? $commune : null,
                $address !== '' ? $address : null,
                $normalized_address !== '' ? $normalized_address : null,
                $ip_address !== '' ? $ip_address : null,
                $device_id !== '' ? $device_id : null,
                $status, $notes, $rejected_count, $is_active, $rule_id
            ]);
            $success_message = 'تم تحديث قاعدة المخاطر.';
            $editing_rule = null;
        } else {
            $existing_id = 0;
            if ($normalized_phone !== '') {
                $existing_statement = $dbRepo->prepare("SELECT id FROM site_security_blacklist WHERE normalized_phone = ? ORDER BY is_active DESC, id DESC LIMIT 1");
                $existing_statement->execute([$normalized_phone]);
                $existing_id = (int)($existing_statement->fetchColumn() ?: 0);
            }

            if ($existing_id > 0) {
                $statement = $dbRepo->prepare("
                    UPDATE site_security_blacklist
                    SET phone = ?, normalized_phone = ?, 
                        customer_name = ?, normalized_name = ?,
                        wilaya = ?, commune = ?, 
                        address = ?, normalized_address = ?,
                        ip_address = ?, device_id = ?,
                        status = ?, notes = ?,
                        rejected_orders_count = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $statement->execute([
                    $phone !== '' ? $phone : null,
                    $normalized_phone !== '' ? $normalized_phone : null,
                    $customer_name !== '' ? $customer_name : null,
                    $normalized_name !== '' ? $normalized_name : null,
                    $wilaya !== '' ? $wilaya : null,
                    $commune !== '' ? $commune : null,
                    $address !== '' ? $address : null,
                    $normalized_address !== '' ? $normalized_address : null,
                    $ip_address !== '' ? $ip_address : null,
                    $device_id !== '' ? $device_id : null,
                    $status, $notes, $rejected_count, $is_active, $existing_id
                ]);
            } else {
                $statement = $dbRepo->prepare("
                    INSERT INTO site_security_blacklist
                    (phone, normalized_phone, customer_name, normalized_name, wilaya, commune, address, normalized_address, ip_address, device_id, status, notes, rejected_orders_count, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $statement->execute([
                    $phone !== '' ? $phone : null,
                    $normalized_phone !== '' ? $normalized_phone : null,
                    $customer_name !== '' ? $customer_name : null,
                    $normalized_name !== '' ? $normalized_name : null,
                    $wilaya !== '' ? $wilaya : null,
                    $commune !== '' ? $commune : null,
                    $address !== '' ? $address : null,
                    $normalized_address !== '' ? $normalized_address : null,
                    $ip_address !== '' ? $ip_address : null,
                    $device_id !== '' ? $device_id : null,
                    $status, $notes, $rejected_count, $is_active
                ]);
            }
            $success_message = 'تمت إضافة قاعدة المخاطر.';
        }
    }
}

$stats = [
    'total' => 0,
    'active' => 0,
    'banned' => 0,
    'deposit' => 0
];
try {
    $stats['total'] = (int)$dbRepo->query("SELECT COUNT(*) FROM site_security_blacklist")->fetchColumn();
    $stats['active'] = (int)$dbRepo->query("SELECT COUNT(*) FROM site_security_blacklist WHERE is_active = 1")->fetchColumn();
    $stats['banned'] = (int)$dbRepo->query("SELECT COUNT(*) FROM site_security_blacklist WHERE is_active = 1 AND status = 'banned'")->fetchColumn();
    $stats['deposit'] = (int)$dbRepo->query("SELECT COUNT(*) FROM site_security_blacklist WHERE is_active = 1 AND status IN ('deposit_required', 'high_risk')")->fetchColumn();
} catch (Exception $e) {
}

$statement = $dbRepo->query("SELECT * FROM site_security_blacklist ORDER BY is_active DESC, FIELD(status, 'banned', 'deposit_required', 'high_risk', 'review', 'warning') ASC, rejected_orders_count DESC, created_at DESC");
$rules = $statement->fetchAll(PDO::FETCH_ASSOC);
$form = $editing_rule ?: [
    'id' => 0,
    'phone' => '',
    'customer_name' => '',
    'wilaya' => '',
    'commune' => '',
    'address' => '',
    'ip_address' => '',
    'device_id' => '',
    'status' => 'banned',
    'notes' => '',
    'rejected_orders_count' => 0,
    'is_active' => 1
];
?>

<style>
.security-page{direction:rtl;padding-bottom:34px;color:#172033}
.security-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;background:linear-gradient(135deg,#102033,#0f9488);color:#fff;border-radius:18px;padding:24px;margin-bottom:18px;box-shadow:0 18px 44px rgba(15,23,42,.18)}
.security-hero h2{margin:0 0 8px;font-size:28px;font-weight:950}
.security-hero p{margin:0;color:#d9f5f2;line-height:1.8}
.security-hero p{display:none}
.security-stats{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;margin-bottom:18px}
.security-stat{background:#fff;border:1px solid #d8e0ec;border-radius:14px;padding:15px;box-shadow:0 10px 26px rgba(23,32,51,.06)}
.security-stat span{display:block;color:#667085;font-weight:850}
.security-stat strong{display:block;margin-top:6px;color:#172033;font-size:26px;font-weight:950}
.security-grid{display:grid;grid-template-columns:minmax(340px,430px) minmax(0,1fr);gap:18px}
.security-card{background:#fff;border:1px solid #d8e0ec;border-radius:16px;box-shadow:0 14px 34px rgba(23,32,51,.07);overflow:hidden}
.security-card-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:16px 18px;border-bottom:1px solid #d8e0ec;background:#f4f7fb;font-weight:950}
.security-card-body{padding:18px}
.security-field{margin-bottom:13px}
.security-field label{display:block;margin-bottom:7px;color:#172033;font-weight:950}
.security-field input,.security-field textarea,.security-field select{width:100%;border:1.5px solid #b7c5d6;border-radius:11px;padding:10px 12px;color:#0f172a;background:#fff;font-weight:850}
.security-field textarea{min-height:86px;resize:vertical}
.manual-security-note{margin:0 0 14px;color:#475569;font-weight:850;line-height:1.8}
.security-two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.security-save{height:44px;border:0;border-radius:12px;background:#0f9488;color:#fff;font-weight:950;padding:0 18px}
.security-reset{height:44px;display:inline-flex;align-items:center;border:1px solid #d8e0ec;border-radius:12px;background:#fff;color:#172033;font-weight:950;padding:0 18px;margin-right:8px}
.security-table{width:100%;border-collapse:separate;border-spacing:0 8px}
.security-table th{background:#e8f0fa;color:#172033;padding:11px;font-weight:950;white-space:nowrap}
.security-table td{background:#fff;border-top:1px solid #d8e0ec;border-bottom:1px solid #d8e0ec;padding:12px;vertical-align:top;color:#172033;font-weight:750}
.security-table td:first-child{border-right:1px solid #d8e0ec;border-radius:0 12px 12px 0}
.security-table td:last-child{border-left:1px solid #d8e0ec;border-radius:12px 0 0 12px}
.security-badge{display:inline-flex;border-radius:999px;padding:6px 10px;font-weight:950;font-size:12px;white-space:nowrap}
.security-badge.warning{background:#fff7ed;color:#9a3412}
.security-badge.review{background:#eff6ff;color:#1d4ed8}
.security-badge.high_risk,.security-badge.deposit_required{background:#fef3c7;color:#92400e}
.security-badge.banned{background:#fee2e2;color:#991b1b}
.security-badge.off{background:#f1f5f9;color:#475569}
.security-signal{display:grid;gap:5px;min-width:210px}
.security-signal small{color:#667085;font-weight:800}
.security-actions{display:flex;gap:6px;flex-wrap:wrap}
.security-actions .btn{border-radius:8px;font-weight:850}
@media(max-width:1100px){.security-grid,.security-hero{grid-template-columns:1fr}.security-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.security-two,.security-stats{grid-template-columns:1fr}}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>أمان الموقع</h1>
    </div>
</section>

<section class="content security-page">
    <?php if ($error_message): ?><div class="callout callout-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success_message): ?><div class="callout callout-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="security-hero">
        <div>
            <h2>قائمة مخاطر العملاء</h2>
            <p>لا تعتمد على رقم الهاتف فقط. هذه الصفحة تفحص الهاتف، IP، بصمة الجهاز، العنوان، والاسم مع الولاية/المدينة. ويمكن تصعيد العميل من تحذير إلى عالي الخطورة ثم منع كامل حسب عدد المحاولات المرفوضة.</p>
        </div>
        <i class="fa fa-shield" style="font-size:54px;color:#f2a516"></i>
    </div>

    <div class="security-stats">
        <div class="security-stat"><span>كل القواعد</span><strong><?php echo (int)$stats['total']; ?></strong></div>
        <div class="security-stat"><span>النشطة</span><strong><?php echo (int)$stats['active']; ?></strong></div>
        <div class="security-stat"><span>منع كامل</span><strong><?php echo (int)$stats['banned']; ?></strong></div>
        <div class="security-stat"><span>عربون / عالي الخطورة</span><strong><?php echo (int)$stats['deposit']; ?></strong></div>
    </div>

    <div class="security-grid">
        <div class="security-card">
            <div class="security-card-head">
                <span><i class="fa fa-plus-circle"></i> <?php echo !empty($form['id']) ? 'تعديل قاعدة' : 'إضافة قاعدة مخاطر'; ?></span>
                <?php if (!empty($form['id'])): ?><a href="site-security.php" class="btn btn-default btn-xs">إلغاء التعديل</a><?php endif; ?>
            </div>
            <div class="security-card-body">
                <form method="post">
                    <input type="hidden" name="rule_id" value="<?php echo (int)($form['id'] ?? 0); ?>">
                    <p class="manual-security-note">يمكنك إضافة أو تعديل إشارات المخاطر يدوياً (رقم الهاتف، العنوان، IP، بصمة الجهاز، أو الاسم مع الولاية والبلدية).</p>
                    <div class="security-two">
                        <div class="security-field">
                            <label>رقم الهاتف</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars((string)($form['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="0555123456">
                        </div>
                        <div class="security-field">
                            <label>الاسم</label>
                            <input type="text" name="customer_name" value="<?php echo htmlspecialchars((string)($form['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="اسم العميل">
                        </div>
                    </div>
                    <div class="security-two">
                        <div class="security-field">
                            <label>الولاية</label>
                            <input type="text" name="wilaya" value="<?php echo htmlspecialchars((string)($form['wilaya'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="الولاية">
                        </div>
                        <div class="security-field">
                            <label>المدينة / البلدية</label>
                            <input type="text" name="commune" value="<?php echo htmlspecialchars((string)($form['commune'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="المدينة">
                        </div>
                    </div>
                    <div class="security-field">
                        <label>العنوان</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars((string)($form['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="العنوان الذي تكرر منه الرفض">
                    </div>
                    <div class="security-two">
                        <div class="security-field">
                            <label>IP</label>
                            <input type="text" name="ip_address" value="<?php echo htmlspecialchars((string)($form['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="41.x.x.x">
                        </div>
                        <div class="security-field">
                            <label>Device ID / Fingerprint</label>
                            <input type="text" name="device_id" value="<?php echo htmlspecialchars((string)($form['device_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="abc123">
                        </div>
                    </div>
                    <div class="security-two">
                        <div class="security-field">
                            <label>مستوى الإجراء</label>
                            <select name="status">
                                <?php foreach ($status_labels as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($form['status'] ?? 'warning') === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="security-field">
                            <label>عدد الطلبات المرفوضة</label>
                            <input type="number" min="0" name="rejected_orders_count" value="<?php echo (int)($form['rejected_orders_count'] ?? 0); ?>">
                        </div>
                    </div>
                    <div class="security-field">
                        <label>ملاحظات داخلية</label>
                        <textarea name="notes" placeholder="مثال: رفض COD مرتين، لا يتم الشحن إلا بعربون"><?php echo htmlspecialchars((string)($form['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <label style="display:flex;gap:8px;align-items:center;margin-bottom:14px;font-weight:900">
                        <input type="checkbox" name="is_active" <?php echo ((int)($form['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>> القاعدة نشطة
                    </label>
                    <button class="security-save" type="submit" name="save_security_rule">
                        <i class="fa fa-save"></i> حفظ القاعدة
                    </button>
                    <?php if (!empty($form['id'])): ?><a class="security-reset" href="site-security.php">قاعدة جديدة</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div class="security-card">
            <div class="security-card-head">
                <span><i class="fa fa-list"></i> قواعد المخاطر</span>
                <span style="color:#667085;font-size:13px">الهاتف + IP + الجهاز + العنوان</span>
            </div>
            <div class="security-card-body">
                <div class="table-responsive">
                    <table class="security-table">
                        <thead>
                            <tr>
                                <th>الإشارات</th>
                                <th>الحالة</th>
                                <th>الرفض</th>
                                <th>الملاحظات</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rules): ?>
                                <tr><td colspan="5" style="text-align:center;color:#667085">لا توجد قواعد مخاطر حاليا.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rules as $rule): ?>
                                <tr>
                                    <td>
                                        <div class="security-signal">
                                            <?php if (!empty($rule['phone'])): ?><span><b>هاتف:</b> <?php echo htmlspecialchars($rule['phone'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                            <?php if (!empty($rule['customer_name'])): ?><span><b>اسم:</b> <?php echo htmlspecialchars($rule['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                            <?php if (!empty($rule['wilaya']) || !empty($rule['commune'])): ?><small><?php echo htmlspecialchars(trim(($rule['wilaya'] ?? '') . ' / ' . ($rule['commune'] ?? ''), ' /'), ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                            <?php if (!empty($rule['address'])): ?><small>عنوان: <?php echo htmlspecialchars($rule['address'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                            <?php if (!empty($rule['ip_address'])): ?><small>IP: <?php echo htmlspecialchars($rule['ip_address'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                            <?php if (!empty($rule['device_id'])): ?><small>Device: <?php echo htmlspecialchars($rule['device_id'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ((int)$rule['is_active'] !== 1): ?>
                                            <span class="security-badge off">متوقفة</span>
                                        <?php else: ?>
                                            <span class="security-badge <?php echo htmlspecialchars((string)$rule['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_labels[$rule['status']] ?? $rule['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$rule['rejected_orders_count']; ?></td>
                                    <td><?php echo htmlspecialchars((string)($rule['notes'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="security-actions">
                                            <a class="btn btn-info btn-xs" href="site-security.php?edit=<?php echo (int)$rule['id']; ?>">تعديل</a>
                                            <a class="btn btn-default btn-xs" href="site-security.php?toggle=<?php echo (int)$rule['id']; ?>"><?php echo ((int)$rule['is_active'] === 1) ? 'إيقاف' : 'تفعيل'; ?></a>
                                            <a class="btn btn-danger btn-xs" href="site-security.php?delete=<?php echo (int)$rule['id']; ?>" onclick="return confirm('حذف قاعدة المخاطر؟');">حذف</a>
                                        </div>
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
