<?php
require_once('header.php');
require_once(__DIR__ . '/../inc/exchange-requests.php');

exchange_requests_ensure_table($pdo);

$status_labels = exchange_requests_status_labels();
$allowed_statuses = array_keys($status_labels);
$success_message = '';
$error_message = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $csrf->verifyRequest();
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        if ($request_id <= 0 || !in_array($status, $allowed_statuses, true)) {
            throw new RuntimeException('بيانات تحديث الحالة غير صحيحة.');
        }

        $stmt = $dbRepo->prepare("UPDATE exchange_requests SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $stmt->execute([$status, $request_id]);
        $success_message = 'تم تحديث حالة طلب التبديل.';
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$filter = trim((string) ($_GET['status'] ?? ''));
if (!in_array($filter, $allowed_statuses, true)) {
    $filter = '';
}

$params = [];
$where = '';
if ($filter !== '') {
    $where = 'WHERE er.status = ?';
    $params[] = $filter;
}

$stmt = $dbRepo->prepare("
    SELECT er.*, o.ecotrack_tracking, o.ecotrack_remote_status, o.ecotrack_remote_time
    FROM exchange_requests er
    LEFT JOIN tbl_order o ON o.id = er.order_id
    $where
    ORDER BY er.created_at DESC, er.id DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function exchange_admin_status_class($status)
{ global $dbRepo;
    global $dbRepo;

    $map = [
        'pending' => 'label-warning',
        'approved' => 'label-success',
        'rejected' => 'label-danger',
        'completed' => 'label-primary',
    ];
    return $map[$status] ?? 'label-default';
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>طلبات التبديل</h1>
    </div>
</section>

<section class="content">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo exchange_requests_h($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo exchange_requests_h($error_message); ?></div>
    <?php endif; ?>

    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">طلبات الزبائن المؤهلة للتبديل خلال 72 ساعة من التسليم</h3>
            <div class="pull-right">
                <a class="btn btn-default btn-sm <?php echo $filter === '' ? 'active' : ''; ?>" href="exchange-requests.php">الكل</a>
                <?php foreach ($status_labels as $status => $label): ?>
                    <a class="btn btn-default btn-sm <?php echo $filter === $status ? 'active' : ''; ?>" href="exchange-requests.php?status=<?php echo urlencode($status); ?>">
                        <?php echo exchange_requests_h($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الطلب</th>
                        <th>الزبون</th>
                        <th>المنتج</th>
                        <th>السبب والصورة</th>
                        <th>التسليم</th>
                        <th>الحالة</th>
                        <th style="width:190px;">تحديث</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">لا توجد طلبات تبديل حاليا.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $index => $row): ?>
                        <?php
                        $status = (string) ($row['status'] ?? 'pending');
                        $proof = trim((string) ($row['proof_image'] ?? ''));
                        $image_url = $proof !== '' ? get_admin_image_url($proof) : '';
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong>#<?php echo (int) ($row['order_id'] ?? 0); ?></strong>
                                <?php if (!empty($row['ecotrack_tracking'])): ?>
                                    <div class="text-muted">تتبع: <?php echo exchange_requests_h($row['ecotrack_tracking']); ?></div>
                                <?php endif; ?>
                                <a href="order-details.php?id=<?php echo (int) ($row['order_id'] ?? 0); ?>" class="btn btn-xs btn-info" style="margin-top:6px;">فتح الطلب</a>
                            </td>
                            <td>
                                <strong><?php echo exchange_requests_h($row['customer_name'] ?? ''); ?></strong>
                                <div><a href="tel:<?php echo exchange_requests_h($row['customer_phone'] ?? ''); ?>"><?php echo exchange_requests_h($row['customer_phone'] ?? ''); ?></a></div>
                            </td>
                            <td>
                                <strong><?php echo exchange_requests_h($row['product_name'] ?? ''); ?></strong>
                                <div class="text-muted">الكمية: <?php echo (int) ($row['quantity'] ?? 1); ?></div>
                            </td>
                            <td>
                                <div style="max-width:320px; white-space:pre-wrap;"><?php echo exchange_requests_h($row['reason'] ?? ''); ?></div>
                                <?php if ($image_url !== ''): ?>
                                    <a href="<?php echo exchange_requests_h($image_url); ?>" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;">
                                        <img src="<?php echo exchange_requests_h($image_url); ?>" alt="صورة سبب التبديل" style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>وقت التسليم: <?php echo exchange_requests_h($row['delivered_at'] ?? ''); ?></div>
                                <?php if (!empty($row['ecotrack_remote_status'])): ?>
                                    <div class="text-muted">Ecotrack: <?php echo exchange_requests_h($row['ecotrack_remote_status']); ?></div>
                                <?php endif; ?>
                                <div class="text-muted">تاريخ الطلب: <?php echo exchange_requests_h($row['created_at'] ?? ''); ?></div>
                            </td>
                            <td>
                                <span class="label <?php echo exchange_admin_status_class($status); ?>">
                                    <?php echo exchange_requests_h($status_labels[$status] ?? $status); ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" class="form-inline">
                                    <?php $csrf->echoInputField(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                    <select name="status" class="form-control input-sm" style="width:115px;">
                                        <?php foreach ($status_labels as $code => $label): ?>
                                            <option value="<?php echo exchange_requests_h($code); ?>" <?php echo $code === $status ? 'selected' : ''; ?>>
                                                <?php echo exchange_requests_h($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">حفظ</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
