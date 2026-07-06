<?php
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/site-security.php');
require_once('header.php');

// Security check
if (!$is_manager) {
    admin_orders_redirect('index.php');
}

// Fetch Companies
$stmt = $dbRepo->query("SELECT * FROM tbl_delivery_company WHERE active = 1 AND api_enabled = 1");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [];
foreach ($companies as $c) {
    $c_id = $c['id'];
    
    // Average response time
    $stmtAvg = $dbRepo->prepare("SELECT AVG(response_time_ms) FROM tbl_api_request_log WHERE delivery_company_id = ?");
    $stmtAvg->execute([$c_id]);
    $avg_time = round((float)$stmtAvg->fetchColumn(), 2);
    
    // Today's Sent
    $stmtSent = $dbRepo->prepare("SELECT COUNT(DISTINCT order_id) FROM tbl_order_timeline WHERE delivery_company_id = ? AND action = 'تم إرسال الطلب لشركة التوصيل' AND DATE(created_at) = CURDATE()");
    $stmtSent->execute([$c_id]);
    $sent_today = $stmtSent->fetchColumn();
    
    // Delivered Today
    $stmtDelivered = $dbRepo->prepare("SELECT COUNT(DISTINCT order_id) FROM tbl_order_timeline WHERE delivery_company_id = ? AND action = 'تحديث حالة التوصيل' AND description LIKE '%إلى Delivered%' AND DATE(created_at) = CURDATE()");
    $stmtDelivered->execute([$c_id]);
    $delivered_today = $stmtDelivered->fetchColumn();
    
    // Returned Today
    $stmtReturned = $dbRepo->prepare("SELECT COUNT(DISTINCT order_id) FROM tbl_order_timeline WHERE delivery_company_id = ? AND action = 'تحديث حالة التوصيل' AND description LIKE '%إلى Returned%' AND DATE(created_at) = CURDATE()");
    $stmtReturned->execute([$c_id]);
    $returned_today = $stmtReturned->fetchColumn();
    
    // Errors (HTTP code >= 400 or null)
    $stmtErrors = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_api_request_log WHERE delivery_company_id = ? AND (http_code >= 400 OR http_code IS NULL) AND DATE(created_at) = CURDATE()");
    $stmtErrors->execute([$c_id]);
    $errors_today = $stmtErrors->fetchColumn();
    
    // Last error
    $stmtLastError = $dbRepo->prepare("SELECT response_body, created_at FROM tbl_api_request_log WHERE delivery_company_id = ? AND (http_code >= 400 OR http_code IS NULL) ORDER BY created_at DESC LIMIT 1");
    $stmtLastError->execute([$c_id]);
    $last_error = $stmtLastError->fetch(PDO::FETCH_ASSOC);

    // Total Requests
    $stmtTotalReqs = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_api_request_log WHERE delivery_company_id = ? AND DATE(created_at) = CURDATE()");
    $stmtTotalReqs->execute([$c_id]);
    $total_reqs = $stmtTotalReqs->fetchColumn();
    
    $success_rate = $total_reqs > 0 ? round((($total_reqs - $errors_today) / $total_reqs) * 100, 2) : 100;

    $stats[] = [
        'name' => $c['name'],
        'avg_time' => $avg_time,
        'sent' => $sent_today,
        'delivered' => $delivered_today,
        'returned' => $returned_today,
        'errors' => $errors_today,
        'success_rate' => $success_rate,
        'last_error' => $last_error
    ];
}

// Cron Status
$stmtCron = $dbRepo->query("SELECT created_at FROM tbl_api_request_log ORDER BY created_at DESC LIMIT 1");
$last_cron_time = $stmtCron->fetchColumn();

// If the last request log is older than 30 mins, Cron might be down.
$cron_status = "Active";
$cron_class = "success";
if (!$last_cron_time || (strtotime(date('Y-m-d H:i:s')) - strtotime($last_cron_time) > 1800)) {
    $cron_status = "Inactive or No pending syncs";
    $cron_class = "warning";
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>لوحة مراقبة التكامل (API Integration Dashboard)</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-blue"><i class="fa fa-cogs"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">حالة الجدولة (Cron)</span>
                    <span class="info-box-number text-<?= $cron_class ?>"><?= $cron_status ?></span>
                    <small>آخر عملية: <?= $last_cron_time ?: 'لا توجد' ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-exchange"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">شركات مفعلة</span>
                    <span class="info-box-number"><?= count($companies) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach($stats as $s): ?>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-truck"></i> <?= htmlspecialchars($s['name']) ?></h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>متوسط زمن الاستجابة:</th>
                            <td><?= $s['avg_time'] ?> ms</td>
                        </tr>
                        <tr>
                            <th>معدل نجاح الـ API (اليوم):</th>
                            <td>
                                <div class="progress progress-xs">
                                    <div class="progress-bar progress-bar-<?= $s['success_rate'] >= 90 ? 'success' : 'danger' ?>" style="width: <?= $s['success_rate'] ?>%"></div>
                                </div>
                                <span class="badge bg-<?= $s['success_rate'] >= 90 ? 'green' : 'red' ?>"><?= $s['success_rate'] ?>%</span>
                            </td>
                        </tr>
                        <tr>
                            <th>الطلبات المرسلة (اليوم):</th>
                            <td><?= $s['sent'] ?></td>
                        </tr>
                        <tr>
                            <th>تم التسليم (اليوم):</th>
                            <td class="text-success"><b><?= $s['delivered'] ?></b></td>
                        </tr>
                        <tr>
                            <th>مرتجع (اليوم):</th>
                            <td class="text-danger"><b><?= $s['returned'] ?></b></td>
                        </tr>
                        <tr>
                            <th>أخطاء الاتصال (اليوم):</th>
                            <td><span class="label label-<?= $s['errors'] > 0 ? 'danger' : 'success' ?>"><?= $s['errors'] ?></span></td>
                        </tr>
                        <?php if($s['last_error']): ?>
                        <tr>
                            <th>آخر خطأ سجل:</th>
                            <td>
                                <small class="text-muted"><?= $s['last_error']['created_at'] ?></small><br>
                                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars($s['last_error']['response_body']) ?></textarea>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($stats)): ?>
        <div class="col-md-12">
            <div class="alert alert-warning">لا توجد شركات توصيل مفعلة بخدمة الـ API حالياً. يرجى تفعيلها من إعدادات التكامل.</div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once('footer.php'); ?>
