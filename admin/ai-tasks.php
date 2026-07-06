<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'header.php';

$status_filter = $_GET['status'] ?? '';
$type_filter   = $_GET['type'] ?? '';

$where = [];
$params = [];
if ($status_filter) { $where[] = "status = ?"; $params[] = $status_filter; }
if ($type_filter)   { $where[] = "task_type = ?"; $params[] = $type_filter; }
$sql = "SELECT * FROM tbl_ai_tasks" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC LIMIT 100";
$tasks = $dbRepo->prepare($sql);
$tasks->execute($params);
$tasks = $tasks->fetchAll();

$types   = $dbRepo->query("SELECT DISTINCT task_type FROM tbl_ai_tasks")->fetchAll(PDO::FETCH_COLUMN);
$summary = $dbRepo->query("SELECT status, COUNT(*) as cnt FROM tbl_ai_tasks GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>📋 سجل مهام الذكاء الاصطناعي</h1>
    </div>
</section>

<section class="content">
<div class="row mb-2">
    <?php foreach(['PENDING'=>['warning','⏳'],'PROCESSING'=>['info','⚙️'],'COMPLETED'=>['success','✅'],'FAILED'=>['danger','❌']] as $s=>[$c,$ic]): ?>
    <div class="col-sm-3"><div class="info-box bg-<?= $c ?>"><span class="info-box-icon"><?= $ic ?></span><div class="info-box-content"><span class="info-box-text"><?= $s ?></span><span class="info-box-number"><?= $summary[$s] ?? 0 ?></span></div></div></div>
    <?php endforeach; ?>
</div>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">المهام</h3>
        <div class="box-tools pull-right">
            <form method="GET" class="form-inline" style="display:inline">
                <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                    <option value="">كل الحالات</option>
                    <?php foreach(['PENDING','PROCESSING','COMPLETED','FAILED'] as $s): ?>
                    <option value="<?=$s?>" <?= $status_filter===$s?'selected':''?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-control input-sm" onchange="this.form.submit()">
                    <option value="">كل الأنواع</option>
                    <?php foreach($types as $t): ?>
                    <option value="<?=$t?>" <?= $type_filter===$t?'selected':''?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="box-body p-0">
        <table class="table table-hover table-condensed" style="margin:0;font-size:12px">
            <thead class="bg-light"><tr><th>#</th><th>النوع</th><th>Entity</th><th>الأولوية</th><th>الحالة</th><th>المزود</th><th>محاولات</th><th>خطأ</th><th>الوقت</th></tr></thead>
            <tbody>
            <?php foreach($tasks as $t):
                $sc = ['COMPLETED'=>'success','FAILED'=>'danger','PENDING'=>'warning','PROCESSING'=>'info'][$t['status']] ?? 'default';
            ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><code><?= htmlspecialchars($t['task_type']) ?></code></td>
                <td><?= $t['entity_type'] ?> #<?= $t['entity_id'] ?></td>
                <td><span class="label label-default"><?= $t['priority'] ?></span></td>
                <td><span class="label label-<?= $sc ?>"><?= $t['status'] ?></span></td>
                <td><?= $t['provider_id'] ?: '—' ?></td>
                <td><?= $t['retries'] ?></td>
                <td><?= $t['error_message'] ? '<span class="text-danger" title="'.htmlspecialchars($t['error_message']).'">⚠️</span>' : '—' ?></td>
                <td><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($tasks)): ?><tr><td colspan="9" class="text-center text-muted p-4">لا توجد مهام</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</section>

<?php require_once 'footer.php'; ?>
