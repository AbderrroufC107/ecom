<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'inc/integration/N8nManager.php';

use Integration\N8nManager;

// Ensure tables exist
N8nManager::ensureTables($pdo);

$success = '';
$error = '';

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int) ($_POST['id'] ?? 0);
        $env         = in_array($_POST['environment'] ?? '', ['production','staging','development']) ? $_POST['environment'] : 'production';
        $label       = trim(substr($_POST['label'] ?? 'Default', 0, 100));
        $base_url    = rtrim(trim($_POST['base_url'] ?? ''), '/');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        // Webhook paths as JSON
        $paths = [];
        foreach (['ai_agent','product_sync','provider_manager','customer360','order_events','analytics','notifications'] as $k) {
            $v = trim($_POST['webhook_' . $k] ?? '');
            if ($v !== '') $paths[$k] = '/' . ltrim($v, '/');
        }

        // API Key: encrypt only if non-empty and changed (not masked)
        $api_key_raw = trim($_POST['api_key'] ?? '');
        if ($api_key_raw === '' || $api_key_raw === '••••••••') {
            // Keep existing
            $stmt = $dbRepo->prepare("SELECT api_key FROM tbl_n8n_integrations WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetchColumn();
            $api_key_encrypted = $existing ?: '';
        } else {
            $api_key_encrypted = N8nManager::encryptApiKey($api_key_raw);
        }

        if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
            $error = 'رابط Base URL غير صالح. مثال: https://thikastore.app.n8n.cloud';
        } else {
            try {
                if ($id > 0) {
                    $dbRepo->prepare("UPDATE tbl_n8n_integrations SET environment=?, label=?, base_url=?, webhook_paths=?, api_key=?, is_active=?, updated_at=NOW() WHERE id=?")
                        ->execute([$env, $label, $base_url, json_encode($paths), $api_key_encrypted, $is_active, $id]);
                } else {
                    $dbRepo->prepare("INSERT INTO tbl_n8n_integrations (environment, label, base_url, webhook_paths, api_key, is_active) VALUES (?,?,?,?,?,?)")
                        ->execute([$env, $label, $base_url, json_encode($paths), $api_key_encrypted, $is_active]);
                }
                $success = '✅ تم حفظ إعدادات التكامل بنجاح.';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                    $error = '❌ خطأ: يوجد تكامل مسجل مسبقاً بنفس البيئة والاسم (Label). الرجاء تغيير الاسم لتجنب التكرار.';
                } else {
                    $error = '❌ خطأ في قاعدة البيانات: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $dbRepo->prepare("DELETE FROM tbl_n8n_integrations WHERE id=?")->execute([$id]);
            $success = '✅ تم حذف التكامل.';
        }
    }

    if ($action === 'test') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $dbRepo->prepare("SELECT environment FROM tbl_n8n_integrations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $manager = new N8nManager($pdo, $row['environment']);
            $ping = $manager->ping();
            $status = $ping['online'] ? 'online' : 'offline';
            $dbRepo->prepare("UPDATE tbl_n8n_integrations SET last_tested=NOW(), last_status=? WHERE id=?")->execute([$status, $id]);
            if ($ping['online']) {
                $success = "✅ الاتصال ناجح! زمن الاستجابة: {$ping['duration']}ms";
            } else {
                $error = "❌ فشل الاتصال: " . ($ping['error'] ?? "HTTP {$ping['http_code']}");
            }
        }
    }
}

// ─── Load Records ─────────────────────────────────────────────────────────────
$stmtIntegrations = $dbRepo->prepare("SELECT * FROM tbl_n8n_integrations ORDER BY environment, label");
$stmtIntegrations->execute();
$integrations = $stmtIntegrations->fetchAll(PDO::FETCH_ASSOC);

// Debug:
error_log("Integrations count: " . count($integrations));
if(empty($integrations)) error_log("Integrations is empty! Query was SELECT * FROM tbl_n8n_integrations");

// Recent call logs
$stmtLogs = $dbRepo->prepare("SELECT * FROM tbl_n8n_call_log ORDER BY id DESC LIMIT 30");
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// Default webhook paths for the form
$defaultPaths = [
    'ai_agent'         => '/webhook/ai-sales-agent-v2',
    'product_sync'     => '/webhook/product-sync',
    'provider_manager' => '/webhook/provider-manager',
    'customer360'      => '/webhook/customer360',
    'order_events'     => '/webhook/order-events',
    'analytics'        => '/webhook/analytics',
    'notifications'    => '/webhook/notifications',
];

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $dbRepo->prepare("SELECT * FROM tbl_n8n_integrations WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editRow) {
        $editRow['webhook_paths_array'] = json_decode($editRow['webhook_paths'] ?? '{}', true) ?: [];
    }
}

require_once 'header.php';
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>⚙️ إعدادات تكامل n8n</h1>
        <p class="text-muted" style="margin:0 0 0 10px; font-size:13px;">إدارة ديناميكية لجميع روابط n8n Webhooks — لا حاجة لتعديل الكود</p>
    </div>
    <div class="content-header-right">
        <a href="?new=1" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> إضافة تكامل جديد</a>
    </div>
</section>

<section class="content">
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- ── Left: Form ── -->
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cogs"></i> <?= $editRow ? 'تعديل التكامل' : 'إضافة تكامل جديد' ?></h3>
            </div>
            <?php if (isset($_GET['new']) || $editRow): ?>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editRow['id'] ?? 0 ?>">
                <div class="box-body">

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>البيئة <span class="text-danger">*</span></label>
                                <select name="environment" class="form-control">
                                    <?php foreach (['production' => '🟢 Production', 'staging' => '🟡 Staging', 'development' => '🔵 Development'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($editRow['environment'] ?? 'production') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>الاسم / Label</label>
                                <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($editRow['label'] ?? 'Default') ?>" placeholder="Default">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Base URL <span class="text-danger">*</span></label>
                        <input type="url" name="base_url" class="form-control" value="<?= htmlspecialchars($editRow['base_url'] ?? '') ?>" placeholder="https://thikastore.app.n8n.cloud" required>
                        <p class="help-block">رابط حساب n8n بدون / في النهاية</p>
                    </div>

                    <div class="form-group">
                        <label>API Key (اختياري)</label>
                        <input type="password" name="api_key" class="form-control" value="<?= $editRow ? '••••••••' : '' ?>" placeholder="اتركه فارغاً إذا لم يكن مطلوباً" autocomplete="new-password">
                        <p class="help-block">يُشفَّر تلقائياً ولا يظهر بعد الحفظ</p>
                    </div>

                    <hr>
                    <h4><i class="fa fa-link"></i> مسارات Webhooks</h4>
                    <p class="help-block">أدخل المسار فقط — سيُضاف Base URL تلقائياً أمامه.</p>

                    <?php
                    $paths = $editRow['webhook_paths_array'] ?? $defaultPaths;
                    $webhookLabels = [
                        'ai_agent'         => ['🤖 AI Sales Agent',    'ai_agent'],
                        'product_sync'     => ['📦 Product Sync',       'product_sync'],
                        'provider_manager' => ['🔑 Provider Manager',   'provider_manager'],
                        'customer360'      => ['👤 Customer 360',       'customer360'],
                        'order_events'     => ['🛒 Order Events',       'order_events'],
                        'analytics'        => ['📊 Analytics',          'analytics'],
                        'notifications'    => ['🔔 Notifications',      'notifications'],
                    ];
                    foreach ($webhookLabels as $key => [$label, $fieldKey]):
                        $val = $paths[$key] ?? $defaultPaths[$key];
                    ?>
                    <div class="form-group">
                        <label><?= $label ?></label>
                        <div class="input-group">
                            <span class="input-group-addon"><small style="font-size:10px" class="text-muted">BASE_URL</small></span>
                            <input type="text" name="webhook_<?= $key ?>" class="form-control" value="<?= htmlspecialchars($val) ?>" placeholder="<?= $defaultPaths[$key] ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <label><input type="checkbox" name="is_active" value="1" <?= ($editRow['is_active'] ?? 1) ? 'checked' : '' ?>> تفعيل هذا التكامل</label>
                    </div>

                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ</button>
                    <a href="n8n-integration.php" class="btn btn-default">إلغاء</a>
                </div>
            </form>
            <?php else: ?>
            <div class="box-body text-center text-muted" style="padding:40px">
                <i class="fa fa-plug fa-3x text-primary" style="margin-bottom:15px;"></i>
                <p style="font-size: 16px;">لم يتم تحديد أي تكامل لتعديله.</p>
                <a href="?new=1" class="btn btn-primary btn-lg mt-3"><i class="fa fa-plus"></i> إنشاء تكامل n8n جديد</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Records ── -->
    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> التكاملات المحفوظة</h3>
            </div>
            <div class="box-body p-0">
                <?php if (empty($integrations)): ?>
                <div class="text-center text-muted" style="padding:40px">لا توجد تكاملات. أضف أول تكامل n8n.</div>
                <?php else: ?>
                <table class="table table-hover table-bordered" style="margin:0">
                    <thead class="bg-light">
                        <tr><th>البيئة</th><th>الاسم</th><th>Base URL</th><th>الحالة</th><th>آخر اختبار</th><th>إجراءات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($integrations as $row):
                        $statusClass = ['online'=>'success','offline'=>'danger','untested'=>'default'][$row['last_status']] ?? 'default';
                        $statusText  = ['online'=>'متصل','offline'=>'غير متصل','untested'=>'لم يُختبر'][$row['last_status']] ?? '—';
                        $envBadge    = ['production'=>'danger','staging'=>'warning','development'=>'info'][$row['environment']] ?? 'default';
                    ?>
                    <tr>
                        <td><span class="label label-<?= $envBadge ?>"><?= ucfirst($row['environment']) ?></span></td>
                        <td><?= htmlspecialchars($row['label']) ?></td>
                        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($row['base_url']) ?>">
                            <small><?= htmlspecialchars($row['base_url']) ?></small>
                        </td>
                        <td><span class="label label-<?= $statusClass ?>"><?= $statusText ?></span>
                            <?= $row['is_active'] ? '' : ' <span class="label label-default">معطّل</span>' ?>
                        </td>
                        <td><small><?= $row['last_tested'] ? date('d/m H:i', strtotime($row['last_tested'])) : '—' ?></small></td>
                        <td>
                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-xs btn-warning" title="تعديل"><i class="fa fa-pencil"></i> تعديل</a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="test">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-info" title="اختبار الاتصال"><i class="fa fa-plug"></i> اختبار</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger" title="حذف"><i class="fa fa-trash"></i> حذف</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Call Logs -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> سجل الاتصالات الأخيرة</h3>
            </div>
            <div class="box-body p-0">
                <?php if (empty($logs)): ?>
                <div class="text-center text-muted" style="padding:20px">لا توجد سجلات بعد.</div>
                <?php else: ?>
                <div style="overflow-y:auto;max-height:300px;">
                <table class="table table-condensed table-hover" style="margin:0;font-size:12px">
                    <thead class="bg-light"><tr><th>Webhook</th><th>HTTP</th><th>زمن</th><th>خطأ</th><th>الوقت</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log):
                        $rowClass = $log['http_code'] >= 200 && $log['http_code'] < 300 ? '' : 'danger';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><code><?= htmlspecialchars($log['webhook_key']) ?></code></td>
                        <td><span class="label label-<?= $rowClass ? 'danger' : 'success' ?>"><?= $log['http_code'] ?></span></td>
                        <td><?= $log['duration_ms'] ?>ms</td>
                        <td><?= $log['error'] ? '<small class="text-danger">' . htmlspecialchars(substr($log['error'], 0, 40)) . '</small>' : '<i class="fa fa-check text-success"></i>' ?></td>
                        <td><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage Guide -->
        <div class="box box-info collapsed-box">
            <div class="box-header with-border" data-widget="collapse" style="cursor:pointer">
                <h3 class="box-title"><i class="fa fa-code"></i> دليل الاستخدام في الكود</h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool"><i class="fa fa-plus"></i></button></div>
            </div>
            <div class="box-body">
<pre style="font-size:12px; direction:ltr; text-align:left;">
// 1. Include the manager
require_once 'inc/integration/N8nManager.php';
use Integration\N8nManager;

// 2. Create instance (uses production env by default)
$n8n = new N8nManager($pdo);

// 3. Call a webhook
$result = $n8n->callWebhook('ai_agent', [
    'conversation_id' => 42,
    'message' => 'Hello!'
]);

// 4. Get URL directly (if needed)
$url = $n8n->getWebhook('product_sync');

// 5. Check connectivity
if ($n8n->isOnline()) { ... }
</pre>
            </div>
        </div>

    </div><!-- /.col-md-7 -->
</div><!-- /.row -->
</section>

<?php require_once 'footer.php'; ?>
