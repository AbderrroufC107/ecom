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

$error = '';
$success = '';

// Handle restore actions (super admin only)
if ($is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_restore'])) {
        $req_id = (int) ($_POST['request_id'] ?? 0);
        $admin_name = $_SESSION['user']['name'] ?? 'مشرف';
        $admin_id = (int) ($_SESSION['user']['id'] ?? 0);
        if ($req_id > 0 && store_approve_restore_request($pdo, $req_id, $admin_name, $admin_id)) {
            $success = 'تمت الموافقة على طلب الاستعادة.';
        } else {
            $error = 'فشل الموافقة على الطلب.';
        }
    }

    if (isset($_POST['reject_restore'])) {
        $req_id = (int) ($_POST['request_id'] ?? 0);
        $notes = trim((string) ($_POST['reject_reason'] ?? ''));
        if ($req_id > 0 && store_reject_restore_request($pdo, $req_id, $notes)) {
            $success = 'تم رفض طلب الاستعادة.';
        } else {
            $error = 'فشل رفض الطلب.';
        }
    }

    if (isset($_POST['execute_restore'])) {
        $req_id = (int) ($_POST['request_id'] ?? 0);
        $req = store_get_restore_request($pdo, $req_id);
        if (!$req || $req['status'] !== 'approved') {
            $error = 'الطلب غير موجود أو لم تتم الموافقة عليه بعد.';
        } else {
            // Execute restore from backup file
            $backup_job = store_get_backup_job($pdo, (int) $req['backup_id']);
            if (!$backup_job || !file_exists($backup_job['file_path'])) {
                $error = 'ملف النسخة غير موجود.';
            } else {
                try {
                    $dbname = defined('DB_NAME') ? DB_NAME : 'ecom';
                    $dbuser = defined('DB_USER') ? DB_USER : 'root';
                    $dbpass = defined('DB_PASS') ? DB_PASS : '';
                    $dbhost = defined('DB_HOST') ? DB_HOST : 'localhost';

                    $filepath = $backup_job['file_path'];
                    // For .sql.gz files
                    if (strpos($filepath, '.gz') !== false) {
                        $cmd = sprintf(
                            'gunzip -c %s | mysql --host=%s --user=%s --password=%s %s 2>&1',
                            escapeshellarg($filepath),
                            escapeshellarg($dbhost),
                            escapeshellarg($dbuser),
                            escapeshellarg($dbpass),
                            escapeshellarg($dbname)
                        );
                    } else {
                        $cmd = sprintf(
                            'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
                            escapeshellarg($dbhost),
                            escapeshellarg($dbuser),
                            escapeshellarg($dbpass),
                            escapeshellarg($dbname),
                            escapeshellarg($filepath)
                        );
                    }

                    $output = [];
                    $return_var = 0;
                    exec($cmd, $output, $return_var);

                    if ($return_var === 0) {
                        store_update_restore_request($pdo, $req_id, [
                            'status' => 'executed',
                            'executed_at' => date('Y-m-d H:i:s'),
                        ]);

                        if (function_exists('audit_log')) {
                            audit_log($pdo, [
                                'entity_type' => 'restore_request',
                                'entity_id' => $req_id,
                                'action_type' => 'restore_executed',
                                'old_value' => 'approved',
                                'new_value' => 'executed',
                                'source' => 'disaster_recovery',
                            ]);
                        }

                        $success = 'تم تنفيذ الاستعادة بنجاح!';
                    } else {
                        $error = 'فشل الاستعادة: ' . implode("\n", $output);
                    }
                } catch (Exception $e) {
                    $error = 'استثناء: ' . $e->getMessage();
                }
            }
        }
    }

    if (isset($_POST['trigger_backup_alert'])) {
        $result = store_check_backup_alerts($pdo);
        if ($result['alerts_sent'] > 0) {
            $success = "تم إرسال {$result['alerts_sent']} تنبيه(ات).";
        } else {
            $success = 'لا توجد مشاكل حالياً — النظام سليم.';
        }
    }
}

// Load data
$health = store_get_backup_health($pdo, $is_super_admin ? null : $store_id);
$storage_summary = store_get_backup_storage_summary($pdo);

// Restore requests
$req_page = max(1, (int) ($_GET['rpage'] ?? 1));
$req_limit = 20;
$req_offset = ($req_page - 1) * $req_limit;
$req_filters = ['status' => $_GET['rstatus'] ?? ''];
if (!$is_super_admin) {
    $req_filters['store_id'] = $store_id;
}
$requests = store_get_restore_requests($pdo, $req_filters, $req_limit, $req_offset);
$total_requests = store_get_restore_request_count($pdo, $req_filters);
$total_req_pages = max(1, ceil($total_requests / $req_limit));

$type_labels = store_get_backup_type_labels();
$status_labels = store_get_backup_status_labels();

function dr_status_badge(string $status): string {
    $map = [
        'pending' => ['bg-info', 'قيد الانتظار'],
        'approved' => ['bg-success', 'تمت الموافقة'],
        'rejected' => ['bg-danger', 'مرفوض'],
        'executed' => ['bg-primary', 'تم التنفيذ'],
    ];
    $cls = $map[$status][0] ?? 'bg-secondary';
    $label = $map[$status][1] ?? $status;
    return "<span class=\"badge {$cls}\">{$label}</span>";
}
?>

<style>
.dr-dash { font-family: 'Cairo', 'Outfit', sans-serif; padding: 24px; direction: rtl; text-align: right; color: #1b2559; }
.dr-card { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(112,144,176,0.12); margin-bottom: 24px; }
.dr-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.dr-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.dr-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
.dr-stat { padding: 18px; background: #f8faff; border-radius: 14px; text-align: center; }
.dr-stat h3 { font-size: 24px; font-weight: 800; margin: 0; }
.dr-stat p { font-size: 12px; color: #a3aed1; margin: 4px 0 0; }
.dr-table { width: 100%; border-collapse: collapse; }
.dr-table th { text-align: right; padding: 10px 8px; font-size: 12px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.dr-table td { padding: 10px 8px; font-size: 13px; border-bottom: 1px solid #f4f7fe; vertical-align: middle; }
.dr-table tr:hover td { background: #f8faff; }
.dr-btn { display: inline-block; padding: 6px 14px; border-radius: 8px; font-weight: 700; font-size: 12px; text-decoration: none; border: none; cursor: pointer; }
.dr-btn-primary { background: #4318ff; color: white; }
.dr-btn-success { background: #05cd99; color: white; }
.dr-btn-danger { background: #f44336; color: white; }
.dr-btn-warning { background: #ff9800; color: white; }
.dr-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.dr-btn-sm { padding: 4px 8px; font-size: 11px; }
.dr-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.dr-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.dr-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.dr-alert-success { background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; }
.dr-alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
.dr-health-card { padding: 16px 20px; border-radius: 14px; margin-bottom: 12px; display: flex; align-items: flex-start; gap: 12px; }
.dr-health-ok { background: #e6f9ed; border: 1px solid #bbf7d0; }
.dr-health-warn { background: #fff3cd; border: 1px solid #ffeeba; }
.dr-health-bad { background: #ffebee; border: 1px solid #ffcdd2; }
.dr-monospace { font-family: 'Consolas', 'Courier New', monospace; direction: ltr; text-align: left; font-size: 12px; }
</style>

<div class="dr-dash">
    <h1 class="dr-title"><i class="fa fa-shield" style="color: #f44336;"></i> التعافي من الكوارث</h1>
    <p class="dr-subtitle">مراقبة صحة النظام وإدارة طلبات الاستعادة — <?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($error): ?><div class="dr-alert dr-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="dr-alert dr-alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <!-- Health Checks -->
    <div class="dr-card">
        <div class="dr-section-head">
            <h3><i class="fa fa-heartbeat" style="color: #05cd99;"></i> فحوصات الصحة</h3>
            <?php if ($is_super_admin): ?>
            <form method="post" style="display: inline;">
                <button type="submit" name="trigger_backup_alert" class="dr-btn dr-btn-outline dr-btn-sm"><i class="fa fa-bell"></i> فحص التنبيهات</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Last Backup Status -->
        <?php
        $no_backup = !$health['last_backup_time'];
        $old_backup = false;
        $backup_ok = false;
        if ($health['last_backup_time']) {
            $hours_since = (time() - strtotime($health['last_backup_time'])) / 3600;
            $old_backup = $hours_since > 24;
            $backup_ok = !$old_backup;
        }
        ?>
        <div class="dr-health-card <?php echo $backup_ok ? 'dr-health-ok' : ($no_backup ? 'dr-health-bad' : 'dr-health-warn'); ?>">
            <div style="font-size: 24px;"><?php echo $backup_ok ? '✅' : ($no_backup ? '❌' : '⚠️'); ?></div>
            <div>
                <strong>آخر نسخة احتياطية</strong>
                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                    <?php
                    if ($health['last_backup_time']) {
                        echo date('Y-m-d H:i:s', strtotime($health['last_backup_time']));
                        $hours_since = (time() - strtotime($health['last_backup_time'])) / 3600;
                        echo " (منذ " . round($hours_since, 1) . " ساعة)";
                    } else {
                        echo "لا توجد نسخ احتياطية بعد!";
                    }
                    ?>
                </p>
            </div>
        </div>

        <!-- Success Rate -->
        <div class="dr-health-card <?php echo $health['success_rate_30d'] >= 80 ? 'dr-health-ok' : ($health['success_rate_30d'] > 0 ? 'dr-health-warn' : 'dr-health-bad'); ?>">
            <div style="font-size: 24px;"><?php echo $health['success_rate_30d'] >= 80 ? '✅' : ($health['success_rate_30d'] > 0 ? '⚠️' : '❌'); ?></div>
            <div>
                <strong>معدل نجاح النسخ (30 يوم)</strong>
                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                    <?php echo $health['success_rate_30d']; ?>%
                    (<?php echo $health['completed_30d']; ?> ناجح / <?php echo $health['failed_30d']; ?> فاشل / <?php echo $health['total_30d']; ?> إجمالي)
                </p>
            </div>
        </div>

        <!-- Storage -->
        <div class="dr-health-card dr-health-ok">
            <div style="font-size: 24px;">💾</div>
            <div>
                <strong>استخدام التخزين</strong>
                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                    <?php echo $health['storage_used_formatted']; ?> — إجمالي <?php echo $health['total_backups']; ?> نسخة
                </p>
            </div>
        </div>

        <!-- Recent Failures -->
        <?php if (!empty($health['recent_failures'])): ?>
        <div class="dr-health-card dr-health-bad">
            <div style="font-size: 24px;">🔴</div>
            <div style="flex: 1;">
                <strong>حالات فشل حديثة (آخر 10)</strong>
                <ul style="margin: 4px 0 0; padding-right: 16px; font-size: 13px; color: #c62828;">
                    <?php foreach ($health['recent_failures'] as $fail): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($type_labels[$fail['backup_type']] ?? $fail['backup_type'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        (<?php echo date('Y-m-d H:i', strtotime($fail['created_at'])); ?>):
                        <?php echo htmlspecialchars(mb_substr($fail['error_message'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8'); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Storage Summary -->
    <div class="dr-card">
        <div class="dr-section-head">
            <h3><i class="fa fa-pie-chart" style="color: #4318ff;"></i> ملخص التخزين</h3>
        </div>
        <div class="dr-grid">
            <?php foreach ($storage_summary['by_type'] ?? [] as $st): ?>
            <div class="dr-stat">
                <h3><?php echo htmlspecialchars($type_labels[$st['backup_type']] ?? $st['backup_type'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo (int) $st['count']; ?> نسخة / <?php echo store_format_bytes((int) $st['total_size']); ?></p>
            </div>
            <?php endforeach; ?>
            <div class="dr-stat">
                <h3 style="color: #4318ff;"><?php echo $storage_summary['total_size_formatted'] ?? '0 B'; ?></h3>
                <p>إجمالي المساحة / <?php echo $storage_summary['total_files'] ?? 0; ?> ملف</p>
            </div>
        </div>
        <?php if (!empty($storage_summary['by_storage'])): ?>
        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
            <?php foreach ($storage_summary['by_storage'] as $sb): ?>
            <span class="badge" style="background: #f4f0ff; color: #4318ff;">
                <?php echo htmlspecialchars($sb['storage_type'], ENT_QUOTES, 'UTF-8'); ?>:
                <?php echo (int) $sb['count']; ?> نسخة / <?php echo store_format_bytes((int) $sb['total_size']); ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Restore Requests -->
    <div class="dr-card">
        <div class="dr-section-head">
            <h3><i class="fa fa-undo" style="color: #ff9800;"></i> طلبات الاستعادة</h3>
            <span style="font-size: 13px; color: #a3aed1;"><?php echo $total_requests; ?> إجمالي</span>
        </div>

        <form class="form-inline" style="margin-bottom: 16px; display: flex; gap: 8px;">
            <select name="rstatus" class="form-control" style="width: auto;">
                <option value="">كل الحالات</option>
                <option value="pending" <?php echo ($_GET['rstatus'] ?? '') === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                <option value="approved" <?php echo ($_GET['rstatus'] ?? '') === 'approved' ? 'selected' : ''; ?>>تمت الموافقة</option>
                <option value="rejected" <?php echo ($_GET['rstatus'] ?? '') === 'rejected' ? 'selected' : ''; ?>>مرفوض</option>
                <option value="executed" <?php echo ($_GET['rstatus'] ?? '') === 'executed' ? 'selected' : ''; ?>>تم التنفيذ</option>
            </select>
            <button type="submit" class="dr-btn dr-btn-primary dr-btn-sm"><i class="fa fa-filter"></i> تصفية</button>
            <a href="?" class="dr-btn dr-btn-outline dr-btn-sm"><i class="fa fa-times"></i> مسح</a>
        </form>

        <?php if (empty($requests)): ?>
        <p style="color: #a3aed1; text-align: center; padding: 24px;">لا توجد طلبات استعادة.</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="dr-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>النوع</th>
                    <th>المتجر</th>
                    <th>مقدم الطلب</th>
                    <th>الحالة</th>
                    <th>الموافقة</th>
                    <th>ملاحظات</th>
                    <th>التاريخ</th>
                    <?php if ($is_super_admin): ?>
                    <th>إجراءات</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?php echo (int) $r['id']; ?></td>
                    <td><span class="badge" style="background: #f4f0ff; color: #4318ff;"><?php echo htmlspecialchars($type_labels[$r['backup_type']] ?? $r['backup_type'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?php echo htmlspecialchars($r['store_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($r['requested_by'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo dr_status_badge($r['status']); ?></td>
                    <td><?php echo $r['approved_by'] ? htmlspecialchars($r['approved_by'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    <td><span class="dr-monospace" style="color: #64748b;"><?php echo htmlspecialchars(mb_substr($r['notes'] ?? '', 0, 40), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td style="font-size: 12px;"><?php echo date('Y-m-d H:i', strtotime($r['created_at'])); ?></td>
                    <?php if ($is_super_admin): ?>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" name="approve_restore" class="dr-btn dr-btn-success dr-btn-sm" onclick="return confirm('الموافقة على طلب الاستعادة؟')"><i class="fa fa-check"></i></button>
                            <button type="submit" name="reject_restore" class="dr-btn dr-btn-danger dr-btn-sm" onclick="return confirm('رفض طلب الاستعادة؟')"><i class="fa fa-times"></i></button>
                        </form>
                        <?php elseif ($r['status'] === 'approved'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" name="execute_restore" class="dr-btn dr-btn-primary dr-btn-sm" onclick="return confirm('تنفيذ الاستعادة الآن؟ هذا سيؤدي إلى استبدال قاعدة البيانات الحالية!')"><i class="fa fa-play"></i> تنفيذ</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_req_pages > 1): ?>
        <div style="margin-top: 16px; display: flex; justify-content: center; gap: 4px;">
            <?php for ($p = 1; $p <= $total_req_pages; $p++): ?>
            <a href="?rpage=<?php echo $p; ?>&rstatus=<?php echo urlencode($_GET['rstatus'] ?? ''); ?>"
               class="dr-btn <?php echo $p === $req_page ? 'dr-btn-primary' : 'dr-btn-outline'; ?> dr-btn-sm" style="min-width: 32px;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="dr-card" style="background: #f8faff;">
        <div class="dr-section-head">
            <h3><i class="fa fa-link" style="color: #4318ff;"></i> روابط سريعة</h3>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="backups.php" class="dr-btn dr-btn-primary"><i class="fa fa-database"></i> إدارة النسخ الاحتياطي</a>
            <a href="queue-dashboard.php" class="dr-btn dr-btn-outline"><i class="fa fa-tasks"></i> لوحة قائمة الانتظار</a>
            <a href="../workers/queue-worker.php --help" class="dr-btn dr-btn-outline"><i class="fa fa-terminal"></i> تشغيل العامل</a>
        </div>
        <div style="margin-top: 12px; background: #0f172a; color: #e2e8f0; padding: 12px 16px; border-radius: 10px; font-family: monospace; font-size: 12px; direction: ltr; text-align: left;">
# فحص الصحة يدوياً:<br>
php -r "require 'admin/inc/store.php'; \$h=store_get_backup_health(\$pdo); print_r(\$h);"<br><br>
# تشغيل النسخ الاحتياطي:<br>
php workers/queue-worker.php backup_database --once
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
