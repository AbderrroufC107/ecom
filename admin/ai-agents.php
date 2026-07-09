<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'header.php';

$success_message = $success_message ?? '';
$error_message   = $error_message ?? '';

// Save provider settings (API key / model / params). CSRF is auto-verified in header.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_provider'])) {
    $pid       = (int) ($_POST['provider_id'] ?? 0);
    $apiKey    = trim((string) ($_POST['api_key'] ?? ''));
    $model     = trim((string) ($_POST['model'] ?? ''));
    $temp      = (float) ($_POST['temperature'] ?? 0.7);
    $maxTokens = (int) ($_POST['max_tokens'] ?? 2000);
    $enabled   = isset($_POST['is_enabled']) ? 1 : 0;
    if ($temp < 0) $temp = 0; if ($temp > 2) $temp = 2;
    if ($maxTokens < 1) $maxTokens = 1;

    // Guard: never store a Gemini model whose free-tier quota is 0 / retired.
    // Auto-upgrade to a model that works, so a stale edit form can't revert it.
    $deadGeminiModels = ['gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-pro'];
    if (in_array(strtolower($model), $deadGeminiModels, true)) {
        $model = 'gemini-2.5-flash';
    }

    try {
        // A blank or masked key means "keep the existing one" — don't wipe it.
        $keyUnchanged = ($apiKey === '' || strpos($apiKey, '•') !== false);
        if ($keyUnchanged) {
            $stmt = $dbRepo->prepare("UPDATE tbl_ai_providers
                SET model=?, temperature=?, max_tokens=?, is_enabled=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$model, $temp, $maxTokens, $enabled, $pid]);
        } else {
            $stmt = $dbRepo->prepare("UPDATE tbl_ai_providers
                SET api_key=?, model=?, temperature=?, max_tokens=?, is_enabled=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$apiKey, $model, $temp, $maxTokens, $enabled, $pid]);
        }
        $success_message = 'تم حفظ إعدادات المزوّد بنجاح.';
    } catch (Exception $e) {
        $error_message = 'تعذّر الحفظ: ' . $e->getMessage();
    }
}

/** Mask an API key for display: keep last 4 chars. */
function mask_api_key(?string $k): string {
    $k = (string) $k;
    if ($k === '') return '';
    $len = strlen($k);
    return $len <= 4 ? str_repeat('•', $len) : str_repeat('•', 8) . substr($k, -4);
}

// Provider being edited (inline form), if any.
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editProvider = null;
if ($editId) {
    $st = $dbRepo->prepare("SELECT * FROM tbl_ai_providers WHERE id = ?");
    $st->execute([$editId]);
    $editProvider = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Stats
$totalProviders = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_providers WHERE is_enabled=1")->fetchColumn();
$pendingTasks   = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_tasks WHERE status='PENDING'")->fetchColumn();
$completedToday = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_tasks WHERE status='COMPLETED' AND DATE(created_at)=CURDATE()")->fetchColumn();
$failedTasks    = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_tasks WHERE status='FAILED'")->fetchColumn();

$kpis = [
    ['label' => 'مزودو AI النشطون', 'value' => $totalProviders, 'icon' => 'fa-cogs',    'c1' => '#0ea5e9', 'c2' => '#0284c7'],
    ['label' => 'مهام معلّقة',      'value' => $pendingTasks,   'icon' => 'fa-clock-o',  'c1' => '#f59e0b', 'c2' => '#d97706'],
    ['label' => 'منجزة اليوم',      'value' => $completedToday, 'icon' => 'fa-check',    'c1' => '#10b981', 'c2' => '#059669'],
    ['label' => 'فاشلة',           'value' => $failedTasks,    'icon' => 'fa-times',    'c1' => '#ef4444', 'c2' => '#dc2626'],
];
?>

<section class="content-header">
    <div class="content-header-left"><h1>🤖 إدارة العملاء الآليين (AI Agents)</h1></div>
</section>

<section class="content">
<?php if ($success_message): ?>
    <div class="alert alert-success"><i class="fa fa-check"></i> <?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger"><i class="fa fa-times"></i> <?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<!-- KPI cards (self-styled so the theme's CSS reset can't blank them) -->
<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px">
    <?php foreach ($kpis as $k): ?>
    <div style="flex:1 1 200px;min-width:200px;display:flex;align-items:center;gap:14px;
                padding:18px 20px;border-radius:12px;color:#fff;
                background:linear-gradient(135deg,<?= $k['c1'] ?>,<?= $k['c2'] ?>);
                box-shadow:0 4px 14px rgba(0,0,0,.10)">
        <i class="fa <?= $k['icon'] ?>" style="font-size:34px;opacity:.9"></i>
        <div>
            <div style="font-size:28px;font-weight:800;line-height:1"><?= (int)$k['value'] ?></div>
            <div style="font-size:13px;opacity:.95;margin-top:4px"><?= htmlspecialchars($k['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($editProvider): $ep = $editProvider; ?>
<!-- Popup overlay (server-rendered so the React shell can't strip it like Bootstrap modals) -->
<div style="position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.55);
            display:flex;align-items:flex-start;justify-content:center;padding:6vh 16px;overflow:auto">
    <a href="ai-agents.php" style="position:absolute;inset:0" aria-label="إغلاق"></a>
    <div style="position:relative;width:100%;max-width:560px;background:#fff;border-radius:14px;
                box-shadow:0 24px 70px rgba(0,0,0,.35);direction:rtl;text-align:right">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <h4 style="margin:0;font-weight:700"><i class="fa fa-server"></i> إعدادات <?= htmlspecialchars($ep['name']) ?></h4>
            <a href="ai-agents.php" style="font-size:24px;line-height:1;color:#64748b;text-decoration:none">&times;</a>
        </div>
        <form method="post" action="ai-agents.php">
            <div style="padding:20px">
                <?php csrf_field(); ?>
                <input type="hidden" name="save_provider" value="1">
                <input type="hidden" name="provider_id" value="<?= (int)$ep['id'] ?>">

                <div class="form-group">
                    <label>مفتاح API</label>
                    <input type="text" name="api_key" class="form-control" dir="ltr" autocomplete="off" autofocus
                           placeholder="<?= !empty($ep['api_key']) ? htmlspecialchars(mask_api_key($ep['api_key'])) . ' — اترك الحقل فارغاً للإبقاء عليه' : 'الصق مفتاح ' . htmlspecialchars($ep['name']) . ' هنا' ?>">
                    <?php if (stripos($ep['name'], 'gemini') !== false): ?>
                        <p class="help-block" style="margin-bottom:0">
                            احصل على مفتاح مجاني من
                            <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-sm-6 form-group">
                        <label>النموذج</label>
                        <input type="text" name="model" class="form-control" dir="ltr" value="<?= htmlspecialchars($ep['model']) ?>">
                    </div>
                    <div class="col-sm-3 form-group">
                        <label>Temperature</label>
                        <input type="number" step="0.05" min="0" max="2" name="temperature" class="form-control" dir="ltr" value="<?= htmlspecialchars($ep['temperature']) ?>">
                    </div>
                    <div class="col-sm-3 form-group">
                        <label>Max tokens</label>
                        <input type="number" min="1" name="max_tokens" class="form-control" dir="ltr" value="<?= (int)$ep['max_tokens'] ?>">
                    </div>
                </div>

                <div class="checkbox">
                    <label><input type="checkbox" name="is_enabled" value="1" <?= $ep['is_enabled'] ? 'checked' : '' ?>> مزوّد نشط</label>
                </div>
            </div>
            <div style="padding:14px 20px;border-top:1px solid #e2e8f0;text-align:left">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <a href="ai-agents.php" class="btn btn-default">إلغاء</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server"></i> مزودو الذكاء الاصطناعي</h3></div>
            <div class="box-body p-0">
                <?php $providers = $dbRepo->query("SELECT * FROM tbl_ai_providers ORDER BY priority DESC")->fetchAll(); ?>
                <table class="table table-hover" style="margin:0">
                    <thead><tr><th>الاسم</th><th>النموذج</th><th>المفتاح</th><th>الحالة</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($providers as $p): $hasKey = !empty($p['api_key']); ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><code><?= htmlspecialchars($p['model']) ?></code></td>
                        <td>
                            <?php if ($hasKey): ?>
                                <span class="label label-success" title="<?= htmlspecialchars(mask_api_key($p['api_key'])) ?>"><i class="fa fa-key"></i> مضبوط</span>
                            <?php else: ?>
                                <span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> غير مضبوط</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="label label-<?= $p['is_enabled'] ? 'success' : 'danger' ?>"><?= $p['is_enabled'] ? 'نشط' : 'معطّل' ?></span></td>
                        <td>
                            <a href="ai-agents.php?edit=<?= (int)$p['id'] ?>" class="btn btn-xs btn-primary">
                                <i class="fa fa-edit"></i> تعديل
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($providers)): ?><tr><td colspan="5" class="text-center text-muted">لا يوجد مزودون</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-tasks"></i> آخر 10 مهام</h3></div>
            <div class="box-body p-0">
                <?php $tasks = $dbRepo->query("SELECT * FROM tbl_ai_tasks ORDER BY id DESC LIMIT 10")->fetchAll(); ?>
                <table class="table table-condensed" style="margin:0;font-size:12px">
                    <thead><tr><th>#</th><th>النوع</th><th>الأولوية</th><th>الحالة</th><th>الوقت</th></tr></thead>
                    <tbody>
                    <?php foreach($tasks as $t):
                        $sc = ['COMPLETED'=>'success','FAILED'=>'danger','PENDING'=>'warning','PROCESSING'=>'info'][$t['status']] ?? 'default';
                    ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td><small><?= htmlspecialchars($t['task_type']) ?></small></td>
                        <td><span class="label label-default"><?= $t['priority'] ?></span></td>
                        <td><span class="label label-<?= $sc ?>"><?= $t['status'] ?></span></td>
                        <td><small><?= date('H:i', strtotime($t['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($tasks)): ?><tr><td colspan="5" class="text-center text-muted">لا توجد مهام</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="box-footer"><a href="ai-tasks.php" class="btn btn-sm btn-default">عرض الكل</a></div>
        </div>
    </div>
</div>
</section>

<?php require_once 'footer.php'; ?>
