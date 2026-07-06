<?php
/**
 * Telegram Bot Administration Dashboard & Monitoring Panel
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

// Block employees
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Employee') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/telegram/Services/TelegramService.php';
require_once __DIR__ . '/telegram/Services/HealthService.php';
require_once __DIR__ . '/telegram/Services/AuditService.php';
require_once __DIR__ . '/telegram/Services/QueueService.php';

$message = '';
$error = '';
$adminId = (int) $_SESSION['user']['id'];

// 1. Process Maintenance / Disaster Recovery Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim((string) $_POST['action']);

    try {
        switch ($action) {
            case 'test_connection':
                $telegramService = TelegramService::getInstance($pdo);
                $res = $telegramService->apiCall('getMe');
                if (!empty($res['ok'])) {
                    $botUser = $res['result']['username'] ?? 'None';
                    // Check if current manager has Telegram linked to send test message
                    $stmt = $dbRepo->prepare("SELECT telegram_chat_id FROM tbl_user WHERE id = ? AND telegram_is_linked = 1 LIMIT 1");
                    $stmt->execute([$adminId]);
                    $mChat = $stmt->fetchColumn();
                    
                    if ($mChat) {
                        $telegramService->sendMessage($mChat, "🔔 <b>رسالة اختبار من النظام!</b>\n\nلقد قمت بإجراء فحص الاتصال من لوحة التحكم بنجاح.");
                        $message = "تم الاتصال بالبوت (@{$botUser}) بنجاح وإرسال رسالة تجريبية لحسابك.";
                    } else {
                        $message = "تم الاتصال بالبوت (@{$botUser}) بنجاح. (ملاحظة: حسابك غير مرتبط بتيليجرام لإرسال رسالة تجريبية).";
                    }
                    AuditService::logAudit($pdo, $adminId, 'test_telegram_connection', null, "Success (@{$botUser})");
                } else {
                    $error = "فشل الاتصال بالبوت: " . ($res['description'] ?? 'No response');
                }
                break;

            case 'reset_webhook':
                $stmt = $dbRepo->query("SELECT telegram_webhook_url, telegram_secret_token, telegram_is_enabled FROM tbl_settings WHERE id = 1 LIMIT 1");
                $set = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$set || empty($set['telegram_webhook_url'])) {
                    $error = "يرجى تهيئة رابط الـ Webhook أولاً في صفحة الإعدادات.";
                } else {
                    $telegramService = TelegramService::getInstance($pdo);
                    $webhookRes = $telegramService->apiCall('setWebhook', [
                        'url' => $set['telegram_webhook_url'],
                        'secret_token' => $set['telegram_secret_token']
                    ]);
                    if (!empty($webhookRes['ok'])) {
                        $message = "تم إعادة تسجيل الـ Webhook بنجاح.";
                        AuditService::logAudit($pdo, $adminId, 'reset_webhook', null, $set['telegram_webhook_url']);
                    } else {
                        $error = "فشل تحديث Webhook: " . ($webhookRes['description'] ?? 'No response');
                    }
                }
                break;

            case 'regenerate_secret':
                $newSecret = md5(uniqid((string)rand(), true));
                $stmt = $dbRepo->query("SELECT telegram_webhook_url FROM tbl_settings WHERE id = 1 LIMIT 1");
                $url = $stmt->fetchColumn();

                $pdo->beginTransaction();
                $dbRepo->prepare("UPDATE tbl_settings SET telegram_secret_token = ? WHERE id = 1")->execute([$newSecret]);
                AuditService::logAudit($pdo, $adminId, 'regenerate_secret', null, "New secret generated");
                $pdo->commit();

                if ($url) {
                    $telegramService = TelegramService::getInstance($pdo);
                    $telegramService->apiCall('setWebhook', [
                        'url' => $url,
                        'secret_token' => $newSecret
                    ]);
                }
                $message = "تم إعادة توليد الرمز السري وتحديث Webhook بنجاح.";
                break;

            case 'retry_failed':
                $stmt = $dbRepo->prepare("
                    UPDATE `tbl_telegram_queue` 
                    SET `status` = 'pending', `attempts` = 0, `next_attempt_at` = NOW() 
                    WHERE `status` IN ('failed', 'dead_letter')
                ");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "تمت إعادة جدولة {$count} مهمة فاشلة في الطابور بنجاح.";
                AuditService::logAudit($pdo, $adminId, 'retry_failed_queue', null, "Retried {$count} jobs");
                break;

            case 'clear_dlq':
                $stmt = $dbRepo->prepare("DELETE FROM `tbl_telegram_queue` WHERE `status` = 'dead_letter'");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "تم تفريغ طابور الرسائل التالفة (Dead Letter Queue) بنجاح. تم حذف {$count} مهمة.";
                AuditService::logAudit($pdo, $adminId, 'clear_dlq', null, "Cleared {$count} jobs");
                break;

            case 'purge_logs':
                $stmt = $dbRepo->prepare("DELETE FROM `tbl_telegram_logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "تم تنظيف السجلات بنجاح. تم حذف {$count} سجل أقدم من 30 يوماً.";
                AuditService::logAudit($pdo, $adminId, 'purge_logs', null, "Purged {$count} rows");
                break;

            case 'export_logs':
                // Send CSV headers
                ob_clean();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=telegram_logs_' . date('Ymd_His') . '.csv');
                $output = fopen('php://output', 'w');
                // UTF-8 BOM
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                fputcsv($output, ['ID', 'Chat ID', 'User Type', 'User ID', 'Message ID', 'Type', 'Action', 'Payload', 'Status', 'IP Address', 'Error', 'Latency (ms)', 'Created At']);
                
                $stmt = $dbRepo->query("SELECT * FROM `tbl_telegram_logs` ORDER BY id DESC LIMIT 5000");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit;
        }
    } catch (Exception $e) {
        $error = "حدث خطأ أثناء تنفيذ العملية: " . $e->getMessage();
    }
}

// 2. Fetch Health Metrics
$health = HealthService::checkHealth($pdo);

// 3. Fetch Statistics Metrics
try {
    // Total logs metrics
    $stmt = $dbRepo->query("
        SELECT 
            SUM(CASE WHEN `status` = 'success' THEN 1 ELSE 0 END) AS success_count,
            SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) AS failed_count,
            AVG(CASE WHEN `status` = 'success' THEN `latency_ms` ELSE NULL END) AS avg_latency
        FROM `tbl_telegram_logs`
    ");
    $logStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $successCount = (int) ($logStats['success_count'] ?? 0);
    $failedCount = (int) ($logStats['failed_count'] ?? 0);
    $totalLogs = $successCount + $failedCount;
    $successRate = $totalLogs > 0 ? round(($successCount / $totalLogs) * 100, 1) : 100;
    $avgLatency = round((float) ($logStats['avg_latency'] ?? 0), 0);

    // Messages grouped by intervals (analytics)
    $todayMsg = (int) $dbRepo->query("SELECT COUNT(*) FROM `tbl_telegram_logs` WHERE `message_type` = 'outgoing' AND `created_at` >= DATE(NOW())")->fetchColumn();
    $weekMsg = (int) $dbRepo->query("SELECT COUNT(*) FROM `tbl_telegram_logs` WHERE `message_type` = 'outgoing' AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $monthMsg = (int) $dbRepo->query("SELECT COUNT(*) FROM `tbl_telegram_logs` WHERE `message_type` = 'outgoing' AND `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    // Linked accounts counts
    $linkedEmployees = (int) $dbRepo->query("SELECT COUNT(*) FROM `tbl_employee` WHERE `telegram_is_linked` = 1")->fetchColumn();
    $linkedManagers = (int) $dbRepo->query("SELECT COUNT(*) FROM `tbl_user` WHERE `telegram_is_linked` = 1")->fetchColumn();

    // Webhook activity
    $lastWebhook = $dbRepo->query("SELECT created_at FROM `tbl_telegram_logs` WHERE `message_type` = 'incoming' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 'لا يوجد نشاط مؤخراً';

    // Top active employees / managers
    $stmt = $dbRepo->query("
        SELECT COALESCE(e.full_name, l.chat_id) AS name, COUNT(l.id) AS msg_count
        FROM tbl_telegram_logs l
        LEFT JOIN tbl_employee e ON e.id = l.user_id AND l.user_type = 'employee'
        WHERE l.user_type = 'employee'
        GROUP BY l.chat_id
        ORDER BY msg_count DESC LIMIT 5
    ");
    $topEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $dbRepo->query("
        SELECT COALESCE(u.full_name, l.chat_id) AS name, COUNT(l.id) AS msg_count
        FROM tbl_telegram_logs l
        LEFT JOIN tbl_user u ON u.id = l.user_id AND l.user_type = 'manager'
        WHERE l.user_type = 'manager'
        GROUP BY l.chat_id
        ORDER BY msg_count DESC LIMIT 5
    ");
    $topManagers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Recent logs for audit / logs view
    $stmt = $dbRepo->query("SELECT * FROM `tbl_telegram_logs` ORDER BY id DESC LIMIT 10");
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Exception $e) {
    $error = "خطأ في جلب بيانات الإحصائيات: " . $e->getMessage();
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>لوحة تحكم ومراقبة Telegram Bot</h1>
    </div>
    <div class="content-header-right">
        <a href="telegram-settings.php" class="btn btn-success"><i class="fa fa-cog"></i> إعدادات البوت</a>
    </div>
</section>

<section class="content telegram-admin telegram-dashboard-page">
    
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

    <!-- 1. Health Status Widget Cards -->
    <div class="row telegram-status-grid">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fa fa-telegram"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">حالة اتصال البوت</span>
                    <span class="info-box-number" style="font-size:18px;">
                        <?php echo $health['api_status'] === 'connected' ? '✅ متصل' : ($health['api_status'] === 'disabled' ? '⚪ معطل' : '❌ خطأ بالاتصال'); ?>
                    </span>
                    <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                    </div>
                    <span class="progress-description">
                        زمن الاستجابة: <?php echo $health['api_latency_ms']; ?> مللي ثانية
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-link"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">حالة Webhook</span>
                    <span class="info-box-number" style="font-size:18px;">
                        <?php echo $health['webhook_status'] === 'set' ? '✅ مسجّل' : ($health['webhook_status'] === 'disabled' ? '⚪ معطل' : '❌ غير مربوط'); ?>
                    </span>
                    <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                    </div>
                    <span class="progress-description" style="text-overflow:ellipsis;white-space:nowrap;overflow:hidden;">
                        آخر نشاط: <?php echo $lastWebhook; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-envelope-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">معدل نجاح الإرسال</span>
                    <span class="info-box-number" style="font-size:18px;"><?php echo $successRate; ?>%</span>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo $successRate; ?>%"></div>
                    </div>
                    <span class="progress-description">
                        النجاح: <?php echo $successCount; ?> | الفشل: <?php echo $failedCount; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box bg-red">
                <span class="info-box-icon"><i class="fa fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">الحسابات المرتبطة</span>
                    <span class="info-box-number" style="font-size:18px;">
                        👥 <?php echo $linkedEmployees + $linkedManagers; ?>
                    </span>
                    <div class="progress">
                        <div class="progress-bar" style="width: 100%"></div>
                    </div>
                    <span class="progress-description">
                        المدراء: <?php echo $linkedManagers; ?> | الموظفون: <?php echo $linkedEmployees; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Queue Status and Analytics Stats -->
    <div class="row telegram-dashboard-grid">
        <!-- Queue Monitoring Card -->
        <div class="col-md-4 telegram-dashboard-column">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-exchange"></i> مراقبة طابور الرسائل (Queue)</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-striped">
                        <tr>
                            <td>⏳ رسائل قيد الانتظار (Pending)</td>
                            <td><span class="badge bg-blue"><?php echo $health['queue_pending']; ?></span></td>
                        </tr>
                        <tr>
                            <td>🔄 رسائل فاشلة للجدولة (Failed)</td>
                            <td><span class="badge bg-yellow"><?php echo $health['queue_failed']; ?></span></td>
                        </tr>
                        <tr>
                            <td>💀 رسائل تالفة (Dead Letter)</td>
                            <td><span class="badge bg-red"><?php echo $health['queue_dead_letter']; ?></span></td>
                        </tr>
                        <tr>
                            <td>📊 متوسط سرعة التوصيل</td>
                            <td><span class="badge bg-purple"><?php echo $avgLatency; ?> ms</span></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-bar-chart"></i> إحصائيات الرسائل الموجهة</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-striped">
                        <tr>
                            <td>📅 إرسال اليوم (Today)</td>
                            <td><span class="badge bg-aqua"><?php echo $todayMsg; ?></span></td>
                        </tr>
                        <tr>
                            <td>📅 إرسال هذا الأسبوع (Week)</td>
                            <td><span class="badge bg-blue"><?php echo $weekMsg; ?></span></td>
                        </tr>
                        <tr>
                            <td>📅 إرسال هذا الشهر (Month)</td>
                            <td><span class="badge bg-purple"><?php echo $monthMsg; ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Maintenance & Disaster Recovery Actions -->
        <div class="col-md-4 telegram-dashboard-column">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-wrench"></i> صيانة الكوارث وإجراءات الطوارئ</h3>
                </div>
                <div class="box-body">
                    <form method="post" class="telegram-actions-form">
                        <button type="submit" name="action" value="test_connection" class="btn btn-block btn-info btn-flat">
                            <i class="fa fa-ping"></i> فحص الاتصال وإرسال تنبيه تجريبي
                        </button>
                        <button type="submit" name="action" value="reset_webhook" class="btn btn-block btn-primary btn-flat">
                            <i class="fa fa-link"></i> إعادة تسجيل Webhook لتيليجرام
                        </button>
                        <button type="submit" name="action" value="regenerate_secret" class="btn btn-block btn-warning btn-flat">
                            <i class="fa fa-key"></i> تجديد رمز Webhook السري الآمن
                        </button>
                        <button type="submit" name="action" value="retry_failed" class="btn btn-block btn-success btn-flat">
                            <i class="fa fa-refresh"></i> إعادة تشغيل الرسائل الفاشلة / التالفة
                        </button>
                        <button type="submit" name="action" value="clear_dlq" class="btn btn-block btn-danger btn-flat">
                            <i class="fa fa-trash"></i> مسح الرسائل التالفة (Clear DLQ)
                        </button>
                        <button type="submit" name="action" value="purge_logs" class="btn btn-block btn-default btn-flat text-red">
                            <i class="fa fa-times"></i> تنظيف السجلات القديمة (أقدم من 30 يوماً)
                        </button>
                        <button type="submit" name="action" value="export_logs" class="btn btn-block btn-default btn-flat">
                            <i class="fa fa-download"></i> تصدير السجلات بتنسيق CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Active Users Panel -->
        <div class="col-md-4 telegram-dashboard-column">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-users"></i> الموظفون والمدراء الأكثر نشاطاً</h3>
                </div>
                <div class="box-body">
                    <strong>المدراء النشطون:</strong>
                    <ul class="list-unstyled" style="padding-right:0; margin-top:5px; margin-bottom:15px;">
                        <?php if (empty($topManagers)): ?>
                            <li class="text-muted">لا يوجد نشاط مسجل بعد</li>
                        <?php else: ?>
                            <?php foreach ($topManagers as $tm): ?>
                                <li>👤 <?php echo htmlspecialchars($tm['name'], ENT_QUOTES, 'UTF-8'); ?> <span class="pull-left label label-success"><?php echo $tm['msg_count']; ?> رسالة</span></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <strong>الموظفون النشطون:</strong>
                    <ul class="list-unstyled" style="padding-right:0; margin-top:5px;">
                        <?php if (empty($topEmployees)): ?>
                            <li class="text-muted">لا يوجد نشاط مسجل بعد</li>
                        <?php else: ?>
                            <?php foreach ($topEmployees as $te): ?>
                                <li>👤 <?php echo htmlspecialchars($te['name'], ENT_QUOTES, 'UTF-8'); ?> <span class="pull-left label label-primary"><?php echo $te['msg_count']; ?> رسالة</span></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Logs Activity View -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-list"></i> آخر السجلات والأنشطة (أحدث 10)</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>معرّف Chat</th>
                                <th>نوع المستخدم</th>
                                <th>نوع السجل</th>
                                <th>العملية</th>
                                <th>الحالة</th>
                                <th>عنوان IP</th>
                                <th>زمن الاستجابة</th>
                                <th>التاريخ والوقت</th>
                                <th>تفاصيل الخطأ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLogs)): ?>
                                <tr><td colspan="9" class="text-center text-muted">لا توجد سجلات بعد.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($log['chat_id'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td>
                                            <?php 
                                            if ($log['user_type'] === 'manager') echo '<span class="label label-success">مدير</span>';
                                            elseif ($log['user_type'] === 'employee') echo '<span class="label label-primary">موظف</span>';
                                            else echo '<span class="label label-default">تلقائي/طابور</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($log['message_type'] === 'outgoing') echo 'صادر ⬆️';
                                            elseif ($log['message_type'] === 'incoming') echo 'وارد ⬇️';
                                            else echo 'تفاعل كيبورد 🖱️';
                                            ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($log['action_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td>
                                            <?php echo $log['status'] === 'success' ? '<span class="text-green">✓ ناجح</span>' : '<span class="text-red">✗ فاشل</span>'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $log['latency_ms']; ?> ms</td>
                                        <td><?php echo htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($log['error_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($log['error_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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

.telegram-admin .info-box,
.telegram-admin .box {
    border: 1px solid var(--tg-border) !important;
    border-radius: 8px !important;
    box-shadow: none !important;
    overflow: hidden;
}

.telegram-admin .box {
    margin-bottom: 16px;
}

.telegram-status-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.telegram-status-grid:before,
.telegram-status-grid:after,
.telegram-dashboard-grid:before,
.telegram-dashboard-grid:after {
    display: none;
}

.telegram-status-grid > [class*="col-"] {
    float: none;
    width: auto;
    padding: 0;
}

.telegram-admin .info-box {
    min-height: 96px;
    margin-bottom: 0;
    color: var(--tg-text) !important;
    background: #fff !important;
}

.telegram-admin .info-box-icon {
    float: right;
    width: 62px;
    height: 96px;
    line-height: 96px;
    font-size: 26px;
    background: var(--tg-soft) !important;
    color: #2563eb !important;
}

.telegram-admin .info-box-content {
    min-height: 96px;
    margin-right: 62px;
    margin-left: 0;
    padding: 12px 14px;
}

.telegram-admin .info-box-text,
.telegram-admin .progress-description {
    color: var(--tg-muted);
    white-space: normal !important;
    line-height: 1.45;
}

.telegram-admin .info-box-number {
    color: var(--tg-text);
    line-height: 1.35;
    margin-top: 3px;
}

.telegram-admin .progress {
    height: 3px;
    margin: 8px 0 5px;
    background: #eef2f7;
}

.telegram-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(300px, 1fr) minmax(280px, 1fr);
    gap: 16px;
}

.telegram-dashboard-grid > .telegram-dashboard-column {
    float: none;
    width: auto;
    padding: 0;
}

.telegram-admin .box-header {
    padding: 12px 14px;
}

.telegram-admin .box-title {
    color: var(--tg-text);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.5;
}

.telegram-admin .box-body {
    padding: 14px;
}

.telegram-admin .box-body.no-padding {
    padding: 0 !important;
}

.telegram-admin .table {
    margin-bottom: 0;
}

.telegram-admin .table > tbody > tr > td,
.telegram-admin .table > thead > tr > th {
    vertical-align: middle;
    padding: 9px 12px;
    line-height: 1.45;
}

.telegram-admin .table > thead > tr > th {
    color: #475569;
    background: var(--tg-soft);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.telegram-admin .badge,
.telegram-admin .label {
    border-radius: 999px;
    font-weight: 700;
}

.telegram-actions-form {
    display: grid;
    gap: 8px;
}

.telegram-actions-form .btn {
    min-height: 34px;
    padding: 7px 10px;
    text-align: right;
    white-space: normal;
    line-height: 1.35;
    border-radius: 6px !important;
}

.telegram-actions-form .btn i {
    width: 18px;
    text-align: center;
}

.telegram-admin .list-unstyled li {
    min-height: 28px;
    padding: 5px 0;
    border-bottom: 1px solid #f1f5f9;
    line-height: 1.45;
}

.telegram-admin .list-unstyled li:last-child {
    border-bottom: 0;
}

.telegram-admin code {
    display: inline-block;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}

.telegram-admin .table-responsive {
    border: 0;
}

@media (max-width: 1199px) {
    .telegram-status-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .telegram-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767px) {
    .telegram-status-grid {
        grid-template-columns: 1fr;
    }

    .telegram-admin .info-box-icon {
        width: 52px;
    }

    .telegram-admin .info-box-content {
        margin-right: 52px;
    }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
