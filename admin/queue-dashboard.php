<?php require_once('header.php'); ?>
<?php
$store_id = $current_store_id;
$store = store_get($pdo, $store_id);
if (!$store) {
    echo '<div class="alert alert-danger">المتجر غير موجود</div>';
    require_once('footer.php');
    exit;
}

$is_super_admin = defined('SUPER_ADMIN') && SUPER_ADMIN === true;

// Handle actions
$error = '';
$success = '';

$action = $_GET['action'] ?? '';
$job_id = (int) ($_GET['job_id'] ?? 0);
$failed_id = (int) ($_GET['failed_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['retry_job']) && $job_id > 0) {
        if (store_retry_job($pdo, $job_id)) {
            $success = 'تم إعادة جدولة الوظيفة.';
        } else {
            $error = 'فشل إعادة الجدولة.';
        }
    }

    if (isset($_POST['cancel_job']) && $job_id > 0) {
        if (store_cancel_job($pdo, $job_id)) {
            $success = 'تم إلغاء الوظيفة.';
        } else {
            $error = 'فشل الإلغاء.';
        }
    }

    if (isset($_POST['requeue_job']) && $job_id > 0) {
        if (store_requeue_job($pdo, $job_id)) {
            $success = 'تم إعادة الوظيفة إلى قائمة الانتظار.';
        } else {
            $error = 'فشل إعادة الإدراج.';
        }
    }

    if (isset($_POST['delete_failed']) && $failed_id > 0) {
        if (store_delete_failed_job($pdo, $failed_id)) {
            $success = 'تم حذف سجل الفشل.';
        } else {
            $error = 'فشل الحذف.';
        }
    }

    if (isset($_POST['requeue_all_failed'])) {
        $count = store_requeue_failed_jobs($pdo, $_POST['job_type'] ?? null, $is_super_admin ? null : $store_id);
        $success = "تم إعادة {$count} وظيفة/وظائف إلى قائمة الانتظار.";
    }

    if (isset($_POST['purge_completed'])) {
        $days = (int) ($_POST['older_than_days'] ?? 7);
        $count = store_purge_completed_jobs($pdo, $days);
        $success = "تم حذف {$count} وظيفة/وظائف مكتملة.";
    }

    if (isset($_POST['purge_failed'])) {
        $count = store_purge_failed_jobs($pdo, $_POST['job_type'] ?? null, $is_super_admin ? null : $store_id);
        $success = "تم حذف {$count} سجل/سجلات فشل.";
    }

    if (isset($_POST['cleanup_stuck'])) {
        $count = store_cleanup_stuck_jobs($pdo, 30);
        $success = "تم تنظيف {$count} وظيفة/وظائف عالقة.";
    }
}

// Load data
$stats = store_get_queue_stats($pdo, $is_super_admin ? null : $store_id);
$health = store_get_queue_health($pdo);
$job_types_list = store_get_job_types_list();
$priority_list = store_get_priority_list();

// Filters for job list
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['job_type'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$filters = [];
if (!$is_super_admin) {
    $filters['store_id'] = $store_id;
}
if ($filter_status !== '') $filters['status'] = $filter_status;
if ($filter_type !== '') $filters['job_type'] = $filter_type;
if ($filter_priority !== '') $filters['priority'] = $filter_priority;

$jobs = store_get_queue_jobs($pdo, $filters, $limit, $offset);
$total_jobs = store_get_queue_job_count($pdo, $filters);
$total_pages = max(1, ceil($total_jobs / $limit));

// Failed jobs
$failed_page = max(1, (int) ($_GET['fpage'] ?? 1));
$failed_limit = 20;
$failed_offset = ($failed_page - 1) * $failed_limit;
$failed_jobs = store_get_failed_jobs($pdo, $is_super_admin ? null : $store_id, $failed_limit, $failed_offset);
$total_failed = store_get_failed_job_count($pdo, $is_super_admin ? null : $store_id);
$total_failed_pages = max(1, ceil($total_failed / $failed_limit));

// Average processing times by type
$avg_times = [];
foreach ($job_types_list as $jt => $jt_label) {
    $avg_times[$jt] = store_get_queue_avg_processing_time($pdo, $jt);
}
$avg_times['all'] = store_get_queue_avg_processing_time($pdo);

function qd_format_ms(float $seconds): string {
    if ($seconds <= 0) return '-';
    if ($seconds < 1) return round($seconds * 1000) . 'ms';
    if ($seconds < 60) return round($seconds, 1) . 's';
    return round($seconds / 60, 1) . 'm';
}

function qd_status_badge(string $status): string {
    $map = [
        'pending' => ['bg-info', 'قيد الانتظار'],
        'running' => ['bg-warning', 'قيد التشغيل'],
        'completed' => ['bg-success', 'مكتمل'],
        'failed' => ['bg-danger', 'فاشل'],
        'cancelled' => ['bg-secondary', 'ملغي'],
    ];
    $cls = $map[$status][0] ?? 'bg-secondary';
    $label = $map[$status][1] ?? $status;
    return "<span class=\"badge {$cls}\">{$label}</span>";
}
?>

<style>
.qd-dash { font-family: 'Cairo', 'Outfit', sans-serif; padding: 24px; direction: rtl; text-align: right; color: #1b2559; }
.qd-card { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(112,144,176,0.12); margin-bottom: 24px; }
.qd-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.qd-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.qd-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
.qd-stat { padding: 18px; background: #f8faff; border-radius: 14px; text-align: center; }
.qd-stat h3 { font-size: 26px; font-weight: 800; margin: 0; }
.qd-stat p { font-size: 12px; color: #a3aed1; margin: 4px 0 0; }
.qd-health { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.qd-health-ok { background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; }
.qd-health-warn { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.qd-health ul { margin: 4px 0 0 16px; padding: 0; font-size: 13px; }
.qd-table { width: 100%; border-collapse: collapse; }
.qd-table th { text-align: right; padding: 10px 8px; font-size: 12px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.qd-table td { padding: 10px 8px; font-size: 13px; border-bottom: 1px solid #f4f7fe; vertical-align: middle; }
.qd-table tr:hover td { background: #f8faff; }
.qd-btn { display: inline-block; padding: 6px 14px; border-radius: 8px; font-weight: 700; font-size: 12px; text-decoration: none; border: none; cursor: pointer; }
.qd-btn-primary { background: #4318ff; color: white; }
.qd-btn-danger { background: #f44336; color: white; }
.qd-btn-warning { background: #ff9800; color: white; }
.qd-btn-success { background: #05cd99; color: white; }
.qd-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.qd-btn-sm { padding: 4px 8px; font-size: 11px; }
.qd-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.qd-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.qd-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.qd-alert-success { background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; }
.qd-alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
.qd-meter { height: 6px; background: #e2e8f0; border-radius: 3px; margin: 8px 0; overflow: hidden; }
.qd-meter-fill { height: 100%; border-radius: 3px; }
.qd-filter-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
.qd-filter-form select, .qd-filter-form input { padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
.qd-worker-status { padding: 12px 16px; border-radius: 12px; background: #f8faff; border: 1px solid #e2e8f0; margin-bottom: 12px; }
.qd-worker-status dt { font-size: 12px; color: #a3aed1; }
.qd-worker-status dd { font-size: 14px; font-weight: 700; margin: 0 0 8px; }
.qd-truncate { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
.qd-monospace { font-family: 'Consolas', 'Courier New', monospace; direction: ltr; text-align: left; }
</style>

<div class="qd-dash">
    <h1 class="qd-title"><i class="fa fa-tasks" style="color: #4318ff;"></i> لوحة تحكم قائمة الانتظار</h1>
    <p class="qd-subtitle">مراقبة وإدارة الوظائف الخلفية — <?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($error): ?><div class="qd-alert qd-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="qd-alert qd-alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="qd-card">
        <div class="qd-grid">
            <div class="qd-stat"><h3 style="color: #4318ff;"><?php echo $stats['pending']; ?></h3><p>قيد الانتظار</p></div>
            <div class="qd-stat"><h3 style="color: #ff9800;"><?php echo $stats['running']; ?></h3><p>قيد التشغيل</p></div>
            <div class="qd-stat"><h3 style="color: #05cd99;"><?php echo $stats['completed']; ?></h3><p>مكتملة</p></div>
            <div class="qd-stat"><h3 style="color: #f44336;"><?php echo $stats['failed']; ?></h3><p>فاشلة</p></div>
            <div class="qd-stat"><h3 style="color: #607d8b;"><?php echo $stats['cancelled']; ?></h3><p>ملغية</p></div>
            <div class="qd-stat"><h3 style="color: #9c27b0;"><?php echo $stats['queued']; ?></h3><p>في قائمة الانتظار</p></div>
            <div class="qd-stat"><h3 style="color: #e91e63;"><?php echo $stats['failed_jobs']; ?></h3><p>سجلات الفشل</p></div>
            <div class="qd-stat"><h3 style="color: #00bcd4;"><?php echo $stats['job_types']; ?></h3><p>أنواع مهمة</p></div>
        </div>
    </div>

    <!-- Health -->
    <div class="qd-card">
        <div class="qd-section-head">
            <h3><i class="fa fa-heartbeat" style="color: <?php echo $health['healthy'] ? '#05cd99' : '#f44336'; ?>;"></i> صحة النظام</h3>
        </div>
        <div class="qd-health <?php echo $health['healthy'] ? 'qd-health-ok' : 'qd-health-warn'; ?>">
            <strong><?php echo $health['healthy'] ? '✓ النظام سليم' : '⚠ توجد مشاكل'; ?></strong>
            <?php if (!empty($health['alerts'])): ?>
            <ul>
                <?php foreach ($health['alerts'] as $alert): ?>
                <li><?php echo htmlspecialchars($alert, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="qd-grid" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
            <div class="qd-stat">
                <h3 style="color: <?php echo $health['stuck_jobs'] > 0 ? '#f44336' : '#05cd99'; ?>; font-size: 22px;"><?php echo $health['stuck_jobs']; ?></h3>
                <p>وظائف عالقة</p>
            </div>
            <div class="qd-stat">
                <h3 style="font-size: 22px;"><?php echo $health['backlog_count']; ?></h3>
                <p>تراكم > 1 ساعة</p>
            </div>
            <div class="qd-stat">
                <h3 style="font-size: 22px;"><?php echo $health['failed_last_hour']; ?></h3>
                <p>فشل آخر ساعة</p>
            </div>
            <div class="qd-stat">
                <h3 style="font-size: 22px;"><?php echo $health['completed_24h']; ?></h3>
                <p>مكتمل 24 ساعة</p>
            </div>
            <div class="qd-stat">
                <h3 style="font-size: 22px;"><?php echo qd_format_ms($health['avg_processing_time_seconds']); ?></h3>
                <p>متوسط وقت المعالجة</p>
            </div>
            <div class="qd-stat">
                <h3 style="font-size: 22px;"><?php echo $health['total_queued']; ?></h3>
                <p>إجمالي في الطابور</p>
            </div>
        </div>

        <!-- Worker Heartbeat -->
        <div style="margin-top: 16px;">
            <h4 style="font-size: 14px; font-weight: 700; color: #64748b;"><i class="fa fa-server"></i> حالة العمال</h4>
            <?php
            $heartbeat_files = glob(sys_get_temp_dir() . '/queue_worker_heartbeat_*');
            if (!empty($heartbeat_files)):
            ?>
            <div class="qd-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                <?php foreach ($heartbeat_files as $hf):
                    $hb = json_decode(file_get_contents($hf), true);
                    if (!$hb) continue;
                    $is_alive = $hb['time'] && (strtotime($hb['time']) > time() - 120);
                ?>
                <div class="qd-worker-status" style="border-right: 4px solid <?php echo $is_alive ? '#05cd99' : '#f44336'; ?>;">
                    <dl>
                        <dt>PID</dt><dd><?php echo htmlspecialchars($hb['pid'] ?? '?', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Host</dt><dd><?php echo htmlspecialchars($hb['host'] ?? '?', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>آخر نبض</dt><dd><?php echo htmlspecialchars($hb['time'] ?? '?', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>الحالة</dt><dd><span class="badge <?php echo $is_alive ? 'bg-success' : 'bg-danger'; ?>"><?php echo $is_alive ? 'نشط' : 'منقطع'; ?></span></dd>
                    </dl>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color: #a3aed1; font-size: 13px;">لا توجد عمالة نشطة حاليًا. قم بتشغيل <code>php workers/queue-worker.php --daemon</code></p>
            <?php endif; ?>
        </div>

        <!-- Maintenance Actions -->
        <div style="margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
            <form method="post" style="display: inline;">
                <button type="submit" name="cleanup_stuck" class="qd-btn qd-btn-warning" onclick="return confirm('تنظيف الوظائف العالقة؟')"><i class="fa fa-broom"></i> تنظيف العالقة</button>
            </form>
            <form method="post" style="display: inline;">
                <input type="number" name="older_than_days" value="7" min="1" max="90" style="width: 60px; padding: 4px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px;">
                <button type="submit" name="purge_completed" class="qd-btn qd-btn-danger" onclick="return confirm('حذف الوظائف المكتملة الأقدم من ' + document.querySelector('input[name=older_than_days]').value + ' يوم؟')"><i class="fa fa-trash"></i> تنظيف المكتملة</button>
            </form>
            <form method="post" style="display: inline;">
                <button type="submit" name="requeue_all_failed" class="qd-btn qd-btn-primary" onclick="return confirm('إعادة جميع الوظائف الفاشلة إلى قائمة الانتظار؟')"><i class="fa fa-refresh"></i> إعادة الفاشلة</button>
            </form>
            <form method="post" style="display: inline;">
                <button type="submit" name="purge_failed" class="qd-btn qd-btn-danger" onclick="return confirm('حذف جميع سجلات الفشل؟')"><i class="fa fa-trash"></i> حذف سجلات الفشل</button>
            </form>
        </div>
    </div>

    <!-- Average Processing Times -->
    <div class="qd-card">
        <div class="qd-section-head">
            <h3><i class="fa fa-clock-o" style="color: #ff9800;"></i> متوسط أوقات المعالجة</h3>
        </div>
        <div class="qd-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
            <?php foreach ($avg_times as $jt => $avg): ?>
            <div class="qd-stat">
                <h3 style="font-size: 18px;"><?php echo qd_format_ms($avg); ?></h3>
                <p><?php echo htmlspecialchars($job_types_list[$jt] ?? ($jt === 'all' ? 'جميع الأنواع' : $jt), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Job Queue Table -->
    <div class="qd-card">
        <div class="qd-section-head">
            <h3><i class="fa fa-list"></i> سجل الوظائف</h3>
            <span style="font-size: 13px; color: #a3aed1;"><?php echo $total_jobs; ?> إجمالي</span>
        </div>

        <!-- Filters -->
        <form class="qd-filter-form" method="get">
            <input type="hidden" name="page" value="1">
            <select name="status">
                <option value="">كل الحالات</option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                <option value="running" <?php echo $filter_status === 'running' ? 'selected' : ''; ?>>قيد التشغيل</option>
                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>فاشل</option>
                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
            </select>
            <select name="job_type">
                <option value="">كل الأنواع</option>
                <?php foreach ($job_types_list as $jt => $jt_label): ?>
                <option value="<?php echo htmlspecialchars($jt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_type === $jt ? 'selected' : ''; ?>><?php echo htmlspecialchars($jt_label, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority">
                <option value="">كل الأولويات</option>
                <?php foreach ($priority_list as $pk => $pl): ?>
                <option value="<?php echo htmlspecialchars($pk, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_priority === $pk ? 'selected' : ''; ?>><?php echo htmlspecialchars($pl, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="qd-btn qd-btn-primary qd-btn-sm"><i class="fa fa-filter"></i> تصفية</button>
            <a href="?" class="qd-btn qd-btn-outline qd-btn-sm"><i class="fa fa-times"></i> مسح</a>
        </form>

        <?php if (empty($jobs)): ?>
        <p style="color: #a3aed1; text-align: center; padding: 24px;">لا توجد وظائف في قائمة الانتظار.</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="qd-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>النوع</th>
                    <th>المتجر</th>
                    <th>الأولوية</th>
                    <th>الحالة</th>
                    <th>محاولات</th>
                    <th>تاريخ الإنشاء</th>
                    <th>آخر تحديث</th>
                    <th>الخطأ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $j): ?>
                <?php
                $j_type_label = $job_types_list[$j['job_type']] ?? $j['job_type'];
                $j_priority_label = $priority_list[$j['priority']] ?? $j['priority'];
                $last_time = $j['completed_at'] ?? $j['failed_at'] ?? $j['started_at'] ?? '';
                ?>
                <tr>
                    <td><?php echo (int) $j['id']; ?></td>
                    <td>
                        <span class="badge" style="background: #f4f0ff; color: #4318ff;"><?php echo htmlspecialchars($j_type_label, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td><?php echo $is_super_admin ? htmlspecialchars($j['store_name'] ?? "-", ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    <td>
                        <span class="badge" style="background: <?php
                            echo $j['priority'] === 'critical' ? '#f44336' : ($j['priority'] === 'high' ? '#ff9800' : ($j['priority'] === 'normal' ? '#4318ff' : '#607d8b'));
                        ?>; color: white;">
                            <?php echo htmlspecialchars($j_priority_label, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?php echo qd_status_badge($j['status']); ?></td>
                    <td><?php echo (int) $j['attempts']; ?>/<?php echo (int) $j['max_attempts']; ?></td>
                    <td style="font-size: 12px;"><?php echo date('Y-m-d H:i', strtotime($j['created_at'])); ?></td>
                    <td style="font-size: 12px;"><?php echo $last_time ? date('Y-m-d H:i', strtotime($last_time)) : '-'; ?></td>
                    <td>
                        <?php if ($j['error_message']): ?>
                        <span class="qd-truncate qd-monospace" style="color: #f44336;" title="<?php echo htmlspecialchars($j['error_message'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(mb_substr($j['error_message'], 0, 50), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($j['status'] === 'failed' || $j['status'] === 'cancelled'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="job_id" value="<?php echo (int) $j['id']; ?>">
                            <button type="submit" name="requeue_job" class="qd-btn qd-btn-success qd-btn-sm" title="إعادة إلى قائمة الانتظار"><i class="fa fa-repeat"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($j['status'] === 'failed'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="job_id" value="<?php echo (int) $j['id']; ?>">
                            <button type="submit" name="retry_job" class="qd-btn qd-btn-primary qd-btn-sm" title="إعادة المحاولة"><i class="fa fa-refresh"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($j['status'] === 'pending' || $j['status'] === 'running'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="job_id" value="<?php echo (int) $j['id']; ?>">
                            <button type="submit" name="cancel_job" class="qd-btn qd-btn-danger qd-btn-sm" title="إلغاء" onclick="return confirm('إلغاء هذه الوظيفة؟')"><i class="fa fa-ban"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="margin-top: 16px; display: flex; justify-content: center; gap: 4px;">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?page=<?php echo $p; ?>&status=<?php echo urlencode($filter_status); ?>&job_type=<?php echo urlencode($filter_type); ?>&priority=<?php echo urlencode($filter_priority); ?>"
               class="qd-btn <?php echo $p === $page ? 'qd-btn-primary' : 'qd-btn-outline'; ?> qd-btn-sm"
               style="min-width: 32px;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Failed Jobs -->
    <div class="qd-card">
        <div class="qd-section-head">
            <h3><i class="fa fa-exclamation-triangle" style="color: #f44336;"></i> سجلات الفشل</h3>
            <span style="font-size: 13px; color: #a3aed1;"><?php echo $total_failed; ?> إجمالي</span>
        </div>

        <?php if (empty($failed_jobs)): ?>
        <p style="color: #a3aed1; text-align: center; padding: 24px;">لا توجد سجلات فشل.</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="qd-table">
            <thead>
                <tr><th>ID</th><th>النوع</th><th>المتجر</th><th>المكون</th><th>محاولات</th><th>الخطأ</th><th>تاريخ الفشل</th><th>إجراءات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($failed_jobs as $fj): ?>
                <tr>
                    <td><?php echo (int) $fj['id']; ?></td>
                    <td>
                        <span class="badge" style="background: #ffebee; color: #f44336;">
                            <?php echo htmlspecialchars($job_types_list[$fj['job_type']] ?? $fj['job_type'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?php echo $is_super_admin ? htmlspecialchars($fj['store_name'] ?? "-", ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    <td style="font-size: 12px;"><?php echo htmlspecialchars($fj['component'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $fj['attempts']; ?></td>
                    <td>
                        <span class="qd-truncate qd-monospace" style="color: #f44336; max-width: 250px;" title="<?php echo htmlspecialchars($fj['error_message'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(mb_substr($fj['error_message'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td style="font-size: 12px;"><?php echo date('Y-m-d H:i', strtotime($fj['failed_at'] ?? $fj['created_at'])); ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="failed_id" value="<?php echo (int) $fj['id']; ?>">
                            <button type="submit" name="delete_failed" class="qd-btn qd-btn-danger qd-btn-sm" onclick="return confirm('حذف سجل الفشل؟')"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_failed_pages > 1): ?>
        <div style="margin-top: 16px; display: flex; justify-content: center; gap: 4px;">
            <?php for ($p = 1; $p <= $total_failed_pages; $p++): ?>
            <a href="?fpage=<?php echo $p; ?>"
               class="qd-btn <?php echo $p === $failed_page ? 'qd-btn-primary' : 'qd-btn-outline'; ?> qd-btn-sm"
               style="min-width: 32px;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Event Distribution -->
    <?php if (!empty($health['by_type'])): ?>
    <div class="qd-card">
        <div class="qd-section-head">
            <h3><i class="fa fa-pie-chart" style="color: #4318ff;"></i> توزيع الوظائف حسب النوع</h3>
        </div>
        <div class="qd-grid" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
            <?php foreach ($health['by_type'] as $bt): ?>
            <div class="qd-stat">
                <h3 style="font-size: 20px;"><?php echo (int) $bt['cnt']; ?></h3>
                <p><?php echo htmlspecialchars($job_types_list[$bt['job_type']] ?? $bt['job_type'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- How to run worker -->
    <div class="qd-card" style="background: #f8faff;">
        <div class="qd-section-head">
            <h3><i class="fa fa-info-circle" style="color: #4318ff;"></i> تشغيل العامل</h3>
        </div>
        <p style="font-size: 13px; color: #64748b;">قم بتشغيل العامل (worker) في الخلفية لمعالجة الوظائف:</p>
        <div style="background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 13px; direction: ltr; text-align: left;">
# تشغيل دائم في الخلفية (Linux/macOS):<br>
nohup php workers/queue-worker.php --daemon &gt; /dev/null 2&gt;&amp;1 &amp;<br><br>
# معالجة وظيفة واحدة:<br>
php workers/queue-worker.php --once<br><br>
# تشغيل نوع معين فقط:<br>
php workers/queue-worker.php telegram_send<br><br>
# إيقاف العامل:<br>
touch <?php echo htmlspecialchars(sys_get_temp_dir() . '/queue_worker_shutdown', ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
