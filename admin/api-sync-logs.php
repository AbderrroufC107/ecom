<?php
require_once('header.php');

// Fetch logs
$stmt = $dbRepo->prepare("
    SELECT l.*, c.name as company_name 
    FROM tbl_api_sync_log l
    LEFT JOIN tbl_delivery_company c ON l.delivery_company_id = c.id
    ORDER BY l.sync_time DESC LIMIT 500
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>سجل مزامنة API</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="example1">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الوقت</th>
                                <th>شركة التوصيل</th>
                                <th>رقم الطلب</th>
                                <th>رقم التتبع</th>
                                <th>الحالة السابقة</th>
                                <th>الحالة الجديدة</th>
                                <th>النتيجة</th>
                                <th>رسالة الخطأ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $row): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= $row['sync_time']; ?></td>
                                <td><?= htmlspecialchars($row['company_name'] ?? ''); ?></td>
                                <td><?= $row['order_id']; ?></td>
                                <td><?= htmlspecialchars($row['tracking_number'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['old_status'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($row['new_status'] ?? '-'); ?></td>
                                <td>
                                    <?php if($row['result'] === 'Success'): ?>
                                        <span class="label label-success">نجاح</span>
                                    <?php else: ?>
                                        <span class="label label-danger">فشل</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['error_message'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
