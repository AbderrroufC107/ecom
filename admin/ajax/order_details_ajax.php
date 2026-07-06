<?php
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/functions.php');
require_once(__DIR__ . '/../inc/CSRF_Protect.php');
$csrf = new CSRF_Protect();

if (!isset($_SESSION['user'])) {
    echo '<div class="alert alert-danger">غير مصرح لك.</div>';
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo '<div class="alert alert-warning">طلب غير صالح.</div>';
    exit;
}

// Fetch Order
$stmt = $dbRepo->prepare("SELECT o.*, 
    c.name as delivery_company_name 
    FROM tbl_order o 
    LEFT JOIN tbl_delivery_company c ON o.delivery_company_id = c.id 
    WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="alert alert-warning">لم يتم العثور على الطلب.</div>';
    exit;
}

require_once(__DIR__ . '/../inc/employee_functions.php');
$current_admin_emp = employee_get_current_admin_employee($pdo);
$is_product_restricted_for_user = false;
if ($current_admin_emp !== null && !empty($current_admin_emp['id'])) {
    if (!employee_can_access_order($pdo, (int)$current_admin_emp['id'], (int)$order['id'])) {
        $is_product_restricted_for_user = true;
    }
}
if ($is_product_restricted_for_user) {
    $order['customer_phone'] = '***-***-**** (مخفي)';
    $order['customer_address'] = 'عنوان مخفي - ليس لديك صلاحية';
    if (isset($order['order_note'])) $order['order_note'] = 'ملاحظات مخفية';
}

// Fetch Call Logs
$stmt_calls = $dbRepo->prepare("SELECT * FROM tbl_order_call_log WHERE order_id = ? ORDER BY called_at DESC");
$stmt_calls->execute([$id]);
$calls = $stmt_calls->fetchAll(PDO::FETCH_ASSOC);

// Fetch Timeline
$stmt_timeline = $dbRepo->prepare("SELECT t.*, u.full_name as user_name, c.name as company_name FROM tbl_order_timeline t LEFT JOIN tbl_user u ON t.user_id = u.id LEFT JOIN tbl_delivery_company c ON t.delivery_company_id = c.id WHERE t.order_id = ? ORDER BY t.created_at DESC");
$stmt_timeline->execute([$id]);
$timeline = $stmt_timeline->fetchAll(PDO::FETCH_ASSOC);

// Fetch API Logs
$stmt_api = $dbRepo->prepare("SELECT l.*, c.name as company_name FROM tbl_api_request_log l LEFT JOIN tbl_delivery_company c ON l.delivery_company_id = c.id WHERE l.order_id = ? ORDER BY l.created_at DESC");
$stmt_api->execute([$id]);
$api_logs = $stmt_api->fetchAll(PDO::FETCH_ASSOC);

// Status Badge Class
$statusClass = 'label-default';
switch ($order['order_status']) {
    case 'Pending': $statusClass = 'label-warning'; break;
    case 'Completed': $statusClass = 'label-success'; break;
    case 'Returned': $statusClass = 'label-danger'; break;
    case 'Cancelled': $statusClass = 'label-default'; break;
}

// Delivery Badge
$deliveryBadge = $order['delivery_company_name'] ? '<span class="label label-info"><i class="fa fa-truck"></i> ' . htmlspecialchars($order['delivery_company_name']) . '</span>' : '';
?>

<?php if ($is_product_restricted_for_user): ?>
<div class="alert alert-danger" style="margin:15px; background:#fef2f2;border:1px solid #f87171;color:#991b1b;padding:12px;border-radius:8px;font-weight:bold;">
    <i class="fa fa-exclamation-triangle"></i> ليس لديك صلاحية لعرض أو تعديل هذا الطلب لأنه تابع لمنتج غير مخصص لك.
</div>
<?php endif; ?>

<!-- Header -->
<div class="drawer-header">
    <div class="drawer-title">
        <span>#<?= $order['id'] ?></span>
        <span>- <?= htmlspecialchars($order['customer_name']) ?></span>
        <span class="label <?= $statusClass ?>"><?= htmlspecialchars($order['order_status']) ?></span>
        <?= $deliveryBadge ?>
    </div>
    <button type="button" class="close-btn js-close-order-drawer" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i></button>
</div>

<!-- Custom Tabs CSS for Drawer -->
<style>
.drawer-tabs { background: var(--e-ui-bg-panel); border-bottom: 1px solid var(--e-ui-border); padding: 0 var(--e-ui-spacing-lg); flex: 0 0 auto; z-index: 2; }
.drawer-tabs .nav-pills { margin-bottom: -1px; display: flex; overflow-x: auto; scrollbar-width: none; }
.drawer-tabs .nav-pills::-webkit-scrollbar { display: none; }
.drawer-tabs .nav-pills > li > a { 
    border-radius: 0; 
    color: var(--e-ui-text-muted); 
    padding: 12px 16px; 
    margin-right: 0; 
    background: transparent; 
    border-bottom: 3px solid transparent;
    font-weight: bold;
    white-space: nowrap;
}
.drawer-tabs .nav-pills > li.active > a, 
.drawer-tabs .nav-pills > li > a:hover { 
    background: transparent; 
    color: var(--e-ui-primary); 
    border-bottom: 3px solid var(--e-ui-primary); 
}
.detail-box { 
    background: var(--e-ui-bg-panel); 
    border: 1px solid var(--e-ui-border); 
    border-radius: var(--e-ui-radius-md); 
    padding: var(--e-ui-spacing-md); 
    margin-bottom: var(--e-ui-spacing-md); 
    box-shadow: var(--e-ui-shadow-sm);
}
.detail-box h4 { margin-top: 0; margin-bottom: 15px; font-weight: bold; font-size: 16px; border-bottom: 1px solid var(--e-ui-border); padding-bottom: 10px; }
.detail-table { width: 100%; }
.detail-table th { width: 140px; color: var(--e-ui-text-muted); padding: 8px; border-bottom: 1px solid var(--e-ui-border); vertical-align: top;}
.detail-table td { padding: 8px; border-bottom: 1px solid var(--e-ui-border); font-weight: bold; color: var(--e-ui-text-main);}
.detail-table tr:last-child th, .detail-table tr:last-child td { border-bottom: none; }
</style>

<div class="drawer-tabs">
    <ul class="nav nav-pills" role="tablist">
        <li role="presentation" class="active"><a href="#t_summary" role="tab" data-toggle="tab"><i class="fa fa-info-circle"></i> الملخص</a></li>
        <li role="presentation"><a href="#t_products" role="tab" data-toggle="tab"><i class="fa fa-shopping-bag"></i> المنتجات</a></li>
        <li role="presentation"><a href="#t_customer" role="tab" data-toggle="tab"><i class="fa fa-user"></i> العميل</a></li>
        <li role="presentation"><a href="#t_delivery" role="tab" data-toggle="tab"><i class="fa fa-truck"></i> التوصيل</a></li>
        <li role="presentation"><a href="#t_calls" role="tab" data-toggle="tab"><i class="fa fa-phone"></i> المتابعة (<?= count($calls) ?>)</a></li>
        <li role="presentation"><a href="#t_timeline" role="tab" data-toggle="tab"><i class="fa fa-history"></i> Timeline</a></li>
        <li role="presentation"><a href="#t_api" role="tab" data-toggle="tab"><i class="fa fa-exchange"></i> API Logs</a></li>
        <li role="presentation"><a href="#t_notes" role="tab" data-toggle="tab"><i class="fa fa-sticky-note"></i> الملاحظات</a></li>
    </ul>
</div>

<!-- Body (Scrollable) -->
<div class="drawer-body" style="padding: var(--e-ui-spacing-lg); background: var(--e-ui-bg-body);">

    <div class="tab-content">
        <!-- Summary Tab -->
        <div role="tabpanel" class="tab-pane active" id="t_summary">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-box">
                        <h4><i class="fa fa-money text-success"></i> المبالغ</h4>
                        <table class="detail-table">
                            <tr><th>سعر المنتج</th><td><?= number_format((float)$order['unit_price'], 2) ?> د.ج</td></tr>
                            <tr><th>تكلفة الشحن</th><td><?= number_format((float)$order['shipping_cost'], 2) ?> د.ج</td></tr>
                            <tr><th>الإجمالي</th><td><span class="label label-success" style="font-size:14px;"><?= number_format((float)$order['amount'], 2) ?> د.ج</span></td></tr>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-box">
                        <h4><i class="fa fa-calendar text-info"></i> التواريخ</h4>
                        <table class="detail-table">
                            <tr><th>تاريخ الطلب</th><td><span dir="ltr"><?= date('Y-m-d H:i', strtotime($order['order_date'])) ?></span></td></tr>
                            <tr><th>رقم التتبع</th><td><?= htmlspecialchars($order['tracking_number'] ?: 'غير متوفر') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Tab -->
        <div role="tabpanel" class="tab-pane" id="t_products">
            <div class="detail-box">
                <h4><i class="fa fa-shopping-bag text-primary"></i> المنتجات المطلوبة</h4>
                <table class="table table-bordered">
                    <thead style="background:var(--e-ui-bg-hover)">
                        <tr>
                            <th>المنتج</th>
                            <th>المتغيرات (اللون/المقاس)</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($order['product_name']) ?></td>
                            <td><?= htmlspecialchars($order['color'] ?? '') ?> <?= htmlspecialchars($order['size'] ?? '') ?></td>
                            <td><?= (int)$order['quantity'] ?></td>
                            <td><?= number_format((float)$order['unit_price'], 2) ?></td>
                            <td><strong><?= number_format((float)$order['unit_price'] * $order['quantity'], 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Customer Tab -->
        <div role="tabpanel" class="tab-pane" id="t_customer">
            <div class="detail-box">
                <h4><i class="fa fa-user text-primary"></i> بيانات العميل</h4>
                <table class="detail-table">
                    <tr><th>الاسم الكامل</th><td><?= htmlspecialchars($order['customer_name']) ?></td></tr>
                    <tr><th>رقم الهاتف</th><td><a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>" dir="ltr"><?= htmlspecialchars($order['customer_phone']) ?></a></td></tr>
                    <tr><th>الولاية</th><td><?= htmlspecialchars($order['customer_country'] ?? $order['customer_state'] ?? '') ?></td></tr>
                    <tr><th>البلدية / المدينة</th><td><?= htmlspecialchars($order['customer_city']) ?></td></tr>
                    <tr><th>العنوان الكامل</th><td><?= nl2br(htmlspecialchars($order['customer_address'])) ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Delivery Tab -->
        <div role="tabpanel" class="tab-pane" id="t_delivery">
            <div class="detail-box">
                <h4><i class="fa fa-truck text-primary"></i> معلومات الشحن والتوصيل</h4>
                <table class="detail-table">
                    <tr><th>طريقة التوصيل</th><td><?= htmlspecialchars($order['delivery_type'] == 'home' ? 'توصيل للمنزل' : 'توصيل للمكتب') ?></td></tr>
                    <tr><th>شركة التوصيل</th><td><?= $order['delivery_company_name'] ? htmlspecialchars($order['delivery_company_name']) : 'غير محددة' ?></td></tr>
                    <tr><th>رقم التتبع</th><td><?= htmlspecialchars($order['tracking_number'] ?: 'غير متوفر') ?></td></tr>
                    <tr><th>حالة شركة التوصيل</th><td><span class="label label-info"><?= htmlspecialchars($order['delivery_status'] ?: 'لا توجد حالة') ?></span></td></tr>
                </table>
            </div>
        </div>

        <!-- Follow-up Tab -->
        <div role="tabpanel" class="tab-pane" id="t_calls">
            <div class="detail-box">
                <h4><i class="fa fa-phone text-warning"></i> سجل المكالمات والمتابعة</h4>
                <?php if(empty($calls)): ?>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> لا توجد مكالمات مسجلة.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>الوقت</th><th>الحالة</th><th>الملاحظة</th><th>الموظف</th></tr></thead>
                        <tbody>
                            <?php foreach($calls as $c): ?>
                                <tr>
                                    <td dir="ltr"><?= date('Y-m-d H:i', strtotime($c['called_at'])) ?></td>
                                    <td><span class="label label-default"><?= htmlspecialchars($c['call_status']) ?></span></td>
                                    <td><?= nl2br(htmlspecialchars($c['call_note'])) ?></td>
                                    <td><?= htmlspecialchars($c['created_by']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline Tab -->
        <div role="tabpanel" class="tab-pane" id="t_timeline">
            <div class="detail-box">
                <h4><i class="fa fa-history text-primary"></i> سجل حركة الطلب (Timeline)</h4>
                <?php if(empty($timeline)): ?>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> لا يوجد سجل حركات.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead><tr><th>الوقت</th><th>الإجراء</th><th>بواسطة</th><th>ملاحظة</th></tr></thead>
                        <tbody>
                            <?php foreach($timeline as $t): ?>
                                <tr>
                                    <td dir="ltr"><small><?= date('Y-m-d H:i:s', strtotime($t['created_at'])) ?></small></td>
                                    <td><strong><?= htmlspecialchars($t['action']) ?></strong></td>
                                    <td><?= htmlspecialchars($t['user_name'] ?? 'النظام') ?></td>
                                    <td><small><?= htmlspecialchars($t['notes']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Logs Tab -->
        <div role="tabpanel" class="tab-pane" id="t_api">
            <div class="detail-box">
                <h4><i class="fa fa-exchange text-info"></i> سجلات مزامنة الـ API</h4>
                <?php if(empty($api_logs)): ?>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> لا توجد سجلات اتصال.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead><tr><th>الوقت</th><th>الشركة</th><th>النوع</th><th>الحالة</th></tr></thead>
                        <tbody>
                            <?php foreach($api_logs as $l): ?>
                                <tr>
                                    <td dir="ltr"><small><?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?></small></td>
                                    <td><?= htmlspecialchars($l['company_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($l['request_type']) ?></td>
                                    <td><?= $l['is_success'] ? '<span class="text-success"><i class="fa fa-check"></i> نجاح</span>' : '<span class="text-danger"><i class="fa fa-times"></i> خطأ</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes Tab -->
        <div role="tabpanel" class="tab-pane" id="t_notes">
            <div class="detail-box">
                <h4><i class="fa fa-sticky-note text-warning"></i> ملاحظات الإدارة</h4>
                <div class="well" style="background:#fff9e6; border-color:#f5e29f;">
                    <?= nl2br(htmlspecialchars($order['order_note'] ?: 'لا توجد ملاحظات إضافية مسجلة للطلب.')) ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Footer -->
<div class="drawer-footer">
    <div class="drawer-footer-actions" style="margin-left: auto;">
        <button type="button" class="btn btn-default js-close-order-drawer">إغلاق</button>
    </div>
    <div class="drawer-footer-actions">
        <?php if (!$is_product_restricted_for_user): ?>
        <button type="button" class="btn btn-primary js-drawer-manage-order" data-title="تعديل وتفاصيل الطلب #<?= $id ?>" data-url="order-details.php?id=<?= $id ?>"><i class="fa fa-pencil"></i> إدارة الطلب</button>
        <?php if(!empty($order['delivery_company_id']) && empty($order['tracking_number'])): ?>
            <button type="button" class="btn btn-info js-drawer-manage-order" data-title="إرسال لشركة التوصيل #<?= $id ?>" data-url="order-details.php?id=<?= $id ?>&action=send_api"><i class="fa fa-send"></i> إرسال لشركة التوصيل</button>
        <?php endif; ?>
        <?php endif; ?>
        <a href="print-order.php?id=<?= $id ?>" target="_blank" class="btn btn-default"><i class="fa fa-print"></i> طباعة</a>
    </div>
</div>
