<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];
$page_title = 'طلباتي';

$search = trim((string) ($_GET['search'] ?? ''));
$status_filter = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "WHERE oa.employee_id = ? AND oa.status = 'active'";
$params = [$employee_id];

if ($search !== '') {
    $where .= " AND (o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.product_name LIKE ? OR o.id = ?)";
    $like = '%' . $search . '%';
    $search_id = is_numeric($search) ? (int) $search : 0;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $search_id;
}

if ($status_filter !== '') {
    $where .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order_assignment oa INNER JOIN tbl_order o ON o.id = oa.order_id {$where}");
$count_stmt->execute($params);
$total_orders = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_orders / $per_page));

$stmt = $pdo->prepare("
    SELECT o.*, oa.assigned_at
    FROM tbl_order_assignment oa
    INNER JOIN tbl_order o ON o.id = oa.order_id
    {$where}
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="staff-card">
    <form method="get" class="row g-2 mb-3">
        <div class="col-12 col-md-5">
            <input type="text" name="search" class="form-control" placeholder="بحث باسم العميل، الهاتف، المنتج، رقم الطلب..."
                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-6 col-md-3">
            <select name="status" class="form-select">
                <option value="">جميع الحالات</option>
                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>معلق</option>
                <option value="Confirmed" <?php echo $status_filter === 'Confirmed' ? 'selected' : ''; ?>>مؤكد</option>
                <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>مكتمل</option>
                <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>ملغي</option>
                <option value="Returned" <?php echo $status_filter === 'Returned' ? 'selected' : ''; ?>>مرتجع</option>
            </select>
        </div>
        <div class="col-3 col-md-2">
            <button type="submit" class="btn btn-primary btn-staff w-100"><i class="bi bi-search"></i> بحث</button>
        </div>
        <div class="col-3 col-md-2">
            <a href="orders.php" class="btn btn-outline-secondary btn-staff w-100"><i class="bi bi-x-circle"></i></a>
        </div>
    </form>

    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">
        إجمالي الطلبات: <?php echo $total_orders; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="staff-empty">
            <i class="bi bi-inbox"></i>
            <p>لا توجد طلبات مطابقة.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table staff-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العميل</th>
                        <th>الهاتف</th>
                        <th>المنتج</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>تاريخ الطلب</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <?php $sc = strtolower($o['order_status'] ?? ''); ?>
                        <tr>
                            <td><strong>#<?php echo (int) $o['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($o['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td dir="ltr" style="text-align:right;"><?php echo htmlspecialchars($o['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($o['product_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) ($o['total_price'] ?? 0), 0); ?> دج</td>
                            <td><span class="status-badge <?php echo $sc; ?>"><?php echo htmlspecialchars($o['order_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td style="font-size:13px;color:var(--text-secondary);"><?php echo htmlspecialchars($o['order_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="order-details.php?id=<?php echo (int) $o['id']; ?>" class="btn btn-sm btn-staff btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mt-3">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
