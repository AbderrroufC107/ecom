<?php
require_once('header.php');

$admin_id = $_SESSION['user']['id'] ?? 0;

if(isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    if($id === 0) {
        $dbRepo->prepare("UPDATE tbl_notification SET is_read = 1 WHERE user_id = 0 OR user_id = ?")->execute([$admin_id]);
    } else {
        $dbRepo->prepare("UPDATE tbl_notification SET is_read = 1 WHERE id = ?")->execute([$id]);
    }
    header('location: notifications.php');
    exit;
}

// Fetch notifications
$stmt = $dbRepo->prepare("SELECT * FROM tbl_notification WHERE user_id = 0 OR user_id = ? ORDER BY created_at DESC LIMIT 500");
$stmt->execute([$admin_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>مركز الإشعارات (Notification Center)</h1>
    </div>
    <div class="content-header-right">
        <a href="notifications.php?mark_read=0" class="btn btn-default btn-sm"><i class="fa fa-check-square-o"></i> تحديد الكل كمقروء</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-body">
                    <?php if(empty($notifications)): ?>
                        <p class="text-center text-muted">لا توجد إشعارات لعرضها.</p>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach($notifications as $n): 
                                $icon = 'fa-info bg-blue';
                                if($n['type'] === 'success') $icon = 'fa-check bg-green';
                                if($n['type'] === 'warning') $icon = 'fa-warning bg-yellow';
                                if($n['type'] === 'danger') $icon = 'fa-bolt bg-red';
                            ?>
                            <li>
                                <i class="fa <?= $icon; ?>"></i>
                                <div class="timeline-item" style="<?= $n['is_read'] == 0 ? 'background-color:#f4f8fb;' : ''; ?>">
                                    <span class="time"><i class="fa fa-clock-o"></i> <?= date('Y-m-d H:i', strtotime($n['created_at'])); ?></span>
                                    <h3 class="timeline-header">
                                        <?= htmlspecialchars($n['title']); ?>
                                        <?php if($n['is_read'] == 0): ?>
                                            <span class="label label-danger">جديد</span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="timeline-body">
                                        <?= nl2br(htmlspecialchars($n['message'])); ?>
                                    </div>
                                    <div class="timeline-footer">
                                        <?php if($n['is_read'] == 0): ?>
                                            <a href="notifications.php?mark_read=<?= $n['id']; ?>" class="btn btn-primary btn-xs">تحديد كمقروء</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <li><i class="fa fa-clock-o bg-gray"></i></li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
