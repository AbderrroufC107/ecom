<?php require_once('header.php'); ?>
<?php
$store_id = $current_store_id;
$store = store_get($pdo, $store_id);
if (!$store) {
    echo '<div class="alert alert-danger">المتجر غير موجود</div>';
    require_once('footer.php');
    exit;
}

$webhooks = store_get_webhooks($pdo, $store_id);
$events_list = store_get_webhook_events_list();
$available_perms = store_get_available_permissions();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_webhook'])) {
        $url = trim((string) ($_POST['wh_url'] ?? ''));
        $secret = trim((string) ($_POST['wh_secret'] ?? ''));
        $events = (array) ($_POST['wh_events'] ?? []);

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'رابط webhook صالح مطلوب.';
        } elseif (empty($events)) {
            $error = 'اختر حدثاً واحداً على الأقل.';
        } else {
            if ($secret === '') $secret = bin2hex(random_bytes(16));
            store_create_webhook($pdo, $store_id, $url, $secret, $events);
            $success = 'تم إنشاء webhook بنجاح.';
            $webhooks = store_get_webhooks($pdo, $store_id);
        }
    }

    if (isset($_POST['delete_webhook'])) {
        $hook_id = (int) ($_POST['hook_id'] ?? 0);
        if ($hook_id > 0 && store_delete_webhook($pdo, $hook_id, $store_id)) {
            $success = 'تم حذف webhook.';
            $webhooks = store_get_webhooks($pdo, $store_id);
        }
    }

    if (isset($_POST['toggle_webhook'])) {
        $hook_id = (int) ($_POST['hook_id'] ?? 0);
        $hook = null;
        foreach ($webhooks as $h) { if ((int)$h['id'] === $hook_id) { $hook = $h; break; } }
        if ($hook) {
            store_update_webhook($pdo, $hook_id, $store_id, ['is_active' => $hook['is_active'] ? 0 : 1]);
            $success = 'تم تغيير حالة webhook.';
            $webhooks = store_get_webhooks($pdo, $store_id);
        }
    }
}

$integrations = [
    'whatsapp' => [
        'name' => 'WhatsApp',
        'icon' => 'fa-whatsapp',
        'color' => '#25D366',
        'desc' => 'أرسل إشعارات الطلبات وتنبيهات الاسترداد عبر WhatsApp. يتطلب مزيل WhatsApp Business API.',
        'status' => 'framework_ready',
        'badge' => 'جاهز للإعداد',
        'badge_class' => 'badge-info',
    ],
    'google_sheets' => [
        'name' => 'Google Sheets',
        'icon' => 'fa-google',
        'color' => '#34A853',
        'desc' => 'صدر الطلبات والعملاء تلقائياً إلى Google Sheets للتتبع والمحاسبة.',
        'status' => 'via_api',
        'badge' => 'عبر API',
        'badge_class' => 'badge-success',
    ],
    'zapier' => [
        'name' => 'Zapier',
        'icon' => 'fa-bolt',
        'color' => '#FF4A00',
        'desc' => 'اربط مع 5000+ تطبيق عبر Zapier. استخدم webhooks API لتشغيل الأتمتة.',
        'status' => 'via_api',
        'badge' => 'عبر Webhook',
        'badge_class' => 'badge-success',
    ],
    'make' => [
        'name' => 'Make (Integromat)',
        'icon' => 'fa-link',
        'color' => '#6B3FA0',
        'desc' => 'أتمتة سير العمل باستخدام Make. استخدم webhooks API وإنشاء سيناريوهات مخصصة.',
        'status' => 'via_api',
        'badge' => 'عبر Webhook',
        'badge_class' => 'badge-success',
    ],
    'slack' => [
        'name' => 'Slack',
        'icon' => 'fa-slack',
        'color' => '#4A154B',
        'desc' => 'احصل على إشعارات الطلبات الجديدة والتحديثات الهامة في قنوات Slack.',
        'status' => 'via_webhook',
        'badge' => 'عبر Webhook',
        'badge_class' => 'badge-success',
    ],
    'discord' => [
        'name' => 'Discord',
        'icon' => 'fa-gamepad',
        'color' => '#5865F2',
        'desc' => 'أرسل إشعارات الطلبات والتقارير إلى قنوات Discord الخاصة بك.',
        'status' => 'via_webhook',
        'badge' => 'عبر Webhook',
        'badge_class' => 'badge-success',
    ],
];
?>

<style>
.int-dash { font-family: 'Cairo', 'Outfit', sans-serif; padding: 24px; direction: rtl; text-align: right; color: #1b2559; }
.int-card { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(112,144,176,0.12); margin-bottom: 24px; }
.int-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.int-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.int-grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
.int-item { background: #f8faff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; transition: all 0.3s; }
.int-item:hover { border-color: #4318ff; box-shadow: 0 12px 30px rgba(67,24,255,0.08); }
.int-item .icon { font-size: 36px; margin-bottom: 12px; }
.int-item h4 { font-size: 18px; font-weight: 800; margin: 0 0 8px; }
.int-item p { font-size: 13px; color: #64748b; margin: 0 0 16px; line-height: 1.6; }
.int-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-info { background: #e8f4ff; color: #1e3a5f; }
.badge-success { background: #e6f9ed; color: #166534; }
.ak-table { width: 100%; border-collapse: collapse; }
.ak-table th { text-align: right; padding: 12px 8px; font-size: 13px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.ak-table td { padding: 12px 8px; font-size: 14px; border-bottom: 1px solid #f4f7fe; }
.ak-table tr:hover td { background: #f8faff; }
.ak-btn { display: inline-block; padding: 8px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; text-decoration: none; border: none; cursor: pointer; }
.ak-btn-primary { background: #4318ff; color: white; }
.ak-btn-danger { background: #f44336; color: white; }
.ak-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.ak-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.ak-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.ak-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.ak-alert-success { background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; }
.ak-alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
.ak-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0; }
.ak-checkbox-group label { display: flex; align-items: center; gap: 4px; font-size: 13px; cursor: pointer; background: #f8faff; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
.ak-badge-perm { background: #f4f0ff; color: #4318ff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin: 1px; display: inline-block; }
</style>

<div class="int-dash">
    <h1 class="int-title">سوق التكاملات</h1>
    <p class="int-subtitle">اربط متجرك بالتطبيقات والخدمات الخارجية</p>

    <?php if ($error): ?><div class="ak-alert ak-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="ak-alert ak-alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <!-- Available Integrations -->
    <div class="int-card">
        <div class="ak-section-head">
            <h3><i class="fa fa-puzzle-piece" style="color: #4318ff; margin-left: 6px;"></i> التكاملات المتاحة</h3>
        </div>
        <div class="int-grid">
            <?php foreach ($integrations as $key => $int): ?>
            <div class="int-item">
                <div class="icon" style="color: <?php echo $int['color']; ?>;"><i class="fa <?php echo $int['icon']; ?>"></i></div>
                <h4><?php echo htmlspecialchars($int['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars($int['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="int-badge <?php echo $int['badge_class']; ?>"><?php echo htmlspecialchars($int['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($int['status'] === 'via_api' || $int['status'] === 'via_webhook'): ?>
                <a href="api-keys.php" class="ak-btn ak-btn-outline" style="float: left; padding: 4px 12px; font-size: 11px;">مفاتيح API</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Webhook Management -->
    <div class="int-card">
        <div class="ak-section-head">
            <h3><i class="fa fa-random" style="color: #ff9800; margin-left: 6px;"></i> Webhooks</h3>
            <span style="font-size: 13px; color: #a3aed1;"><?php echo count($webhooks); ?> مسجل</span>
        </div>

        <?php if (!empty($webhooks)): ?>
        <table class="ak-table">
            <thead>
                <tr><th>الرابط</th><th>الأحداث</th><th>الحالة</th><th>التاريخ</th><th>إجراءات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($webhooks as $wh): ?>
                <?php
                $wh_events = array_filter(explode(',', $wh['events'] ?? ''));
                ?>
                <tr>
                    <td style="font-family: monospace; font-size: 12px; word-break: break-all;"><?php echo htmlspecialchars($wh['url'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php foreach ($wh_events as $e):
                            $elabel = $events_list[$e] ?? $e;
                        ?>
                        <span class="ak-badge-perm"><?php echo htmlspecialchars($elabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <span class="ak-badge <?php echo $wh['is_active'] ? 'ak-badge-active' : 'ak-badge-revoked'; ?>">
                            <?php echo $wh['is_active'] ? 'نشط' : 'موقوف'; ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($wh['created_at'])); ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="hook_id" value="<?php echo (int) $wh['id']; ?>">
                            <button type="submit" name="toggle_webhook" class="ak-btn ak-btn-outline" style="padding: 4px 10px; font-size: 11px;">
                                <i class="fa <?php echo $wh['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                            </button>
                            <button type="submit" name="delete_webhook" class="ak-btn ak-btn-danger" style="padding: 4px 10px; font-size: 11px;" onclick="return confirm('حذف هذا webhook؟')">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <hr style="border-color: #e2e8f0; margin: 16px 0;">
        <?php endif; ?>

        <h4 style="font-size: 16px; font-weight: 800; margin: 0 0 12px;"><i class="fa fa-plus-circle" style="color: #05cd99;"></i> إضافة Webhook جديد</h4>
        <form method="post">
            <div class="row">
                <div class="col-md-5">
                    <label>رابط Webhook</label>
                    <input type="url" name="wh_url" class="form-control" placeholder="https://hooks.example.com/..." required>
                </div>
                <div class="col-md-3">
                    <label>السر (Secret) — يترك فارغاً للتوليد التلقائي</label>
                    <input type="text" name="wh_secret" class="form-control" placeholder="اختياري">
                </div>
                <div class="col-md-12" style="margin-top: 12px;">
                    <label>الأحداث</label>
                    <div class="ak-checkbox-group">
                        <?php foreach ($events_list as $event_key => $event_label): ?>
                        <label>
                            <input type="checkbox" name="wh_events[]" value="<?php echo htmlspecialchars($event_key, ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo htmlspecialchars($event_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-12" style="margin-top: 16px;">
                    <button type="submit" name="create_webhook" class="ak-btn ak-btn-primary"><i class="fa fa-plus"></i> إنشاء Webhook</button>
                    <a href="api-keys.php" class="ak-btn ak-btn-outline"><i class="fa fa-key"></i> إدارة مفاتيح API</a>
                    <a href="../docs/api/" class="ak-btn ak-btn-outline"><i class="fa fa-book"></i> توثيق API</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once('footer.php'); ?>
