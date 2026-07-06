<?php require_once('header.php'); ?>
<?php
require_once('inc/audit.php');
require_once('inc/error_logger.php');
audit_ensure_tables($pdo);

$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$per_page = isset($_GET['per_page']) ? min(200, max(10, (int) $_GET['per_page'])) : 50;

$filters = [];
foreach (['entity_type', 'entity_id', 'action_type', 'source', 'performed_by_type', 'performed_by_id', 'phone', 'date_from', 'date_to', 'risk_level'] as $key) {
    if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
        $filters[$key] = trim((string) $_GET[$key]);
    }
}

$export = isset($_GET['export']) ? trim((string) $_GET['export']) : '';
if ($export !== '' && in_array($export, ['csv', 'excel'], true)) {
    $all_data = audit_search($pdo, $filters, 1, 100000);
    $rows = $all_data['data'];

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['#', 'النوع', 'المعرف', 'الإجراء', 'المصدر', 'المنفذ', 'القيمة القديمة', 'القيمة الجديدة', 'IP', 'التاريخ']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['id'],
                $r['entity_type'],
                $r['entity_id'],
                audit_get_action_label($r['action_type']),
                audit_get_source_label($r['source']),
                $r['performed_by_type'] . '#' . $r['performed_by_id'],
                mb_substr((string) ($r['old_value'] ?? ''), 0, 200),
                mb_substr((string) ($r['new_value'] ?? ''), 0, 200),
                $r['ip_address'],
                $r['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.xls"');
    echo '<table border="1">';
    echo '<tr><th>#</th><th>النوع</th><th>المعرف</th><th>الإجراء</th><th>المصدر</th><th>المنفذ</th><th>القيمة القديمة</th><th>القيمة الجديدة</th><th>IP</th><th>التاريخ</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) $r['id']) . '</td>';
        echo '<td>' . htmlspecialchars($r['entity_type']) . '</td>';
        echo '<td>' . htmlspecialchars((string) $r['entity_id']) . '</td>';
        echo '<td>' . htmlspecialchars(audit_get_action_label($r['action_type'])) . '</td>';
        echo '<td>' . htmlspecialchars(audit_get_source_label($r['source'])) . '</td>';
        echo '<td>' . htmlspecialchars($r['performed_by_type'] . '#' . $r['performed_by_id']) . '</td>';
        echo '<td>' . htmlspecialchars(mb_substr((string) ($r['old_value'] ?? ''), 0, 200)) . '</td>';
        echo '<td>' . htmlspecialchars(mb_substr((string) ($r['new_value'] ?? ''), 0, 200)) . '</td>';
        echo '<td>' . htmlspecialchars($r['ip_address']) . '</td>';
        echo '<td>' . htmlspecialchars($r['created_at']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

$result = audit_search($pdo, $filters, $page, $per_page);
$logs = $result['data'];
$total = $result['total'];
$total_pages = $result['total_pages'];

$entity_types = $dbRepo->query("SELECT DISTINCT entity_type FROM tbl_audit_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
$action_types = $dbRepo->query("SELECT DISTINCT action_type FROM tbl_audit_log ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
$sources = $dbRepo->query("SELECT DISTINCT source FROM tbl_audit_log ORDER BY source")->fetchAll(PDO::FETCH_COLUMN);
?>
<section class="content-header">
    <div class="content-header-left">
        <h1>سجل التدقيق (Audit Log)</h1>
    </div>
    <div class="content-header-right">
        <a href="audit-log.php?export=csv<?php
            foreach ($filters as $k => $v) echo '&' . urlencode($k) . '=' . urlencode($v);
        ?>" class="btn btn-success btn-sm"><i class="fa fa-download"></i> تصدير CSV</a>
        <a href="audit-log.php?export=excel<?php
            foreach ($filters as $k => $v) echo '&' . urlencode($k) . '=' . urlencode($v);
        ?>" class="btn btn-primary btn-sm"><i class="fa fa-file-excel-o"></i> تصدير Excel</a>
    </div>
</section>

<section class="content">
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">تصفية السجل</h3>
        </div>
        <div class="box-body">
            <form method="get" action="audit-log.php" class="form-inline" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <div class="form-group">
                    <label>النوع &nbsp;</label>
                    <select name="entity_type" class="form-control" style="width:140px;">
                        <option value="">الكل</option>
                        <?php foreach ($entity_types as $et): ?>
                        <option value="<?php echo htmlspecialchars($et, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filters['entity_type'] ?? '') === $et ? 'selected' : ''; ?>><?php echo htmlspecialchars($et, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>المعرف &nbsp;</label>
                    <input type="number" name="entity_id" class="form-control" style="width:100px;" value="<?php echo htmlspecialchars((string) ($filters['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="ID">
                </div>
                <div class="form-group">
                    <label>الإجراء &nbsp;</label>
                    <select name="action_type" class="form-control" style="width:160px;">
                        <option value="">الكل</option>
                        <?php foreach ($action_types as $at): ?>
                        <option value="<?php echo htmlspecialchars($at, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filters['action_type'] ?? '') === $at ? 'selected' : ''; ?>><?php echo htmlspecialchars(audit_get_action_label($at), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>المصدر &nbsp;</label>
                    <select name="source" class="form-control" style="width:130px;">
                        <option value="">الكل</option>
                        <?php foreach ($sources as $src): ?>
                        <option value="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filters['source'] ?? '') === $src ? 'selected' : ''; ?>><?php echo htmlspecialchars(audit_get_source_label($src), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>الهاتف &nbsp;</label>
                    <input type="text" name="phone" class="form-control" style="width:130px;" value="<?php echo htmlspecialchars((string) ($filters['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="رقم العميل">
                </div>
                <div class="form-group">
                    <label>من &nbsp;</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label>إلى &nbsp;</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <button type="submit" class="btn btn-info"><i class="fa fa-search"></i> بحث</button>
                <a href="audit-log.php" class="btn btn-default"><i class="fa fa-times"></i> إلغاء</a>
            </form>
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">السجل (<?php echo number_format($total); ?> إدخال)</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>النوع</th>
                        <th>المعرف</th>
                        <th>الإجراء</th>
                        <th>المصدر</th>
                        <th>المنفذ</th>
                        <th>البيانات</th>
                        <th>IP</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="9" class="text-center text-muted">لا توجد إدخالات</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo (int) $log['id']; ?></td>
                        <td>
                            <span class="label label-<?php
                                $colors = ['order' => 'primary', 'employee' => 'success', 'security' => 'danger', 'commission' => 'warning', 'recovery_task' => 'info'];
                                echo $colors[$log['entity_type']] ?? 'default';
                            ?>"><?php echo htmlspecialchars($log['entity_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td>
                            <?php if ($log['entity_type'] === 'order' && $log['entity_id'] > 0): ?>
                                <a href="order-details.php?id=<?php echo (int) $log['entity_id']; ?>">#<?php echo (int) $log['entity_id']; ?></a>
                            <?php else: ?>
                                <?php echo (int) $log['entity_id']; ?>
                            <?php endif; ?>
                            <?php if (!empty($log['order_customer_name'])): ?>
                                <br><small><?php echo htmlspecialchars($log['order_customer_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <i class="fa <?php echo audit_get_action_icon($log['action_type']); ?>"></i>
                            <?php echo htmlspecialchars(audit_get_action_label($log['action_type']), ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td><span class="label label-default"><?php echo htmlspecialchars(audit_get_source_label($log['source']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><small><?php echo htmlspecialchars($log['performed_by_type'] . '#' . $log['performed_by_id'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;font-size:12px;">
                            <?php
                            $parts = [];
                            if ($log['old_value'] !== null && $log['old_value'] !== '') {
                                $parts[] = '<span class="text-danger"><s>' . htmlspecialchars(mb_substr((string) $log['old_value'], 0, 150), ENT_QUOTES, 'UTF-8') . '</s></span>';
                            }
                            if ($log['new_value'] !== null && $log['new_value'] !== '') {
                                $parts[] = '<span class="text-success">' . htmlspecialchars(mb_substr((string) $log['new_value'], 0, 150), ENT_QUOTES, 'UTF-8') . '</span>';
                            }
                            echo implode(' → ', $parts) ?: '<span class="text-muted">-</span>';
                            ?>
                        </td>
                        <td><small><?php echo htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td><small><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="box-footer">
            <ul class="pagination pagination-sm no-margin">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <a href="?p=<?php echo $i; ?>&per_page=<?php echo $per_page; ?><?php
                        foreach ($filters as $k => $v) echo '&' . urlencode($k) . '=' . urlencode($v);
                    ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
            <span class="pull-right" style="line-height:30px;">إجمالي: <?php echo number_format($total); ?></span>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once('footer.php'); ?>
