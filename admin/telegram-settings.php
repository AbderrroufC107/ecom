<?php
/**
 * Telegram Bot Settings Page
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// The bot token/webhook are a single shared setting for the whole platform
// (tbl_settings id=1) — only Super Admin may view or change them. A regular
// Manager (role=Admin) changing this would break Telegram for every other
// manager and employee, so they (and Employees) are redirected away.
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'Super Admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/telegram/Services/TelegramService.php';
require_once __DIR__ . '/telegram/Services/AuditService.php';

$message = '';
$error = '';

// Load settings
try {
    $stmt = $dbRepo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "حدث خطأ أثناء تحميل الإعدادات: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $botToken = trim((string) ($_POST['telegram_bot_token'] ?? ''));
    $webhookUrl = trim((string) ($_POST['telegram_webhook_url'] ?? ''));
    $isEnabled = isset($_POST['telegram_is_enabled']) ? 1 : 0;
    $empNotif = isset($_POST['telegram_enable_employee_notifications']) ? 1 : 0;
    $mgrNotif = isset($_POST['telegram_enable_manager_notifications']) ? 1 : 0;
    $compNotif = isset($_POST['telegram_enable_complaint_notifications']) ? 1 : 0;
    $dailyNotif = isset($_POST['telegram_enable_daily_reports']) ? 1 : 0;
    $queueNotif = isset($_POST['telegram_enable_queue_processing']) ? 1 : 0;
    $retryAttempts = (int) ($_POST['telegram_queue_retry_attempts'] ?? 3);
    $reportTime = trim((string) ($_POST['telegram_daily_report_time'] ?? '08:00:00'));
    $reminderHours = (int) ($_POST['telegram_reminder_hours'] ?? 24);
    $weeklyDay = (int) ($_POST['telegram_weekly_report_day'] ?? 5);
    $secretToken = trim((string) ($_POST['telegram_secret_token'] ?? ''));

    if ($secretToken === '') {
        $secretToken = md5(uniqid((string)rand(), true));
    }

    $pdo->beginTransaction();
    try {
        // Update DB
        $stmt = $dbRepo->prepare("
            UPDATE tbl_settings 
            SET 
                telegram_bot_token = ?,
                telegram_webhook_url = ?,
                telegram_is_enabled = ?,
                telegram_enable_employee_notifications = ?,
                telegram_enable_manager_notifications = ?,
                telegram_enable_complaint_notifications = ?,
                telegram_enable_daily_reports = ?,
                telegram_enable_queue_processing = ?,
                telegram_queue_retry_attempts = ?,
                telegram_daily_report_time = ?,
                telegram_reminder_hours = ?,
                telegram_weekly_report_day = ?,
                telegram_secret_token = ?
            WHERE id = 1
        ");
        
        $stmt->execute([
            $botToken,
            $webhookUrl,
            $isEnabled,
            $empNotif,
            $mgrNotif,
            $compNotif,
            $dailyNotif,
            $queueNotif,
            $retryAttempts,
            $reportTime,
            $reminderHours,
            $weeklyDay,
            $secretToken
        ]);

        // Audit Trail
        AuditService::logAudit($pdo, (int)$_SESSION['user']['id'], 'update_telegram_settings', json_encode($settings), json_encode($_POST));

        $pdo->commit();
        $message = "تم حفظ إعدادات البوت بنجاح.";

        // Clear Bot Username Cache so it re-fetches
        $cacheFile = __DIR__ . '/telegram/cache/telegram_bot_username.cache';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        // Auto-Register Webhook if URL is provided and Bot is enabled
        if ($isEnabled && $botToken !== '' && $webhookUrl !== '') {
            // Re-instantiate TelegramService with new token
            $telegramService = TelegramService::getInstance($pdo);
            $webhookRes = $telegramService->apiCall('setWebhook', [
                'url' => $webhookUrl,
                'secret_token' => $secretToken
            ]);

            if (!empty($webhookRes['ok'])) {
                $message .= " وتم ربط Webhook الخاص بالبوت بنجاح.";
            } else {
                $error = "تم حفظ الإعدادات، ولكن فشل تسجيل Webhook في تيليجرام: " . ($webhookRes['description'] ?? 'No response');
            }
        }

        // Reload Settings
        $stmt = $dbRepo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "فشل حفظ الإعدادات: " . $e->getMessage();
    }
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إعدادات بوت تيليجرام (Telegram Bot Settings)</h1>
    </div>
    <div class="content-header-right">
        <a href="telegram-dashboard.php" class="btn btn-primary"><i class="fa fa-dashboard"></i> لوحة التحكم</a>
    </div>
</section>

<section class="content telegram-admin telegram-settings-page">
    <div class="row">
        <div class="col-md-12">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h4><i class="icon fa fa-check"></i> نجاح!</h4>
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h4><i class="icon fa fa-ban"></i> خطأ!</h4>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form class="form-horizontal telegram-settings-form" action="" method="post">
                <div class="box box-info telegram-settings-box">
                    <div class="box-header with-border">
                        <h3 class="box-title">إعدادات الاتصال والخصائص</h3>
                    </div>
                    <div class="box-body">
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">تفعيل البوت في النظام</label>
                            <div class="col-sm-9">
                                <label class="switch" style="margin-top: 7px;">
                                    <input type="checkbox" name="telegram_is_enabled" value="1" <?php if (($settings['telegram_is_enabled'] ?? 0) == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">رمز توكن البوت (Bot Token) <span>*</span></label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="telegram_bot_token" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">رابط Webhook (Webhook URL)</label>
                            <div class="col-sm-6">
                                <input type="url" class="form-control" name="telegram_webhook_url" value="<?php echo htmlspecialchars($settings['telegram_webhook_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://your-domain.com/admin/telegram-webhook.php">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">رمز التحقق السري (Secret Token)</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="telegram_secret_token" value="<?php echo htmlspecialchars($settings['telegram_secret_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="توليد تلقائي إذا تُرِك فارغاً">
                                <small class="text-muted">مستخدم للتحقق من هوية الطلبات الواردة إلى Webhook لضمان أمان البوت.</small>
                            </div>
                        </div>

                        <hr>
                        <div class="box-header with-border" style="padding-left:0; margin-bottom:15px;">
                            <h3 class="box-title">خصائص الإشعارات المجدولة والجدولة الزمنية</h3>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">وقت التقرير اليومي</label>
                            <div class="col-sm-2">
                                <input type="time" class="form-control" name="telegram_daily_report_time" value="<?php echo htmlspecialchars($settings['telegram_daily_report_time'] ?? '08:00:00', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">تذكير بالمهام المعلقة (ساعة)</label>
                            <div class="col-sm-2">
                                <input type="number" class="form-control" name="telegram_reminder_hours" value="<?php echo (int) ($settings['telegram_reminder_hours'] ?? 24); ?>" min="1">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">يوم التقرير الأسبوعي</label>
                            <div class="col-sm-3">
                                <select class="form-control" name="telegram_weekly_report_day">
                                    <option value="1" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 1) echo 'selected'; ?>>الإثنين</option>
                                    <option value="2" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 2) echo 'selected'; ?>>الثلاثاء</option>
                                    <option value="3" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 3) echo 'selected'; ?>>الأربعاء</option>
                                    <option value="4" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 4) echo 'selected'; ?>>الخميس</option>
                                    <option value="5" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 5) echo 'selected'; ?>>الجمعة</option>
                                    <option value="6" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 6) echo 'selected'; ?>>السبت</option>
                                    <option value="7" <?php if (($settings['telegram_weekly_report_day'] ?? 5) == 7) echo 'selected'; ?>>الأحد</option>
                                </select>
                            </div>
                        </div>

                        <hr>
                        <div class="box-header with-border" style="padding-left:0; margin-bottom:15px;">
                            <h3 class="box-title">خصائص الإشعارات الافتراضية والتشغيل</h3>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">إشعارات الموظفين</label>
                            <div class="col-sm-9">
                                <label class="switch" style="margin-top: 7px;">
                                    <input type="checkbox" name="telegram_enable_employee_notifications" value="1" <?php if (($settings['telegram_enable_employee_notifications'] ?? 1) == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">إشعارات المديرين</label>
                            <div class="col-sm-9">
                                <label class="switch" style="margin-top: 7px;">
                                    <input type="checkbox" name="telegram_enable_manager_notifications" value="1" <?php if (($settings['telegram_enable_manager_notifications'] ?? 1) == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">إشعارات الشكاوى والملاحظات</label>
                            <div class="col-sm-9">
                                <label class="switch" style="margin-top: 7px;">
                                    <input type="checkbox" name="telegram_enable_complaint_notifications" value="1" <?php if (($settings['telegram_enable_complaint_notifications'] ?? 1) == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">التقرير اليومي المجدول</label>
                            <div class="col-sm-9">
                                <label class="switch" style="margin-top: 7px;">
                                    <input type="checkbox" name="telegram_enable_daily_reports" value="1" <?php if (($settings['telegram_enable_daily_reports'] ?? 0) == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">تفعيل طابور الرسائل (Queue)</label>
                            <div class="col-sm-9">
                                <label class="switch" style="margin-top: 7px;">
                                    <input type="checkbox" name="telegram_enable_queue_processing" value="1" <?php if (($settings['telegram_enable_queue_processing'] ?? 1) == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">عدد محاولات إعادة الإرسال</label>
                            <div class="col-sm-2">
                                <input type="number" class="form-control" name="telegram_queue_retry_attempts" value="<?php echo (int) ($settings['telegram_queue_retry_attempts'] ?? 3); ?>" min="1" max="10">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label"></label>
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-success pull-left" name="save_settings">حفظ التغييرات</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
.telegram-admin {
  --tg-border: #e5e7eb;
  --tg-muted: #64748b;
  --tg-text: #0f172a;
  --tg-soft: #f8fafc;
  direction: rtl;
}

.telegram-admin .row {
  margin-left: -8px;
  margin-right: -8px;
}

.telegram-admin [class*="col-"] {
  padding-left: 8px;
  padding-right: 8px;
}

.telegram-settings-page .box {
  border: 1px solid var(--tg-border) !important;
  border-radius: 8px !important;
  box-shadow: none !important;
  overflow: hidden;
}

.telegram-settings-page .box-header {
  padding: 12px 14px;
}

.telegram-settings-page .box-title {
  color: var(--tg-text);
  font-size: 14px;
  font-weight: 700;
  line-height: 1.5;
}

.telegram-settings-page .box-body {
  display: grid;
  grid-template-columns: repeat(2, minmax(280px, 1fr));
  gap: 12px;
  padding: 14px;
}

.telegram-settings-page .box-body > .form-group {
  display: grid;
  grid-template-columns: minmax(150px, 220px) minmax(0, 1fr);
  align-items: center;
  gap: 10px 14px;
  min-height: 62px;
  margin: 0;
  padding: 12px;
  background: #fff;
  border: 1px solid #eef2f7;
  border-radius: 8px;
}

.telegram-settings-page .box-body > hr,
.telegram-settings-page .box-body > .box-header {
  grid-column: 1 / -1;
}

.telegram-settings-page .box-body > hr {
  display: none;
}

.telegram-settings-page .box-body > .box-header {
  margin: 4px 0 0 !important;
  padding: 10px 12px !important;
  background: var(--tg-soft);
  border: 1px solid #eef2f7;
  border-radius: 8px;
}

.telegram-settings-page .control-label {
  float: none !important;
  width: auto !important;
  padding: 0 !important;
  color: var(--tg-muted);
  font-size: 13px;
  font-weight: 700;
  text-align: right !important;
  line-height: 1.5;
}

.telegram-settings-page [class*="col-sm-"] {
  float: none !important;
  width: 100% !important;
  max-width: none !important;
  padding: 0 !important;
}

.telegram-settings-page .form-control {
  min-height: 36px;
  border-radius: 6px;
  border-color: #dbe3ee;
  box-shadow: none;
}

.telegram-settings-page .form-control:focus {
  border-color: #93c5fd;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
}

.telegram-settings-page small.text-muted {
  display: block;
  margin-top: 6px;
  color: var(--tg-muted);
  line-height: 1.55;
}

.telegram-settings-page .box-body > .form-group:last-child {
  grid-column: 1 / -1;
  min-height: auto;
  background: var(--tg-soft);
}

.telegram-settings-page .btn {
  min-height: 36px;
  border-radius: 6px !important;
  font-weight: 700;
}

/* Modern styling for Switch Toggles */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}
.switch input { 
  opacity: 0;
  width: 0;
  height: 0;
}
.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}
.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}
input:checked + .slider {
  background-color: #3c8dbc;
}
input:focus + .slider {
  box-shadow: 0 0 1px #3c8dbc;
}
input:checked + .slider:before {
  -webkit-transform: translateX(26px);
  -ms-transform: translateX(26px);
  transform: translateX(26px);
}
.slider.round {
  border-radius: 34px;
}
.slider.round:before {
  border-radius: 50%;
}

@media (max-width: 1199px) {
  .telegram-settings-page .box-body {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767px) {
  .telegram-settings-page .box-body > .form-group {
    grid-template-columns: 1fr;
  }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
