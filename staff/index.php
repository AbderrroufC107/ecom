<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];
$kpis = performance_get_kpis($pdo, $employee_id);

$stmt = $pdo->prepare("
    SELECT o.*, oa.assigned_at
    FROM tbl_order_assignment oa
    INNER JOIN tbl_order o ON o.id = oa.order_id
    WHERE oa.employee_id = ? AND oa.status = 'active'
    ORDER BY o.order_date DESC
    LIMIT 5
");
$stmt->execute([$employee_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ranking = performance_get_ranking($pdo, null);
$rank_position = 0;
foreach ($ranking as $i => $r) {
    if ((int) $r['id'] === $employee_id) {
        $rank_position = $i + 1;
        break;
    }
}

$commission_summary = performance_get_commission_summary($pdo, $employee_id);

$page_title = 'لوحة التحكم';
?>

<div class="row g-3">
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">إجمالي الطلبات</div>
            <div class="staff-card-value"><?php echo (int) $kpis['total_assigned']; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">معلق</div>
            <div class="staff-card-value" style="color:var(--warning);"><?php echo (int) $kpis['pending']; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">مكتمل</div>
            <div class="staff-card-value" style="color:var(--success);"><?php echo (int) $kpis['completed']; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">النقاط</div>
            <div class="staff-card-value" style="color:var(--accent);"><?php echo (int) $kpis['score']; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">نسبة التوصيل</div>
            <div class="staff-card-value"><?php echo $kpis['delivery_success_rate']; ?>%</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">الترتيب</div>
            <div class="staff-card-value">#<?php echo $rank_position; ?></div>
            <div class="staff-card-label">من <?php echo count($ranking); ?> موظف</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">عمولات اليوم</div>
            <div class="staff-card-value" style="color:var(--success);"><?php echo number_format($commission_summary['today'], 2); ?> دج</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="staff-card">
            <div class="staff-card-title">غير مدفوعة</div>
            <div class="staff-card-value"><?php echo number_format($commission_summary['total_unpaid'], 2); ?> دج</div>
        </div>
    </div>
</div>

<div class="staff-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <div>
            <div class="staff-card-title" style="margin-bottom:0;">آخر الطلبات</div>
        </div>
        <a href="orders.php" class="btn btn-outline-primary btn-staff btn-sm">عرض الكل</a>
    </div>

    <?php if (empty($recent_orders)): ?>
        <div class="staff-empty">
            <i class="bi bi-inbox"></i>
            <p>لا توجد طلبات مسندة إليك حالياً.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table staff-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العميل</th>
                        <th>المنتج</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $o): ?>
                        <?php $status_class = strtolower($o['order_status'] ?? ''); ?>
                        <tr>
                            <td><?php echo (int) $o['id']; ?></td>
                            <td><?php echo htmlspecialchars($o['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($o['product_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) ($o['total_price'] ?? 0), 0); ?> دج</td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($o['order_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td style="font-size:13px;color:var(--text-secondary);"><?php echo htmlspecialchars($o['order_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><a href="order-details.php?id=<?php echo (int) $o['id']; ?>" class="btn btn-sm btn-staff btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
