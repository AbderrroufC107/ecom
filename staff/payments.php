<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];

// Fetch payment history for this employee
$stmt = $pdo->prepare("
    SELECT p.*, a.full_name as admin_name
    FROM tbl_employee_payments p
    LEFT JOIN tbl_user a ON p.admin_id = a.id
    WHERE p.employee_id = ?
    ORDER BY p.id DESC
");
$stmt->execute([$employee_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'سجل الدفعات والفواتير';
?>

<div class="staff-card mb-4">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h4 style="margin:0;color:var(--text-primary);"><i class="bi bi-wallet2"></i> <?php echo $page_title; ?></h4>
    </div>

    <?php if (empty($payments)): ?>
        <div class="staff-empty">
            <i class="bi bi-receipt"></i>
            <p>لا توجد مدفوعات سابقة.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table staff-table">
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>الطلبات المؤكدة</th>
                        <th>سعر العمولة</th>
                        <th>المبلغ الإجمالي</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['invoice_number']); ?></strong></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($p['payment_date'])); ?></td>
                            <td><span class="status-badge completed"><?php echo $p['confirmed_orders']; ?></span></td>
                            <td><?php echo number_format($p['commission_rate'], 2); ?> دج</td>
                            <td style="font-weight:bold;color:var(--success);"><?php echo number_format($p['total_amount'], 2); ?> دج</td>
                            <td>
                                <a href="../<?php echo htmlspecialchars($p['pdf_path']); ?>" target="_blank" class="btn btn-sm btn-staff btn-outline-danger" style="color:#dc3545; border-color:#dc3545;">
                                    <i class="bi bi-file-earmark-pdf"></i> تحميل الفاتورة
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
