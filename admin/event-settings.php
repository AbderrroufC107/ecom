<?php
require_once('header.php');
require_once('inc/employee_functions.php');
if (file_exists('inc/telegram_bot.php')) { require_once('inc/telegram_bot.php'); }
require_once('inc/telegram_actions.php');

telegram_ensure_tables($pdo);

$error_message = '';
$success_message = '';

$config_keys = [
    'event_bot_enabled' => 'تفعيل بوت المراقبة',
    'event_bot_chat_id' => 'معرّف شات البوت (معرّف مجموعة/قناة/مدير)',
    'event_unprocessed_order_enabled' => 'تفعيل مراقبة الطلبات غير المعالجة',
    'event_unprocessed_order_minutes' => 'وقت الانتظار قبل التنبيه (دقيقة)',
    'event_employee_inactivity_enabled' => 'تفعيل مراقبة خمول الموظفين',
    'event_employee_inactivity_minutes' => 'وقت الخمول قبل التنبيه (دقيقة)',
    'event_ecotrack_status_enabled' => 'تفعيل مراقبة تحديثات ECOTRACK',
    'event_delivered_enabled' => 'تفعيل تنبيهات التسليم',
    'event_returned_enabled' => 'تفعيل تنبيهات المرتجع',
    'event_high_cancellation_enabled' => 'تفعيل مراقبة معدل الإلغاء',
    'event_cancellation_threshold' => 'نسبة الإلغاء القصوى (%)',
    'event_cancellation_hours' => 'فترة احتساب الإلغاء (ساعة)',
    'event_failed_telegram_enabled' => 'تفعيل مراقبة فشل إرسال التلغرام',
    'event_failed_telegram_attempts' => 'عدد المحاولات الفاشلة قبل التنبيه',
    'event_unassigned_orders_enabled' => 'تفعيل مراقبة الطلبات غير الموزعة',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        foreach ($config_keys as $key => $label) {
            $value = trim((string) ($_POST[$key] ?? ''));
            $stmt = $dbRepo->prepare("UPDATE tbl_event_settings SET config_value = ? WHERE config_key = ?");
            $stmt->execute([$value, $key]);
            if ($stmt->rowCount() === 0) {
                $dbRepo->prepare("INSERT IGNORE INTO tbl_event_settings (config_key, config_value) VALUES (?, ?)")->execute([$key, $value]);
            }
        }
        $success_message = 'تم حفظ الإعدادات بنجاح.';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$current = [];
$stmt = $dbRepo->query("SELECT config_key, config_value FROM tbl_event_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $current[$row['config_key']] = $row['config_value'];
}
?>
<style>
.ews-wrap { direction: rtl; text-align: right; font-family: 'Cairo', sans-serif; }
.ews-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px; margin-bottom: 18px; }
.ews-card h4 { margin: 0 0 16px; font-weight: 800; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
.ews-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; gap: 12px; flex-wrap: wrap; }
.ews-row:last-child { border-bottom: none; }
.ews-label { font-weight: 700; color: #334155; min-width: 200px; }
.ews-input { width: 200px; }
.ews-input-sm { width: 100px; }
.ews-switch { width: 50px; height: 26px; }
.ews-note { font-size: 12px; color: #94a3b8; margin-top: 2px; }
</style>
<section class="content ews-wrap">
    <div class="emp-hero">
        <div>
            <h3><i class="fa fa-bell"></i> إعدادات بوت المراقبة</h3>
            <p>تحكم في إعدادات التنبيهات والمراقبة التلقائية.</p>
        </div>
    </div>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success_message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php
    $last_run = $current['last_event_monitor_run'] ?? '';
    $last_run_display = $last_run !== '' ? date('d/m/Y H:i:s', strtotime($last_run)) : 'لم يتم التشغيل بعد';
    ?>
    <div class="callout callout-info">
        <strong>آخر تشغيل للمراقب:</strong> <?php echo $last_run_display; ?>
        &nbsp;|&nbsp;
        <a href="scripts/event-monitor.php?key=<?php echo defined('EVENT_MONITOR_SECRET') ? EVENT_MONITOR_SECRET : ''; ?>" class="btn btn-default btn-xs" target="_blank"><i class="fa fa-play"></i> تشغيل يدوي</a>
    </div>

    <form method="post">
        <div class="ews-card">
            <h4>الإعدادات العامة</h4>
            <?php ews_render_row($current, 'event_bot_enabled', 'checkbox', 'تفعيل بوت المراقبة', 'عطل/شغل النظام بأكمله'); ?>
            <?php ews_render_row($current, 'event_bot_chat_id', 'text', 'معرّف شات البوت', 'أدخل معرّف المجموعة أو القناة أو المدير', '300px'); ?>
        </div>

        <div class="ews-card">
            <h4>الطلبات غير المعالجة</h4>
            <?php ews_render_row($current, 'event_unprocessed_order_enabled', 'checkbox', 'تفعيل', 'تنبيه عند بقاء طلب معلق لمدة محددة'); ?>
            <?php ews_render_row($current, 'event_unprocessed_order_minutes', 'number', 'المهلة (دقيقة)', 'بعد كم دقيقة من عدم المعالجة يتم التنبيه'); ?>
        </div>

        <div class="ews-card">
            <h4>خمول الموظفين</h4>
            <?php ews_render_row($current, 'event_employee_inactivity_enabled', 'checkbox', 'تفعيل', 'تنبيه عند عدم قيام الموظف بأي إجراء'); ?>
            <?php ews_render_row($current, 'event_employee_inactivity_minutes', 'number', 'المهلة (دقيقة)', 'بعد كم دقيقة من الخمول'); ?>
        </div>

        <div class="ews-card">
            <h4>تحديثات ECOTRACK</h4>
            <?php ews_render_row($current, 'event_ecotrack_status_enabled', 'checkbox', 'تفعيل مراقبة الحالة', 'تنبيه عند تغير حالة الشحنة'); ?>
            <?php ews_render_row($current, 'event_delivered_enabled', 'checkbox', 'تفعيل تنبيه التسليم', 'تنبيه عند اكتمال التسليم'); ?>
            <?php ews_render_row($current, 'event_returned_enabled', 'checkbox', 'تفعيل تنبيه المرتجع', 'تنبيه عند إرجاع الشحنة'); ?>
        </div>

        <div class="ews-card">
            <h4>معدل الإلغاء المرتفع</h4>
            <?php ews_render_row($current, 'event_high_cancellation_enabled', 'checkbox', 'تفعيل', 'تنبيه عند ارتفاع نسبة إلغاء موظف'); ?>
            <?php ews_render_row($current, 'event_cancellation_threshold', 'number', 'الحد الأقصى (%)', 'النسبة المئوية القصوى المسموح بها'); ?>
            <?php ews_render_row($current, 'event_cancellation_hours', 'number', 'فترة الاحتساب (ساعة)', 'عدد الساعات الماضية لحساب النسبة'); ?>
        </div>

        <div class="ews-card">
            <h4>فشل إرسال التلغرام</h4>
            <?php ews_render_row($current, 'event_failed_telegram_enabled', 'checkbox', 'تفعيل', 'تنبيه عند فشل إرسال إشعار لموظف'); ?>
            <?php ews_render_row($current, 'event_failed_telegram_attempts', 'number', 'عدد المحاولات', 'عدد المحاولات الفاشلة قبل التنبيه'); ?>
        </div>

        <div class="ews-card">
            <h4>الطلبات غير الموزعة</h4>
            <?php ews_render_row($current, 'event_unassigned_orders_enabled', 'checkbox', 'تفعيل', 'تنبيه فوري عند وجود طلب بدون موظف'); ?>
        </div>

        <div style="margin-top:16px;">
            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fa fa-save"></i> حفظ الإعدادات</button>
        </div>
    </form>
</section>
<?php
function ews_render_row(array $current, string $key, string $type, string $label, string $note = '', string $width = '')
{ global $dbRepo;
    global $dbRepo;

    $value = $current[$key] ?? '';
    $style = $width !== '' ? 'style="width:' . $width . ';"' : '';
    echo '<div class="ews-row">';
    echo '<div><div class="ews-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
    if ($note !== '') {
        echo '<div class="ews-note">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '</div>';
    if ($type === 'checkbox') {
        $checked = $value === '1' ? 'checked' : '';
        echo '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="0">';
        echo '<input type="checkbox" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="1" class="ews-switch" ' . $checked . '>';
    } else {
        echo '<input type="' . $type . '" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" class="form-control input-sm ews-input" ' . $style . '>';
    }
    echo '</div>';
}
?>
<?php require_once('footer.php'); ?>
