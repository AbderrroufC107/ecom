<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];
$page_title = 'عمولاتي';

$summary = performance_get_commission_summary($pdo, $employee_id);

$stmt = $pdo->prepare("
    SELECT ec.*, o.customer_name, o.product_name, o.total_price
    FROM tbl_employee_commission ec
    LEFT JOIN tbl_order o ON o.id = ec.order_id
    WHERE ec.employee_id = ?
    ORDER BY ec.created_at DESC
    LIMIT 100
");
$stmt->execute([$employee_id]);
$commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM tbl_commission_payment WHERE employee_id = ? ORDER BY paid_at DESC LIMIT 50");
$stmt->execute([$employee_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-3">
    <div class="col-6 col-md-3">
        <div class="staff-card">
            <div class="staff-card-title">اليوم</div>
            <div class="staff-card-value" style="color:var(--success);"><?php echo number_format($summary['today'], 2); ?> دج</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="staff-card">
            <div class="staff-card-title">هذا الأسبوع</div>
            <div class="staff-card-value"><?php echo number_format($summary['this_week'], 2); ?> دج</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="staff-card">
            <div class="staff-card-title">هذا الشهر</div>
            <div class="staff-card-value"><?php echo number_format($summary['this_month'], 2); ?> دج</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="staff-card">
            <div class="staff-card-title">غير مدفوعة</div>
            <div class="staff-card-value" style="color:var(--warning);"><?php echo number_format($summary['total_unpaid'], 2); ?> دج</div>
            <div class="staff-card-label">المدفوع: <?php echo number_format($summary['total_paid'], 2); ?> دج</div>
        </div>
    </div>
</div>

<div class="staff-card">
    <div class="staff-card-title">سجل العمولات</div>
    <?php if (empty($commissions)): ?>
        <div class="staff-empty">
            <i class="bi bi-cash-coin"></i>
            <p>لا توجد عمولات بعد. أكمل طلباتك لبدء تحقيق الأرباح.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table staff-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الطلب</th>
                        <th>العميل</th>
                        <th>قيمة الطلب</th>
                        <th>النوع</th>
                        <th>العمولة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commissions as $c): ?>
                        <tr>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($c['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>#<?php echo (int) $c['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($c['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) ($c['total_price'] ?? 0), 0); ?> دج</td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($c['commission_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><strong style="color:var(--success);">+<?php echo number_format((float) $c['commission_amount'], 2); ?> دج</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="staff-card">
    <div class="staff-card-title">سجل المدفوعات</div>
    <?php if (empty($payments)): ?>
        <div class="staff-empty">
            <i class="bi bi-wallet2"></i>
            <p>لم يتم تسجيل أي دفعات بعد.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table staff-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المبلغ</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['paid_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong style="color:var(--danger);">-<?php echo number_format((float) $p['amount'], 2); ?> دج</strong></td>
                            <td><?php echo htmlspecialchars($p['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
