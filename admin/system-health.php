<?php require_once('header.php'); ?>
<?php
require_once('inc/audit.php');
require_once('inc/error_logger.php');
audit_ensure_tables($pdo);
error_logger_ensure_tables($pdo);

$health = [];

$db_check = ['status' => 'healthy', 'message' => ''];
try {
    $dbRepo->query("SELECT 1");
    $db_check['message'] = 'قاعدة البيانات تعمل';
} catch (Exception $e) {
    $db_check['status'] = 'critical';
    $db_check['message'] = 'خطأ: ' . $e->getMessage();
}
$health['database'] = $db_check;

$cron_check = ['status' => 'warning', 'message' => 'لم يتم تسجيل تشغيل بعد'];
$last_run = '';
try {
    if (function_exists('telegram_get_event_setting')) {
        $last_run = telegram_get_event_setting($pdo, 'last_event_monitor_run');
    }
    if ($last_run === '') {
        $stmt = $dbRepo->query("SELECT config_value FROM tbl_event_settings WHERE config_key = 'last_event_monitor_run' LIMIT 1");
        $last_run = (string) $stmt->fetchColumn();
    }
    if ($last_run !== '') {
        $diff = time() - strtotime($last_run);
        if ($diff < 120) {
            $cron_check['status'] = 'healthy';
            $cron_check['message'] = 'آخر تشغيل: ' . date('d/m/Y H:i', strtotime($last_run)) . ' (منذ ' . $diff . ' ثانية)';
        } elseif ($diff < 600) {
            $cron_check['status'] = 'warning';
            $cron_check['message'] = 'آخر تشغيل: ' . date('d/m/Y H:i', strtotime($last_run)) . ' (منذ ' . round($diff / 60) . ' دقيقة)';
        } else {
            $cron_check['status'] = 'critical';
            $cron_check['message'] = 'آخر تشغيل: ' . date('d/m/Y H:i', strtotime($last_run)) . ' (منذ أكثر من ' . round($diff / 60) . ' دقيقة)';
        }
    }
} catch (Exception $e) {
    $cron_check['message'] = 'خطأ في التحقق: ' . $e->getMessage();
}
$health['cron'] = $cron_check;

$ecotrack_check = ['status' => 'warning', 'message' => 'لم يتم التحقق'];
try {
    $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_order WHERE ecotrack_sent_at IS NOT NULL AND ecotrack_sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recent_syncs = (int) $stmt->fetchColumn();
    $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_order WHERE ecotrack_last_error != '' AND ecotrack_last_error IS NOT NULL AND ecotrack_updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recent_errors = (int) $stmt->fetchColumn();

    if ($recent_syncs > 0 && $recent_errors === 0) {
        $ecotrack_check['status'] = 'healthy';
        $ecotrack_check['message'] = 'آخر 24 ساعة: ' . $recent_syncs . ' مزامنة، 0 أخطاء';
    } elseif ($recent_syncs > 0 && $recent_errors <= $recent_syncs / 2) {
        $ecotrack_check['status'] = 'warning';
        $ecotrack_check['message'] = 'آخر 24 ساعة: ' . $recent_syncs . ' مزامنة، ' . $recent_errors . ' خطأ';
    } else {
        $ecotrack_check['status'] = 'critical';
        $ecotrack_check['message'] = 'آخر 24 ساعة: ' . $recent_syncs . ' مزامنة، ' . $recent_errors . ' خطأ';
    }
} catch (Exception $e) {
    $ecotrack_check['message'] = 'خطأ في التحقق: ' . $e->getMessage();
}
$health['ecotrack'] = $ecotrack_check;

$telegram_check = ['status' => 'warning', 'message' => 'لم يتم التحقق'];
try {
    $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_telegram_delivery_log WHERE delivery_status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $failed_1h = (int) $stmt->fetchColumn();
    $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_telegram_delivery_log WHERE delivery_status = 'sent' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $sent_1h = (int) $stmt->fetchColumn();

    if ($failed_1h === 0 && $sent_1h > 0) {
        $telegram_check['status'] = 'healthy';
        $telegram_check['message'] = 'آخر ساعة: ' . $sent_1h . ' ناجح، 0 فشل';
    } elseif ($failed_1h <= 3) {
        $telegram_check['status'] = 'warning';
        $telegram_check['message'] = 'آخر ساعة: ' . $sent_1h . ' ناجح، ' . $failed_1h . ' فشل';
    } else {
        $telegram_check['status'] = 'critical';
        $telegram_check['message'] = 'آخر ساعة: ' . $sent_1h . ' ناجح، ' . $failed_1h . ' فشل';
    }
} catch (Exception $e) {
    $telegram_check['message'] = 'خطأ في التحقق: ' . $e->getMessage();
}
$health['telegram'] = $telegram_check;

$recovery_check = ['status' => 'warning', 'message' => 'لم يتم التحقق'];
try {
    if (function_exists('recovery_engine_get_queue_counts')) {
        $queue = recovery_engine_get_queue_counts($pdo);
        $pending = (int) ($queue['pending'] ?? 0);
        $overdue = (int) ($queue['overdue'] ?? 0);
        $recent_failures = (int) ($queue['recent_failures'] ?? 0);

        if ($overdue === 0 && $pending <= 5) {
            $recovery_check['status'] = 'healthy';
            $recovery_check['message'] = $pending . ' قيد الانتظار، ' . $overdue . ' متأخر';
        } elseif ($overdue <= 10) {
            $recovery_check['status'] = 'warning';
            $recovery_check['message'] = $pending . ' قيد الانتظار، ' . $overdue . ' متأخر';
        } else {
            $recovery_check['status'] = 'critical';
            $recovery_check['message'] = $pending . ' قيد الانتظار، ' . $overdue . ' متأخر';
        }
        if ($recent_failures > 20) {
            $recovery_check['status'] = 'critical';
            $recovery_check['message'] .= ' + ' . $recent_failures . ' فشل حديث';
        }
        $recovery_check['data'] = $queue;
    } else {
        $recovery_check['message'] = 'محرك الاسترداد غير مثبت';
        $recovery_check['status'] = 'warning';
    }
} catch (Exception $e) {
    $recovery_check['message'] = 'خطأ في التحقق: ' . $e->getMessage();
}
$health['recovery'] = $recovery_check;

$errors_check = error_logger_check_health($pdo);
$health['errors'] = $errors_check;

$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$audit_24h = (int) $stmt->fetchColumn();

$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_system_error_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$error_24h = (int) $stmt->fetchColumn();
?>
<style>
.system-health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 15px;
}
.health-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 8px 20px rgba(15,23,42,0.05);
}
.health-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.health-card-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
}
.health-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.health-status.healthy { background: #dcfce7; color: #166534; }
.health-status.warning { background: #fef3c7; color: #92400e; }
.health-status.critical { background: #fee2e2; color: #991b1b; }
.health-metric {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
}
.health-label {
    font-size: 12px;
    color: #64748b;
}
</style>

<section class="content-header">
    <h1>صحة النظام (System Health)</h1>
</section>

<section class="content">
    <div class="system-health-grid">
        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-database" style="color:#2563eb;"></i> قاعدة البيانات</h4>
                <span class="health-status <?php echo $health['database']['status']; ?>">
                    <?php echo $health['database']['status'] === 'healthy' ? 'سليمة' : ($health['database']['status'] === 'warning' ? 'تحذير' : 'حرج'); ?>
                </span>
            </div>
            <p><?php echo htmlspecialchars($health['database']['message'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-clock-o" style="color:#0891b2;"></i> Cron / المراقب</h4>
                <span class="health-status <?php echo $health['cron']['status']; ?>">
                    <?php echo $health['cron']['status'] === 'healthy' ? 'سليمة' : ($health['cron']['status'] === 'warning' ? 'تحذير' : 'حرج'); ?>
                </span>
            </div>
            <p><?php echo htmlspecialchars($health['cron']['message'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-truck" style="color:#16a34a;"></i> مزامنة Ecotrack</h4>
                <span class="health-status <?php echo $health['ecotrack']['status']; ?>">
                    <?php echo $health['ecotrack']['status'] === 'healthy' ? 'سليمة' : ($health['ecotrack']['status'] === 'warning' ? 'تحذير' : 'حرج'); ?>
                </span>
            </div>
            <p><?php echo htmlspecialchars($health['ecotrack']['message'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-telegram" style="color:#2563eb;"></i> تلغرام</h4>
                <span class="health-status <?php echo $health['telegram']['status']; ?>">
                    <?php echo $health['telegram']['status'] === 'healthy' ? 'سليمة' : ($health['telegram']['status'] === 'warning' ? 'تحذير' : 'حرج'); ?>
                </span>
            </div>
            <p><?php echo htmlspecialchars($health['telegram']['message'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-life-ring" style="color:#f59e0b;"></i> محرك الاسترداد</h4>
                <span class="health-status <?php echo $health['recovery']['status']; ?>">
                    <?php echo $health['recovery']['status'] === 'healthy' ? 'سليم' : ($health['recovery']['status'] === 'warning' ? 'تحذير' : 'حرج'); ?>
                </span>
            </div>
            <p><?php echo htmlspecialchars($health['recovery']['message'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($health['recovery']['data'])): ?>
            <div style="display:flex;gap:15px;margin-top:10px;">
                <div><span class="health-metric"><?php echo (int) ($health['recovery']['data']['pending'] ?? 0); ?></span><br><span class="health-label">قيد الانتظار</span></div>
                <div><span class="health-metric"><?php echo (int) ($health['recovery']['data']['overdue'] ?? 0); ?></span><br><span class="health-label">متأخر</span></div>
                <div><span class="health-metric"><?php echo (int) ($health['recovery']['data']['review'] ?? 0); ?></span><br><span class="health-label">مراجعة</span></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-exclamation-triangle" style="color:#ef4444;"></i> أخطاء النظام</h4>
                <span class="health-status <?php echo $health['errors']['status']; ?>">
                    <?php echo $health['errors']['status'] === 'healthy' ? 'سليمة' : ($health['errors']['status'] === 'warning' ? 'تحذير' : 'حرج'); ?>
                </span>
            </div>
            <div style="display:flex;gap:15px;margin-bottom:10px;">
                <div><span class="health-metric"><?php echo (int) ($health['errors']['errors_1h'] ?? 0); ?></span><br><span class="health-label">آخر ساعة</span></div>
                <div><span class="health-metric"><?php echo (int) ($health['errors']['errors_24h'] ?? 0); ?></span><br><span class="health-label">آخر 24 ساعة</span></div>
            </div>
            <?php if (!empty($health['errors']['issues'])): ?>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($health['errors']['issues'] as $issue): ?>
                <li style="font-size:12px;color:#dc2626;padding:2px 0;">• <?php echo htmlspecialchars($issue, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-list-alt" style="color:#8b5cf6;"></i> سجل التدقيق</h4>
            </div>
            <div style="display:flex;gap:15px;">
                <div><span class="health-metric"><?php echo number_format($audit_24h); ?></span><br><span class="health-label">إجراء خلال 24 ساعة</span></div>
            </div>
            <p style="margin-top:8px;"><a href="audit-log.php" class="btn btn-sm btn-default"><i class="fa fa-search"></i> عرض السجل</a></p>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-clock-o" style="color:#64748b;"></i> الوظائف المعلقة</h4>
            </div>
            <?php
            $pending_jobs = [];
            try {
                $stmt = $dbRepo->query("SELECT task_type, COUNT(*) AS cnt FROM tbl_recovery_tasks WHERE status = 'pending' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW() GROUP BY task_type ORDER BY cnt DESC LIMIT 10");
                $pending_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            ?>
            <?php if (!empty($pending_jobs)): ?>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($pending_jobs as $job): ?>
                <li style="font-size:13px;padding:3px 0;">• <?php echo htmlspecialchars($job['task_type'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int) $job['cnt']; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted">لا توجد وظائف معلقة</p>
            <?php endif; ?>
        </div>

        <div class="health-card">
            <div class="health-card-header">
                <h4><i class="fa fa-times-circle" style="color:#dc2626;"></i> الوظائف الفاشلة</h4>
            </div>
            <?php
            $failed_jobs = [];
            try {
                $stmt = $dbRepo->query("SELECT component, COUNT(*) AS cnt FROM tbl_system_error_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY component ORDER BY cnt DESC LIMIT 10");
                $failed_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            ?>
            <?php if (!empty($failed_jobs)): ?>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($failed_jobs as $job): ?>
                <li style="font-size:13px;padding:3px 0;">• <?php echo htmlspecialchars($job['component'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int) $job['cnt']; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted">لا توجد وظائف فاشلة</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
