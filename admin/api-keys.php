<?php require_once('header.php'); ?>
<?php
$store_id = $current_store_id;
$store = store_get($pdo, $store_id);
if (!$store) {
    echo '<div class="alert alert-danger">المتجر غير موجود</div>';
    require_once('footer.php');
    exit;
}

$error = '';
$success = '';
$show_key = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_key'])) {
        $name = trim((string) ($_POST['key_name'] ?? ''));
        $permissions = (array) ($_POST['permissions'] ?? []);
        $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if ($name === '') {
            $error = 'اسم المفتاح مطلوب.';
        } else {
            $result = store_create_api_key($pdo, $store_id, $name, $permissions, $expires);
            $show_key = $result['api_key'];
            $success = 'تم إنشاء المفتاح بنجاح. انسخه الآن — لن تتمكن من رؤيته مرة أخرى.';
            if (function_exists('audit_log')) {
                audit_log($pdo, [
                    'entity_type' => 'api_key',
                    'entity_id' => $result['id'],
                    'action_type' => 'api_key_created',
                    'source' => 'admin_panel',
                ]);
            }
        }
    }

    if (isset($_POST['revoke_key'])) {
        $key_id = (int) ($_POST['key_id'] ?? 0);
        if ($key_id > 0 && store_revoke_api_key($pdo, $key_id, $store_id)) {
            $success = 'تم إلغاء المفتاح.';
            if (function_exists('audit_log')) {
                audit_log($pdo, [
                    'entity_type' => 'api_key',
                    'entity_id' => $key_id,
                    'action_type' => 'api_key_revoked',
                    'source' => 'admin_panel',
                ]);
            }
        } else {
            $error = 'فشل إلغاء المفتاح.';
        }
    }

    if (isset($_POST['rotate_key'])) {
        $key_id = (int) ($_POST['key_id'] ?? 0);
        $new_key = store_rotate_api_key($pdo, $key_id, $store_id);
        if ($new_key) {
            $show_key = $new_key;
            $success = 'تم تدوير المفتاح. انسخ المفتاح الجديد الآن.';
        } else {
            $error = 'فشل تدوير المفتاح.';
        }
    }
}

$keys = store_get_api_keys($pdo, $store_id);
$usage_stats = store_get_api_usage_stats($pdo, $store_id);
$rate_limit = store_get_rate_limit($store['plan_type'] ?? 'starter');
$all_permissions = store_get_available_permissions();
$logs = store_get_api_logs($pdo, $store_id, 20);

function ak_format_date($val) {
    return $val ? date('Y-m-d H:i', strtotime($val)) : '-';
}
?>

<style>
.ak-dash { font-family: 'Cairo', 'Outfit', sans-serif; padding: 24px; direction: rtl; text-align: right; color: #1b2559; }
.ak-card { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(112,144,176,0.12); margin-bottom: 24px; }
.ak-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.ak-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.ak-grid { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.ak-stat { padding: 20px; background: #f8faff; border-radius: 16px; text-align: center; }
.ak-stat h3 { font-size: 28px; font-weight: 800; margin: 0; color: #4318ff; }
.ak-stat p { font-size: 13px; color: #a3aed1; margin: 4px 0 0; }
.ak-table { width: 100%; border-collapse: collapse; }
.ak-table th { text-align: right; padding: 12px 8px; font-size: 13px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.ak-table td { padding: 12px 8px; font-size: 14px; border-bottom: 1px solid #f4f7fe; vertical-align: middle; }
.ak-table tr:hover td { background: #f8faff; }
.ak-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
.ak-badge-active { background: #e6f9ed; color: #05cd99; }
.ak-badge-revoked { background: #ffebee; color: #f44336; }
.ak-badge-perm { background: #f4f0ff; color: #4318ff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin: 1px; display: inline-block; }
.ak-key-display { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 14px; word-break: break-all; margin: 12px 0; }
.ak-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0; }
.ak-checkbox-group label { display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer; background: #f8faff; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
.ak-checkbox-group input:checked + span { color: #4318ff; font-weight: 700; }
.ak-btn { display: inline-block; padding: 8px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; text-decoration: none; border: none; cursor: pointer; }
.ak-btn-primary { background: #4318ff; color: white; }
.ak-btn-danger { background: #f44336; color: white; }
.ak-btn-warning { background: #ff9800; color: white; }
.ak-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.ak-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.ak-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.ak-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.ak-alert-success { background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; }
.ak-alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
</style>

<div class="ak-dash">
    <h1 class="ak-title">مفاتيح API</h1>
    <p class="ak-subtitle">إدارة مفاتيح API للمتجر — الحد: <?php echo htmlspecialchars($rate_limit['label'], ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($error): ?><div class="ak-alert ak-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="ak-alert ak-alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <?php if ($show_key): ?>
    <div class="ak-card" style="border: 2px solid #05cd99;">
        <h3 style="color: #05cd99; margin: 0 0 8px;"><i class="fa fa-key"></i> مفتاح API جديد</h3>
        <p style="color: #a3aed1; font-size: 13px;">انسخ هذا المفتاح الآن — لن تتمكن من رؤيته مرة أخرى.</p>
        <div class="ak-key-display"><?php echo htmlspecialchars($show_key, ENT_QUOTES, 'UTF-8'); ?></div>
        <button class="ak-btn ak-btn-primary" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($show_key, ENT_QUOTES, 'UTF-8'); ?>')"><i class="fa fa-copy"></i> نسخ</button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="ak-grid" style="margin-bottom: 24px;">
        <div class="ak-stat">
            <h3><?php echo count($keys); ?></h3>
            <p>إجمالي المفاتيح</p>
        </div>
        <div class="ak-stat">
            <h3><?php echo $usage_stats['today']; ?></h3>
            <p>طلبات اليوم</p>
        </div>
        <div class="ak-stat">
            <h3><?php echo $usage_stats['this_month']; ?></h3>
            <p>طلبات هذا الشهر</p>
        </div>
        <div class="ak-stat">
            <h3><?php echo $rate_limit['max_daily'] >= 999999 ? '∞' : $rate_limit['max_daily']; ?></h3>
            <p>الحد اليومي</p>
        </div>
    </div>

    <!-- Create Key -->
    <div class="ak-card">
        <div class="ak-section-head">
            <h3><i class="fa fa-plus-circle" style="color: #4318ff;"></i> إنشاء مفتاح جديد</h3>
        </div>
        <form method="post">
            <div class="row">
                <div class="col-md-4">
                    <label>اسم المفتاح</label>
                    <input type="text" name="key_name" class="form-control" placeholder="مثال: Zapier Integration" required>
                </div>
                <div class="col-md-4">
                    <label>تاريخ الانتهاء (اختياري)</label>
                    <input type="date" name="expires_at" class="form-control">
                </div>
            </div>
            <div style="margin-top: 12px;">
                <label>الصلاحيات</label>
                <div class="ak-checkbox-group">
                    <?php foreach ($all_permissions as $perm_key => $perm_label): ?>
                    <label>
                        <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($perm_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $perm_key === '*' ? '' : ''; ?>>
                        <span><?php echo htmlspecialchars($perm_label, ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" name="create_key" class="ak-btn ak-btn-primary" style="margin-top: 16px;"><i class="fa fa-key"></i> إنشاء مفتاح</button>
        </form>
    </div>

    <!-- Keys List -->
    <div class="ak-card">
        <div class="ak-section-head">
            <h3><i class="fa fa-list"></i> المفاتيح الحالية</h3>
        </div>
        <?php if (empty($keys)): ?>
        <p style="color: #a3aed1; text-align: center; padding: 24px;">لا توجد مفاتيح API بعد. قم بإنشاء أول مفتاح أعلاه.</p>
        <?php else: ?>
        <table class="ak-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>المفتاح</th>
                    <th>الصلاحيات</th>
                    <th>آخر استخدام</th>
                    <th>الانتهاء</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $k): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($k['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td style="font-family: monospace; font-size: 12px; direction: ltr; text-align: left;">
                        <?php echo htmlspecialchars(substr($k['api_key'], 0, 16) . '...' . substr($k['api_key'], -8), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td>
                        <?php
                        $perms = array_filter(explode(',', $k['permissions'] ?? ''));
                        foreach ($perms as $p):
                            $plabel = $all_permissions[$p] ?? $p;
                        ?>
                        <span class="ak-badge-perm"><?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?php echo ak_format_date($k['last_used_at']); ?></td>
                    <td><?php echo ak_format_date($k['expires_at']); ?></td>
                    <td>
                        <span class="ak-badge <?php echo $k['is_active'] ? 'ak-badge-active' : 'ak-badge-revoked'; ?>">
                            <?php echo $k['is_active'] ? 'نشط' : 'ملغي'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($k['is_active']): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                            <button type="submit" name="rotate_key" class="ak-btn ak-btn-warning" style="padding: 4px 10px; font-size: 11px;" onclick="return confirm('تدوير هذا المفتاح؟ المفتاح القديم لن يعمل بعد الآن.')"><i class="fa fa-refresh"></i></button>
                            <button type="submit" name="revoke_key" class="ak-btn ak-btn-danger" style="padding: 4px 10px; font-size: 11px;" onclick="return confirm('إلغاء هذا المفتاح؟')"><i class="fa fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Recent API Logs -->
    <div class="ak-card">
        <div class="ak-section-head">
            <h3><i class="fa fa-history"></i> آخر طلبات API</h3>
        </div>
        <?php if (empty($logs)): ?>
        <p style="color: #a3aed1; text-align: center; padding: 24px;">لا توجد طلبات API مسجلة بعد.</p>
        <?php else: ?>
        <table class="ak-table">
            <thead>
                <tr><th>الوقت</th><th>المسار</th><th>الطريقة</th><th>الرمز</th><th>المفتاح</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                    <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($log['endpoint'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="ak-badge" style="background: #e8f4ff; color: #2196f3;"><?php echo htmlspecialchars($log['request_method'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><span class="ak-badge" style="background: <?php echo $log['response_code'] < 300 ? '#e6f9ed' : '#ffebee'; ?>; color: <?php echo $log['response_code'] < 300 ? '#05cd99' : '#f44336'; ?>;"><?php echo (int) $log['response_code']; ?></span></td>
                    <td style="font-size: 12px;"><?php echo htmlspecialchars($log['key_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once('footer.php'); ?>
