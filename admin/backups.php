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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_backup'])) {
        $type = $_POST['backup_type'] ?? 'database';
        $scope = $_POST['scope'] ?? 'full';
        $scope_value = $_POST['scope_value'] ?? '';

        if ($type === 'database') {
            $job_id = store_enqueue_backup_database($pdo, $store_id, $scope, $scope_value);
        } elseif ($type === 'files') {
            $job_id = store_enqueue_backup_files($pdo, $store_id);
        } elseif ($type === 'store') {
            $job_id = store_enqueue_backup_store($pdo, $store_id);
        } else {
            $job_id = 0;
            $error = 'نوع النسخ غير معروف';
        }

        if ($job_id > 0) {
            $success = 'تم إضافة مهمة النسخ الاحتياطي إلى قائمة الانتظار.';
        } elseif (!$error) {
            $error = 'فشل إضافة مهمة النسخ.';
        }
    }

    if (isset($_POST['delete_backup'])) {
        $backup_id = (int) ($_POST['backup_id'] ?? 0);
        if ($backup_id > 0 && store_delete_backup_job($pdo, $backup_id)) {
            $success = 'تم حذف النسخة الاحتياطية.';
        } else {
            $error = 'فشل الحذف أو غير مصرح.';
        }
    }

    if (isset($_POST['request_restore'])) {
        $backup_id = (int) ($_POST['backup_id'] ?? 0);
        $notes = trim((string) ($_POST['restore_notes'] ?? ''));
        $admin_name = $_SESSION['user']['name'] ?? $_SESSION['store_user']['name'] ?? 'مستخدم';
        $admin_id = $_SESSION['user']['id'] ?? $_SESSION['store_user']['id'] ?? 0;

        if ($backup_id > 0) {
            $rid = store_create_restore_request($pdo, $backup_id, $store_id, $admin_name, $admin_id, $notes);
            if ($rid > 0) {
                $success = 'تم تقديم طلب الاستعادة. ينتظر موافقة المشرف.';
            } else {
                $error = 'فشل إنشاء طلب الاستعادة.';
            }
        }
    }

    if (isset($_POST['save_config'])) {
        $configs = [
            'retention_daily' => (string) ($_POST['retention_daily'] ?? '7'),
            'retention_weekly' => (string) ($_POST['retention_weekly'] ?? '4'),
            'retention_monthly' => (string) ($_POST['retention_monthly'] ?? '12'),
            's3_access_key' => trim((string) ($_POST['s3_access_key'] ?? '')),
            's3_secret_key' => trim((string) ($_POST['s3_secret_key'] ?? '')),
            's3_bucket' => trim((string) ($_POST['s3_bucket'] ?? '')),
            's3_endpoint' => trim((string) ($_POST['s3_endpoint'] ?? '')),
            's3_region' => trim((string) ($_POST['s3_region'] ?? '')),
            'auto_backup_enabled' => isset($_POST['auto_backup_enabled']) ? '1' : '0',
            'auto_backup_interval' => (string) ($_POST['auto_backup_interval'] ?? '24'),
        ];

        foreach ($configs as $key => $value) {
            store_set_backup_config($pdo, 0, $key, $value);
        }

        if (function_exists('audit_log')) {
            audit_log($pdo, [
                'entity_type' => 'backup_config',
                'entity_id' => 0,
                'action_type' => 'backup_config_updated',
                'source' => 'backup_dashboard',
            ]);
        }

        $success = 'تم حفظ الإعدادات.';
    }

    if (isset($_POST['apply_retention'])) {
        $result = store_apply_retention_policy($pdo, $is_super_admin ? null : $store_id);
        $success = sprintf(
            'تم تطبيق سياسة الاحتفاظ: حذف %d يومي + %d أسبوعي + %d شهري = %d إجمالي. تم تحرير %s.',
            $result['daily_deleted'], $result['weekly_deleted'], $result['monthly_deleted'],
            $result['total_deleted'], store_format_bytes($result['freed_bytes'])
        );
    }
}

// Load data
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$filters = [];
if (!$is_super_admin) {
    $filters['store_id'] = $store_id;
}
if ($filter_type !== '') $filters['backup_type'] = $filter_type;
if ($filter_status !== '') $filters['status'] = $filter_status;

$backups = store_get_backup_jobs($pdo, $filters, $limit, $offset);
$total = store_get_backup_job_count($pdo, $filters);
$total_pages = max(1, ceil($total / $limit));

$health = store_get_backup_health($pdo, $is_super_admin ? null : $store_id);

$type_labels = store_get_backup_type_labels();
$scope_labels = store_get_backup_scope_labels();
$status_labels = store_get_backup_status_labels();

// Config values (from store_id=0 for global)
$config_keys = ['retention_daily', 'retention_weekly', 'retention_monthly', 's3_access_key', 's3_secret_key', 's3_bucket', 's3_endpoint', 's3_region', 'auto_backup_enabled', 'auto_backup_interval'];
$config = [];
foreach ($config_keys as $ck) {
    $config[$ck] = store_get_backup_config($pdo, 0, $ck, '');
}

function bu_status_badge(string $status): string {
    $map = [
        'pending' => ['bg-info', 'قيد الانتظار'],
        'running' => ['bg-warning', 'قيد التشغيل'],
        'completed' => ['bg-success', 'مكتمل'],
        'failed' => ['bg-danger', 'فاشل'],
    ];
    $cls = $map[$status][0] ?? 'bg-secondary';
    $label = $map[$status][1] ?? $status;
    return "<span class=\"badge {$cls}\">{$label}</span>";
}
?>

<style>
.bu-dash { font-family: 'Cairo', 'Outfit', sans-serif; padding: 24px; direction: rtl; text-align: right; color: #1b2559; }
.bu-card { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(112,144,176,0.12); margin-bottom: 24px; }
.bu-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.bu-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.bu-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
.bu-stat { padding: 18px; background: #f8faff; border-radius: 14px; text-align: center; }
.bu-stat h3 { font-size: 24px; font-weight: 800; margin: 0; }
.bu-stat p { font-size: 12px; color: #a3aed1; margin: 4px 0 0; }
.bu-table { width: 100%; border-collapse: collapse; }
.bu-table th { text-align: right; padding: 10px 8px; font-size: 12px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.bu-table td { padding: 10px 8px; font-size: 13px; border-bottom: 1px solid #f4f7fe; vertical-align: middle; }
.bu-table tr:hover td { background: #f8faff; }
.bu-btn { display: inline-block; padding: 6px 14px; border-radius: 8px; font-weight: 700; font-size: 12px; text-decoration: none; border: none; cursor: pointer; }
.bu-btn-primary { background: #4318ff; color: white; }
.bu-btn-success { background: #05cd99; color: white; }
.bu-btn-danger { background: #f44336; color: white; }
.bu-btn-warning { background: #ff9800; color: white; }
.bu-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.bu-btn-sm { padding: 4px 8px; font-size: 11px; }
.bu-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.bu-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.bu-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.bu-alert-success { background: #e6f9ed; color: #166534; border: 1px solid #bbf7d0; }
.bu-alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
.bu-monospace { font-family: 'Consolas', 'Courier New', monospace; direction: ltr; text-align: left; font-size: 12px; }
.bu-truncate { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
</style>

<div class="bu-dash">
    <h1 class="bu-title"><i class="fa fa-database" style="color: #4318ff;"></i> النسخ الاحتياطي</h1>
    <p class="bu-subtitle">إدارة النسخ الاحتياطية واستعادة البيانات — <?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($error): ?><div class="bu-alert bu-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="bu-alert bu-alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="bu-card">
        <div class="bu-grid">
            <div class="bu-stat"><h3 style="color: #05cd99;"><?php echo $health['completed_30d']; ?></h3><p>ناجح (30 يوم)</p></div>
            <div class="bu-stat"><h3 style="color: #f44336;"><?php echo $health['failed_30d']; ?></h3><p>فاشل (30 يوم)</p></div>
            <div class="bu-stat"><h3 style="color: #4318ff;"><?php echo $health['success_rate_30d']; ?>%</h3><p>معدل النجاح</p></div>
            <div class="bu-stat"><h3 style="color: #ff9800;"><?php echo $health['storage_used_formatted']; ?></h3><p>المساحة المستخدمة</p></div>
            <div class="bu-stat"><h3 style="color: #9c27b0;"><?php echo $health['total_backups']; ?></h3><p>إجمالي النسخ</p></div>
        </div>
        <?php if ($health['last_backup_time']): ?>
        <div style="text-align: center; margin-top: 12px; font-size: 13px; color: #64748b;">
            <i class="fa fa-clock-o"></i> آخر نسخة: <?php echo date('Y-m-d H:i', strtotime($health['last_backup_time'])); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create Backup -->
    <div class="bu-card">
        <div class="bu-section-head">
            <h3><i class="fa fa-plus-circle" style="color: #05cd99;"></i> إنشاء نسخة احتياطية جديدة</h3>
        </div>
        <form method="post" class="row">
            <div class="col-md-3">
                <label>النوع</label>
                <select name="backup_type" class="form-control" id="bu-type">
                    <option value="database">قاعدة البيانات</option>
                    <option value="files">الملفات</option>
                    <option value="store">المتجر كاملاً (قاعدة بيانات + ملفات)</option>
                </select>
            </div>
            <div class="col-md-3" id="bu-scope-group">
                <label>النطاق</label>
                <select name="scope" class="form-control">
                    <option value="full">كاملم</option>
                    <option value="tables">جداول محددة</option>
                    <option value="store">متجر محدد</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>قيمة النطاق (اختياري)</label>
                <input type="text" name="scope_value" class="form-control" placeholder="معرف المتجر أو أسماء الجداول">
            </div>
            <div class="col-md-3" style="padding-top: 22px;">
                <button type="submit" name="create_backup" class="bu-btn bu-btn-success"><i class="fa fa-play"></i> بدء النسخ</button>
            </div>
        </form>
        <p style="font-size: 12px; color: #a3aed1; margin-top: 8px;">سيتم إضافة المهمة إلى قائمة الانتظار للمعالجة في الخلفية.</p>
    </div>

    <!-- Config (super admin) -->
    <?php if ($is_super_admin): ?>
    <div class="bu-card">
        <div class="bu-section-head">
            <h3><i class="fa fa-cog" style="color: #607d8b;"></i> إعدادات النسخ الاحتياطي</h3>
        </div>
        <form method="post">
            <div class="row">
                <div class="col-md-4">
                    <label>الاحتفاظ اليومي (أيام)</label>
                    <input type="number" name="retention_daily" class="form-control" value="<?php echo htmlspecialchars($config['retention_daily'] ?: '7', ENT_QUOTES, 'UTF-8'); ?>" min="1" max="90">
                </div>
                <div class="col-md-4">
                    <label>الاحتفاظ الأسبوعي (أسابيع)</label>
                    <input type="number" name="retention_weekly" class="form-control" value="<?php echo htmlspecialchars($config['retention_weekly'] ?: '4', ENT_QUOTES, 'UTF-8'); ?>" min="1" max="52">
                </div>
                <div class="col-md-4">
                    <label>الاحتفاظ الشهري (أشهر)</label>
                    <input type="number" name="retention_monthly" class="form-control" value="<?php echo htmlspecialchars($config['retention_monthly'] ?: '12', ENT_QUOTES, 'UTF-8'); ?>" min="1" max="120">
                </div>
            </div>
            <hr style="border-color: #e2e8f0; margin: 16px 0;">
            <h4 style="font-size: 14px; font-weight: 700;">تخزين S3 (اختياري)</h4>
            <div class="row">
                <div class="col-md-4">
                    <label>Access Key</label>
                    <input type="text" name="s3_access_key" class="form-control" value="<?php echo htmlspecialchars($config['s3_access_key'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label>Secret Key</label>
                    <input type="password" name="s3_secret_key" class="form-control" value="<?php echo htmlspecialchars($config['s3_secret_key'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label>Bucket</label>
                    <input type="text" name="s3_bucket" class="form-control" value="<?php echo htmlspecialchars($config['s3_bucket'] ?: 'backups', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label>Endpoint (لـ S3 متوافق)</label>
                    <input type="text" name="s3_endpoint" class="form-control" value="<?php echo htmlspecialchars($config['s3_endpoint'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://s3.example.com">
                </div>
                <div class="col-md-4">
                    <label>Region</label>
                    <input type="text" name="s3_region" class="form-control" value="<?php echo htmlspecialchars($config['s3_region'] ?: 'us-east-1', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <hr style="border-color: #e2e8f0; margin: 16px 0;">
            <div class="row">
                <div class="col-md-4">
                    <label>
                        <input type="checkbox" name="auto_backup_enabled" value="1" <?php echo $config['auto_backup_enabled'] === '1' ? 'checked' : ''; ?>>
                        تفعيل النسخ التلقائي
                    </label>
                </div>
                <div class="col-md-4">
                    <label>الفاصل (ساعات)</label>
                    <select name="auto_backup_interval" class="form-control">
                        <option value="6" <?php echo $config['auto_backup_interval'] === '6' ? 'selected' : ''; ?>>كل 6 ساعات</option>
                        <option value="12" <?php echo $config['auto_backup_interval'] === '12' ? 'selected' : ''; ?>>كل 12 ساعة</option>
                        <option value="24" <?php echo $config['auto_backup_interval'] === '24' ? 'selected' : ''; ?>>كل 24 ساعة</option>
                        <option value="48" <?php echo $config['auto_backup_interval'] === '48' ? 'selected' : ''; ?>>كل 48 ساعة</option>
                        <option value="72" <?php echo $config['auto_backup_interval'] === '72' ? 'selected' : ''; ?>>كل 72 ساعة</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 16px; display: flex; gap: 8px;">
                <button type="submit" name="save_config" class="bu-btn bu-btn-primary"><i class="fa fa-save"></i> حفظ الإعدادات</button>
                <button type="submit" name="apply_retention" class="bu-btn bu-btn-warning" onclick="return confirm('تطبيق سياسة الاحتفاظ وحذف النسخ القديمة؟')"><i class="fa fa-trash"></i> تطبيق سياسة الاحتفاظ</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Backup History -->
    <div class="bu-card">
        <div class="bu-section-head">
            <h3><i class="fa fa-history"></i> سجل النسخ الاحتياطي</h3>
            <span style="font-size: 13px; color: #a3aed1;"><?php echo $total; ?> إجمالي</span>
        </div>

        <form class="form-inline" style="margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
            <select name="type" class="form-control" style="width: auto;">
                <option value="">كل الأنواع</option>
                <?php foreach ($type_labels as $tk => $tl): ?>
                <option value="<?php echo $tk; ?>" <?php echo $filter_type === $tk ? 'selected' : ''; ?>><?php echo $tl; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width: auto;">
                <option value="">كل الحالات</option>
                <?php foreach ($status_labels as $sk => $sl): ?>
                <option value="<?php echo $sk; ?>" <?php echo $filter_status === $sk ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bu-btn bu-btn-primary bu-btn-sm"><i class="fa fa-filter"></i> تصفية</button>
            <a href="?" class="bu-btn bu-btn-outline bu-btn-sm"><i class="fa fa-times"></i> مسح</a>
        </form>

        <?php if (empty($backups)): ?>
        <p style="color: #a3aed1; text-align: center; padding: 24px;">لا توجد نسخ احتياطية بعد. قم بإنشاء أول نسخة أعلاه.</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="bu-table">
            <thead>
                <tr><th>ID</th><th>النوع</th><th>النطاق</th><th>الحالة</th><th>الحجم</th><th>التخزين</th><th>تاريخ الإنشاء</th><th>مدة التنفيذ</th><th>الخطأ</th><th>إجراءات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $b): ?>
                <?php
                $duration = '';
                if ($b['started_at'] && $b['completed_at']) {
                    $diff = strtotime($b['completed_at']) - strtotime($b['started_at']);
                    $duration = $diff > 60 ? round($diff / 60) . 'د' : $diff . 'ث';
                }
                ?>
                <tr>
                    <td><?php echo (int) $b['id']; ?></td>
                    <td><span class="badge" style="background: #f4f0ff; color: #4318ff;"><?php echo htmlspecialchars($type_labels[$b['backup_type']] ?? $b['backup_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?php echo htmlspecialchars($scope_labels[$b['scope']] ?? $b['scope'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo bu_status_badge($b['status']); ?></td>
                    <td><?php echo $b['file_size'] > 0 ? store_format_bytes((int) $b['file_size']) : '-'; ?></td>
                    <td><span class="badge" style="background: #e8f4ff; color: #1e3a5f;"><?php echo htmlspecialchars($b['storage_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td style="font-size: 12px;"><?php echo date('Y-m-d H:i', strtotime($b['created_at'])); ?></td>
                    <td style="font-size: 12px;"><?php echo $duration ?: '-'; ?></td>
                    <td>
                        <?php if ($b['error_message']): ?>
                        <span class="bu-truncate bu-monospace" style="color: #f44336;" title="<?php echo htmlspecialchars($b['error_message'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(mb_substr($b['error_message'], 0, 40), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'completed' && $b['file_path'] && file_exists($b['file_path'])): ?>
                        <a href="?download=<?php echo (int) $b['id']; ?>" class="bu-btn bu-btn-success bu-btn-sm" title="تحميل"><i class="fa fa-download"></i></a>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="backup_id" value="<?php echo (int) $b['id']; ?>">
                            <button type="submit" name="request_restore" class="bu-btn bu-btn-warning bu-btn-sm" title="طلب استعادة" onclick="return confirm('طلب استعادة هذه النسخة؟ يحتاج إلى موافقة المشرف.')"><i class="fa fa-undo"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($is_super_admin || $b['status'] === 'failed'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="backup_id" value="<?php echo (int) $b['id']; ?>">
                            <button type="submit" name="delete_backup" class="bu-btn bu-btn-danger bu-btn-sm" title="حذف" onclick="return confirm('حذف هذه النسخة؟')"><i class="fa fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div style="margin-top: 16px; display: flex; justify-content: center; gap: 4px;">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?page=<?php echo $p; ?>&type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>"
               class="bu-btn <?php echo $p === $page ? 'bu-btn-primary' : 'bu-btn-outline'; ?> bu-btn-sm" style="min-width: 32px;">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Handle download
if (isset($_GET['download'])) {
    $backup_id = (int) $_GET['download'];
    if (!$is_super_admin) {
        store_backup_download($pdo, $backup_id, $store_id);
    } else {
        $job = store_get_backup_job($pdo, $backup_id);
        if ($job && file_exists($job['file_path'])) {
            store_backup_download($pdo, $backup_id, (int) $job['store_id']);
        }
    }
}
?>
<?php require_once('footer.php'); ?>
