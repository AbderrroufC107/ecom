<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'header.php';
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>🤖 إدارة العملاء الآليين (AI Agents)</h1>
    </div>
</section>

<section class="content">
<?php
// Stats
$totalProviders = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_providers WHERE is_enabled=1")->fetchColumn();
$pendingTasks   = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_tasks WHERE status='PENDING'")->fetchColumn();
$completedToday = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_tasks WHERE status='COMPLETED' AND DATE(created_at)=CURDATE()")->fetchColumn();
$failedTasks    = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_tasks WHERE status='FAILED'")->fetchColumn();
?>
<div class="row">
    <div class="col-sm-3"><div class="info-box bg-aqua"><span class="info-box-icon"><i class="fa fa-cogs"></i></span><div class="info-box-content"><span class="info-box-text">مزودو AI النشطون</span><span class="info-box-number"><?= $totalProviders ?></span></div></div></div>
    <div class="col-sm-3"><div class="info-box bg-yellow"><span class="info-box-icon"><i class="fa fa-clock-o"></i></span><div class="info-box-content"><span class="info-box-text">مهام معلقة</span><span class="info-box-number"><?= $pendingTasks ?></span></div></div></div>
    <div class="col-sm-3"><div class="info-box bg-green"><span class="info-box-icon"><i class="fa fa-check"></i></span><div class="info-box-content"><span class="info-box-text">منجزة اليوم</span><span class="info-box-number"><?= $completedToday ?></span></div></div></div>
    <div class="col-sm-3"><div class="info-box bg-red"><span class="info-box-icon"><i class="fa fa-times"></i></span><div class="info-box-content"><span class="info-box-text">فاشلة</span><span class="info-box-number"><?= $failedTasks ?></span></div></div></div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server"></i> مزودو الذكاء الاصطناعي</h3></div>
            <div class="box-body p-0">
                <?php $providers = $dbRepo->query("SELECT * FROM tbl_ai_providers ORDER BY priority DESC")->fetchAll(); ?>
                <table class="table table-hover" style="margin:0">
                    <thead><tr><th>الاسم</th><th>النموذج</th><th>الأولوية</th><th>الحالة</th></tr></thead>
                    <tbody>
                    <?php foreach($providers as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><code><?= htmlspecialchars($p['model']) ?></code></td>
                        <td><?= $p['priority'] ?></td>
                        <td><span class="label label-<?= $p['is_enabled'] ? 'success' : 'danger' ?>"><?= $p['is_enabled'] ? 'نشط' : 'معطّل' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($providers)): ?><tr><td colspan="4" class="text-center text-muted">لا يوجد مزودون</td></tr><?php endif; ?>
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
