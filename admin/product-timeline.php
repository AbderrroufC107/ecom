<?php
require_once('header.php');

if (!isset($_GET['id'])) {
    header('location: stock.php');
    exit;
}

$product_id = (int)$_GET['id'];

// Get product details
$stmt = $dbRepo->prepare("SELECT p_name FROM tbl_product WHERE p_id = ?");
$stmt->execute([$product_id]);
$product_name = $stmt->fetchColumn();

if (!$product_name) {
    header('location: stock.php');
    exit;
}

// Get Audit Log Timeline for this product
$stmt = $dbRepo->prepare("
    SELECT a.*, v.size_id, v.color_id, s.size_name, c.color_name, u.full_name as user_name
    FROM tbl_stock_audit_log a
    LEFT JOIN tbl_product_variant v ON a.variant_id = v.variant_id
    LEFT JOIN tbl_size s ON v.size_id = s.size_id
    LEFT JOIN tbl_color c ON v.color_id = c.color_id
    LEFT JOIN tbl_user u ON a.user_id = u.id
    WHERE a.product_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$product_id]);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<section class="content-header">
    <div class="content-header-left">
        <h1>سجل حركة المنتج: <?= htmlspecialchars($product_name); ?></h1>
    </div>
    <div class="content-header-right">
        <a href="stock.php" class="btn btn-primary btn-sm">العودة إلى المخزون</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <ul class="timeline">
                <?php if (empty($timeline)): ?>
                    <li>
                        <i class="fa fa-clock-o bg-gray"></i>
                        <div class="timeline-item">
                            <h3 class="timeline-header no-border">لا توجد حركات مسجلة لهذا المنتج بعد.</h3>
                        </div>
                    </li>
                <?php else: ?>
                    <?php 
                    $last_date = '';
                    foreach($timeline as $log): 
                        $current_date = date('Y-m-d', strtotime($log['created_at']));
                        if ($current_date !== $last_date) {
                            $last_date = $current_date;
                    ?>
                        <li class="time-label">
                            <span class="bg-red">
                                <?= $current_date; ?>
                            </span>
                        </li>
                    <?php } 
                    
                    // Determine icon and color based on change
                    $change = (int)$log['change_amount'];
                    $icon = 'fa-exchange bg-blue';
                    $sign = '';
                    if ($change > 0) {
                        $icon = 'fa-plus bg-green';
                        $sign = '+';
                    } elseif ($change < 0) {
                        $icon = 'fa-minus bg-red';
                    }

                    // Variant string
                    $variant_str = '';
                    if (!empty($log['variant_id'])) {
                        $labels = [];
                        if (!empty($log['size_name'])) $labels[] = $log['size_name'];
                        if (!empty($log['color_name'])) $labels[] = $log['color_name'];
                        $variant_str = ' <small class="label label-default">' . implode(' / ', $labels) . '</small>';
                    }
                    ?>
                    <li>
                        <i class="fa <?= $icon; ?>"></i>
                        <div class="timeline-item">
                            <span class="time"><i class="fa fa-clock-o"></i> <?= date('H:i', strtotime($log['created_at'])); ?></span>

                            <h3 class="timeline-header">
                                <strong><?= $sign . $change; ?></strong> 
                                <?= $variant_str; ?>
                                - <?= htmlspecialchars($log['reason']); ?>
                            </h3>

                            <div class="timeline-body">
                                <ul>
                                    <li><strong>الكمية السابقة:</strong> <?= $log['old_qty']; ?></li>
                                    <li><strong>الكمية الجديدة:</strong> <?= $log['new_qty']; ?></li>
                                    <?php if (!empty($log['note'])): ?>
                                    <li><strong>ملاحظة:</strong> <?= nl2br(htmlspecialchars($log['note'])); ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="timeline-footer">
                                <span class="text-muted"><i class="fa fa-user"></i> المستخدم: <?= htmlspecialchars($log['user_name'] ?? 'System/API'); ?> | <i class="fa fa-globe"></i> IP: <?= htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <li>
                        <i class="fa fa-clock-o bg-gray"></i>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
