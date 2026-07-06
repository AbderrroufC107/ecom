<?php
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/employee_functions.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
    die('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('<div class="alert alert-danger">رقم الطلب غير صالح</div>');
}

$stmt = $dbRepo->prepare("
    SELECT o.*, 
           u.full_name as employee_name,
           u.id as employee_id
    FROM tbl_order o 
    LEFT JOIN tbl_order_assignment oa ON o.id = oa.order_id AND oa.status = 'active'
    LEFT JOIN tbl_user u ON oa.employee_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('<div class="alert alert-danger">الطلب غير موجود</div>');
}

// Fetch products
$products_stmt = $dbRepo->prepare("SELECT * FROM tbl_order_item WHERE order_id = ?");
$products_stmt->execute([$id]);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no items in tbl_order_item, fallback to main order details
if (empty($products)) {
    $products = [[
        'product_name' => $order['product_name'],
        'size' => $order['order_size'],
        'color' => $order['order_color'],
        'quantity' => $order['quantity'],
        'unit_price' => $order['unit_price']
    ]];
}

// Fetch timeline
$timeline_stmt = $dbRepo->prepare("SELECT * FROM tbl_order_timeline WHERE order_id = ? ORDER BY created_at DESC");
$timeline_stmt->execute([$id]);
$timeline = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch calls
$calls_stmt = $dbRepo->prepare("SELECT * FROM tbl_order_call_log WHERE order_id = ? ORDER BY created_at DESC");
$calls_stmt->execute([$id]);
$calls = $calls_stmt->fetchAll(PDO::FETCH_ASSOC);
$call_count = count($calls);
$last_call = $calls[0] ?? null;

$delivery_co = 'غير محدد';
$tracking = '-';
$remote_status = '-';
if (!empty($order['ecotrack_tracking'])) {
    $delivery_co = 'EcoTrack';
    $tracking = $order['ecotrack_tracking'];
    $remote_status = $order['ecotrack_remote_status'];
} elseif (!empty($order['zrexpress_tracking'])) {
    $delivery_co = 'ZR Express';
    $tracking = $order['zrexpress_tracking'];
    $remote_status = $order['zrexpress_remote_status'];
}
?>
<div class="order-details-grid" style="padding: 20px; background: #f8fafc; border-top: 2px solid var(--primary); display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; direction: rtl;">
    
    <!-- Customer Info -->
    <div class="od-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h4 style="margin-top:0; color: #334155; font-size: 15px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;"><i class="fa fa-user"></i> معلومات العميل</h4>
        <div style="font-size: 13px; color: #475569; line-height: 1.8;">
            <div><strong>الاسم:</strong> <?php echo htmlspecialchars($order['customer_name'] ?: '-'); ?></div>
            <div><strong>الهاتف:</strong> <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>"><?php echo htmlspecialchars($order['customer_phone'] ?: '-'); ?></a></div>
            <div><strong>الولاية:</strong> <?php echo htmlspecialchars($order['wilaya'] ?: '-'); ?></div>
            <div><strong>البلدية:</strong> <?php echo htmlspecialchars($order['commune'] ?: '-'); ?></div>
            <div><strong>العنوان:</strong> <?php echo htmlspecialchars($order['address'] ?: '-'); ?></div>
            <div><strong>ملاحظة العميل:</strong> <?php echo nl2br(htmlspecialchars($order['customer_note'] ?: '-')); ?></div>
        </div>
    </div>

    <!-- Products -->
    <div class="od-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h4 style="margin-top:0; color: #334155; font-size: 15px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;"><i class="fa fa-shopping-bag"></i> المنتجات</h4>
        <div style="font-size: 13px; color: #475569;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #f1f5f9; background: #f8fafc; font-size: 11px; color:#64748b;">
                    <th style="padding: 6px; text-align: right;">المنتج</th>
                    <th style="padding: 6px; text-align: center;">الكمية</th>
                    <th style="padding: 6px; text-align: left;">السعر</th>
                </tr>
                <?php foreach($products as $p): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 8px 6px;">
                        <strong><?php echo htmlspecialchars($p['product_name'] ?: '-'); ?></strong>
                        <div style="font-size: 11px; color:#94a3b8; margin-top:2px;">
                            <?php if(!empty($p['size'])) echo 'مقاس: ' . htmlspecialchars($p['size']) . ' | '; ?>
                            <?php if(!empty($p['color'])) echo 'لون: ' . htmlspecialchars($p['color']); ?>
                        </div>
                    </td>
                    <td style="padding: 8px 6px; text-align: center;">x<?php echo (int)($p['quantity'] ?: 1); ?></td>
                    <td style="padding: 8px 6px; text-align: left; font-weight: bold; color: #0f172a;"><?php echo number_format((float)($p['unit_price'] ?: 0), 0); ?> دج</td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="2" style="padding: 8px 6px; text-align: right; font-weight: bold;">تكلفة التوصيل:</td>
                    <td style="padding: 8px 6px; text-align: left; font-weight: bold; color: #f97316;"><?php echo number_format((float)($order['shipping_cost'] ?: 0), 0); ?> دج</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 8px 6px; text-align: right; font-weight: 800; font-size: 14px;">الإجمالي الكلي:</td>
                    <td style="padding: 8px 6px; text-align: left; font-weight: 800; font-size: 14px; color: var(--primary);"><?php echo number_format((float)($order['total_price'] ?: 0), 0); ?> دج</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Shipping Info -->
    <div class="od-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h4 style="margin-top:0; color: #334155; font-size: 15px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;"><i class="fa fa-truck"></i> معلومات الشحن</h4>
        <div style="font-size: 13px; color: #475569; line-height: 1.8;">
            <div><strong>نوع التوصيل:</strong> <?php echo htmlspecialchars($order['delivery_type'] ?: 'إلى المنزل'); ?></div>
            <div><strong>شركة التوصيل:</strong> <?php echo $delivery_co; ?></div>
            <div><strong>رقم التتبع:</strong> <?php echo $tracking !== '-' ? '<span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-family:monospace; user-select:all;">'.htmlspecialchars($tracking).'</span>' : '-'; ?></div>
            <div><strong>حالة الشركة:</strong> <?php echo htmlspecialchars($remote_status ?: '-'); ?></div>
            <?php if(!empty($order['ecotrack_label_url'])): ?>
            <div style="margin-top:10px;"><a href="<?php echo htmlspecialchars($order['ecotrack_label_url']); ?>" target="_blank" class="btn btn-xs btn-primary"><i class="fa fa-print"></i> طباعة الوصل (EcoTrack)</a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Employee / Follow-up -->
    <div class="od-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h4 style="margin-top:0; color: #334155; font-size: 15px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;"><i class="fa fa-headset"></i> المتابعة والموظف</h4>
        <div style="font-size: 13px; color: #475569; line-height: 1.8;">
            <div><strong>الموظف المسؤول:</strong> <?php echo htmlspecialchars($order['employee_name'] ?: 'غير معين'); ?></div>
            <div><strong>إجمالي المكالمات:</strong> <span class="badge" style="background:#e2e8f0; color:#1e293b;"><?php echo $call_count; ?></span></div>
            <div><strong>حالة آخر اتصال:</strong> <?php echo htmlspecialchars($order['last_call_status'] ?: '-'); ?></div>
            <div><strong>تاريخ آخر اتصال:</strong> <?php echo $order['last_call_at'] ? date('d/m/Y H:i', strtotime($order['last_call_at'])) : '-'; ?></div>
            <?php if($last_call): ?>
            <div style="margin-top: 8px; padding: 8px; background: #fefce8; border-right: 3px solid #eab308; border-radius: 4px;">
                <strong>آخر ملاحظة:</strong><br>
                <?php echo nl2br(htmlspecialchars($last_call['note'] ?: '-')); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ERP Timeline -->
    <div class="od-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); grid-column: 1 / -1;">
        <h4 style="margin-top:0; color: #334155; font-size: 15px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;"><i class="fa fa-history"></i> الخط الزمني (Timeline)</h4>
        <div style="font-size: 13px; color: #475569;">
            <?php if(empty($timeline)): ?>
            <p>لا توجد أحداث مسجلة.</p>
            <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach($timeline as $t): ?>
                <div style="display:flex; gap:15px; align-items:center; border-bottom:1px dashed #e2e8f0; padding-bottom:6px;">
                    <div style="min-width:120px; font-size:11px; color:#64748b;"><?php echo date('d/m/Y H:i:s', strtotime($t['created_at'])); ?></div>
                    <div style="font-weight:bold; color:var(--primary); min-width:80px;"><?php echo htmlspecialchars($t['new_status']); ?></div>
                    <div style="color:#94a3b8; font-size:11px;"><?php echo htmlspecialchars($t['changed_by'] ?: 'النظام'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
