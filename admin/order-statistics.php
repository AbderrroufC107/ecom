<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

require_once('header.php');

if (function_exists('admin_ensure_order_ecotrack_columns')) {
    admin_ensure_order_ecotrack_columns($pdo);
}

if (!function_exists('stats_h')) {
    function stats_h($value)
    { global $dbRepo;
    global $dbRepo;

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('stats_money')) {
    function stats_money($value)
    { global $dbRepo;
    global $dbRepo;

        return number_format((float) $value) . ' دج';
    }
}

if (!function_exists('stats_text')) {
    function stats_text($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s{2,}/u', ' ', $value);
        return trim((string) $value);
    }
}

if (!function_exists('stats_status_meta')) {
    function stats_status_meta($status)
    { global $dbRepo;
    global $dbRepo;

        $status = trim((string) $status);
        $map = [
            'Pending' => ['label' => 'قيد التأكيد', 'class' => 'pending'],
            'Completed' => ['label' => 'مكتمل', 'class' => 'completed'],
            'Returned' => ['label' => 'مرتجع', 'class' => 'returned'],
            'Cancelled' => ['label' => 'ملغى', 'class' => 'cancelled'],
            'Processing' => ['label' => 'قيد المعالجة', 'class' => 'processing'],
        ];
        return $map[$status] ?? ['label' => ($status !== '' ? $status : 'غير محدد'), 'class' => 'default'];
    }
}

if (!function_exists('stats_shipping_meta')) {
    function stats_shipping_meta($remote_status, $tracking = '')
    { global $dbRepo;
    global $dbRepo;

        $status = mb_strtolower(stats_text($remote_status), 'UTF-8');
        if (trim((string) $tracking) === '') {
            return ['label' => 'بدون تتبع', 'class' => 'empty', 'icon' => 'fa-minus-circle'];
        }
        if ($status === '') {
            return ['label' => 'بانتظار المزامنة', 'class' => 'waiting', 'icon' => 'fa-clock-o'];
        }
        if (strpos($status, 'livr') !== false || strpos($status, 'delivered') !== false || strpos($status, 'تم التسليم') !== false) {
            return ['label' => stats_text($remote_status), 'class' => 'delivered', 'icon' => 'fa-check-circle'];
        }
        if (strpos($status, 'retour') !== false || strpos($status, 'returned') !== false || strpos($status, 'refus') !== false || strpos($status, 'مرتجع') !== false || strpos($status, 'رفض') !== false) {
            return ['label' => stats_text($remote_status), 'class' => 'returned', 'icon' => 'fa-undo'];
        }
        if (strpos($status, 'injoignable') !== false || strpos($status, 'annul') !== false || strpos($status, 'pas de reponse') !== false || strpos($status, 'failed') !== false) {
            return ['label' => stats_text($remote_status), 'class' => 'warning', 'icon' => 'fa-exclamation-circle'];
        }
        return ['label' => stats_text($remote_status), 'class' => 'transit', 'icon' => 'fa-truck'];
    }
}

if (!function_exists('stats_column')) {
    function stats_column($alias, $column)
    { global $dbRepo;
    global $dbRepo;

        return $alias !== '' ? $alias . '.' . $column : $column;
    }
}

if (!function_exists('stats_tracking_condition')) {
    function stats_tracking_condition($alias, $filter)
    { global $dbRepo;
    global $dbRepo;

        $tracking = stats_column($alias, 'ecotrack_tracking');
        $remote = stats_column($alias, 'ecotrack_remote_status');
        $has_tracking = "($tracking IS NOT NULL AND $tracking <> '')";
        $delivered = "($remote LIKE '%Livré%' OR $remote LIKE '%Livre%' OR $remote LIKE '%LivrÃ%' OR $remote LIKE '%Delivered%')";
        $returned = "($remote LIKE '%Retour%' OR $remote LIKE '%Returned%' OR $remote LIKE '%Refus%' OR $remote LIKE '%رفض%' OR $remote LIKE '%مرتجع%')";
        $warning = "($remote LIKE '%injoignable%' OR $remote LIKE '%pas de reponse%' OR $remote LIKE '%annul%' OR $remote LIKE '%failed%')";

        if ($filter === 'has_tracking') {
            return $has_tracking;
        }
        if ($filter === 'no_tracking') {
            return "($tracking IS NULL OR $tracking = '')";
        }
        if ($filter === 'delivered') {
            return "($has_tracking AND $delivered)";
        }
        if ($filter === 'returned') {
            return "($has_tracking AND $returned)";
        }
        if ($filter === 'warning') {
            return "($has_tracking AND $warning)";
        }
        if ($filter === 'transit') {
            return "($has_tracking AND NOT $delivered AND NOT $returned AND NOT $warning)";
        }
        return '';
    }
}

if (!function_exists('stats_build_where')) {
    function stats_build_where($alias = '', $product_alias = '')
    { global $dbRepo;
    global $dbRepo;

        $conditions = [];
        $params = [];
        $prefix = $alias !== '' ? $alias . '.' : '';

        if (!empty($_GET['date_from'])) {
            $conditions[] = $prefix . 'order_date >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $conditions[] = $prefix . 'order_date <= ?';
            $params[] = $_GET['date_to'] . ' 23:59:59';
        }
        if (!empty($_GET['status'])) {
            $conditions[] = $prefix . 'order_status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['product_name'])) {
            if ($product_alias !== '') {
                $conditions[] = '(' . $product_alias . '.p_name = ? OR ' . $prefix . 'product_name = ?)';
                $params[] = $_GET['product_name'];
                $params[] = $_GET['product_name'];
            } else {
                $conditions[] = $prefix . 'product_name = ?';
                $params[] = $_GET['product_name'];
            }
        }
        if (!empty($_GET['shipping_status'])) {
            $shipping_condition = stats_tracking_condition($alias, $_GET['shipping_status']);
            if ($shipping_condition !== '') {
                $conditions[] = $shipping_condition;
            }
        }
        if (!empty($_GET['q'])) {
            $q = '%' . trim((string) $_GET['q']) . '%';
            $conditions[] = '('
                . $prefix . 'id LIKE ? OR '
                . $prefix . 'customer_name LIKE ? OR '
                . $prefix . 'customer_phone LIKE ? OR '
                . $prefix . 'product_name LIKE ? OR '
                . $prefix . 'ecotrack_tracking LIKE ? OR '
                . $prefix . 'ecotrack_remote_status LIKE ?'
                . ')';
            array_push($params, $q, $q, $q, $q, $q, $q);
        }

        return [$conditions, $params];
    }
}

if (!function_exists('stats_fetch_value')) {
    function stats_fetch_value(PDO $pdo, $sql, array $params = [])
    { global $dbRepo;
    global $dbRepo;

        $statement = $dbRepo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }
}

$status_options = [
    '' => 'كل حالات الطلب',
    'Pending' => 'قيد التأكيد',
    'Completed' => 'مكتمل',
    'Returned' => 'مرتجع',
    'Cancelled' => 'ملغى',
];

$shipping_options = [
    '' => 'كل حالات التتبع',
    'has_tracking' => 'له رقم تتبع',
    'no_tracking' => 'بدون تتبع',
    'delivered' => 'تم التسليم',
    'transit' => 'قيد التوصيل',
    'returned' => 'مرتجع',
    'warning' => 'تحتاج متابعة',
];

$other_expenses = isset($_GET['other_expenses']) ? max(0, (float) $_GET['other_expenses']) : 0;

[$base_conditions, $base_params] = stats_build_where('', '');
$base_where = $base_conditions ? 'WHERE ' . implode(' AND ', $base_conditions) : '';

[$order_conditions, $order_params] = stats_build_where('o', 'p');
$order_where = $order_conditions ? 'WHERE ' . implode(' AND ', $order_conditions) : '';

$total_orders = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order $base_where", $base_params);

$completed_conditions = $base_conditions;
$completed_params = $base_params;
$completed_conditions[] = "order_status = 'Completed'";
$completed_where = 'WHERE ' . implode(' AND ', $completed_conditions);
$completed_orders = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order $completed_where", $completed_params);

$pending_conditions = $base_conditions;
$pending_params = $base_params;
$pending_conditions[] = "order_status = 'Pending'";
$pending_where = 'WHERE ' . implode(' AND ', $pending_conditions);
$pending_orders = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order $pending_where", $pending_params);

$returned_conditions = $base_conditions;
$returned_params = $base_params;
$returned_conditions[] = "order_status = 'Returned'";
$returned_where = 'WHERE ' . implode(' AND ', $returned_conditions);
$returned_orders = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order $returned_where", $returned_params);

$revenue_conditions = $base_conditions;
$revenue_params = $base_params;
$revenue_conditions[] = "order_status = 'Completed'";
$revenue_where = 'WHERE ' . implode(' AND ', $revenue_conditions);
$total_revenue = (float) stats_fetch_value($pdo, "SELECT COALESCE(SUM(total_price), 0) FROM tbl_order $revenue_where", $revenue_params);

$profit_conditions = $order_conditions;
$profit_params = $order_params;
$profit_conditions[] = "o.order_status = 'Completed'";
$profit_where = 'WHERE ' . implode(' AND ', $profit_conditions);
$total_profit = (float) stats_fetch_value(
    $pdo,
    "SELECT COALESCE(SUM(o.quantity * (o.unit_price - COALESCE(p.purchase_price, 0))), 0)
     FROM tbl_order o
     LEFT JOIN tbl_product p ON o.product_id = p.p_id
     $profit_where",
    $profit_params
) - $other_expenses;

$tracked_orders = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order WHERE ecotrack_tracking IS NOT NULL AND ecotrack_tracking <> ''");
$delivered_shipments = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order WHERE " . stats_tracking_condition('', 'delivered'));
$returned_shipments = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order WHERE " . stats_tracking_condition('', 'returned'));
$followup_shipments = (int) stats_fetch_value($pdo, "SELECT COUNT(*) FROM tbl_order WHERE " . stats_tracking_condition('', 'warning'));

$statement = $dbRepo->prepare("
    SELECT DISTINCT COALESCE(NULLIF(p.p_name, ''), NULLIF(o.product_name, '')) AS product_name
    FROM tbl_order o
    LEFT JOIN tbl_product p ON o.product_id = p.p_id
    WHERE COALESCE(NULLIF(p.p_name, ''), NULLIF(o.product_name, '')) IS NOT NULL
    ORDER BY product_name
");
$statement->execute();
$product_options = $statement->fetchAll(PDO::FETCH_COLUMN);

$statement = $dbRepo->prepare("
    SELECT o.*, p.purchase_price, p.p_name, c.cust_name
    FROM tbl_order o
    LEFT JOIN tbl_product p ON o.product_id = p.p_id
    LEFT JOIN tbl_customer c ON o.customer_id = c.id
    $order_where
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT 200
");
$statement->execute($order_params);
$orders = $statement->fetchAll(PDO::FETCH_ASSOC);
$shown_orders = count($orders);

$active_filters = [];
if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
    $active_filters[] = 'الفترة: ' . (!empty($_GET['date_from']) ? $_GET['date_from'] : '...') . ' إلى ' . (!empty($_GET['date_to']) ? $_GET['date_to'] : '...');
}
if (!empty($_GET['status'])) {
    $active_filters[] = 'حالة الطلب: ' . ($status_options[$_GET['status']] ?? $_GET['status']);
}
if (!empty($_GET['shipping_status'])) {
    $active_filters[] = 'التتبع: ' . ($shipping_options[$_GET['shipping_status']] ?? $_GET['shipping_status']);
}
if (!empty($_GET['product_name'])) {
    $active_filters[] = 'المنتج: ' . stats_text($_GET['product_name']);
}
if (!empty($_GET['q'])) {
    $active_filters[] = 'البحث: ' . stats_text($_GET['q']);
}
if ($other_expenses > 0) {
    $active_filters[] = 'مصاريف إضافية: ' . stats_money($other_expenses);
}
?>

<style>
.stats-pro-page,
.stats-pro-page * {
    box-sizing: border-box;
}
.stats-pro-page {
    direction: rtl;
    color: #172033;
    background: #f5f7fb;
    padding-bottom: 34px;
}
.stats-pro-page .stats-shell {
    max-width: 100%;
    margin: 0 auto;
}
.stats-topbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}
.stats-title h2 {
    margin: 0;
    color: #101828;
    font-size: 26px;
    font-weight: 950;
    letter-spacing: 0;
}
.stats-title p {
    margin: 6px 0 0;
    color: #667085;
    line-height: 1.7;
    font-weight: 750;
}
.stats-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.stats-actions a,
.stats-actions button,
.stats-filter button,
.stats-filter a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #172033;
    padding: 0 13px;
    font-weight: 900;
    text-decoration: none;
}
.stats-actions .primary,
.stats-filter .primary {
    border-color: #0f9488;
    background: #0f9488;
    color: #fff;
}
.stats-kpis {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.stats-kpi {
    min-width: 0;
    border: 1px solid #d9e4f0;
    border-radius: 12px;
    background: #fff;
    padding: 13px 14px;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.stats-kpi span {
    display: block;
    color: #607086;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stats-kpi strong {
    display: block;
    margin-top: 7px;
    color: #0f172a;
    font-size: 24px;
    font-weight: 950;
    line-height: 1.1;
}
.stats-kpi.green strong { color: #079455; }
.stats-kpi.orange strong { color: #b54708; }
.stats-kpi.red strong { color: #c01048; }
.stats-kpi.blue strong { color: #175cd3; }
.stats-filter {
    margin-bottom: 14px;
    border: 1px solid #d9e4f0;
    border-radius: 14px;
    background: #fff;
    padding: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
}
.stats-filter-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
    align-items: end;
}
.stats-field label {
    display: block;
    margin-bottom: 6px;
    color: #344054;
    font-size: 12px;
    font-weight: 950;
}
.stats-field input,
.stats-field select {
    width: 100%;
    height: 40px;
    border: 1.5px solid #b9c7d8;
    border-radius: 10px;
    background: #fff;
    color: #101828;
    padding: 0 11px;
    font-weight: 850;
}
.stats-field input:focus,
.stats-field select:focus {
    outline: none;
    border-color: #0f9488;
    box-shadow: 0 0 0 3px rgba(15, 148, 136, .13);
}
.stats-filter-actions {
    display: flex;
    gap: 8px;
}
.stats-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 12px;
}
.stats-chip {
    display: inline-flex;
    max-width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #f8fafc;
    color: #344054;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 850;
}
.stats-panel {
    overflow: hidden;
    border: 1px solid #d9e4f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .05);
}
.stats-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #e5edf5;
    background: #fbfdff;
}
.stats-panel-head h3 {
    margin: 0;
    color: #101828;
    font-size: 18px;
    font-weight: 950;
}
.stats-panel-head span {
    color: #607086;
    font-weight: 850;
}
.stats-table-wrap {
    width: 100%;
    overflow-x: hidden;
}
.stats-orders-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
}
.stats-orders-table th {
    background: #eaf1f8;
    color: #172033;
    border-bottom: 1px solid #c7d7e9;
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 950;
    text-align: right;
}
.stats-orders-table td {
    border-bottom: 1px solid #e3ebf4;
    padding: 12px 10px;
    vertical-align: middle;
    color: #172033;
    font-weight: 800;
    line-height: 1.55;
}
.stats-orders-table tbody tr:hover td {
    background: #f7fbfb;
}
.stats-orders-table th:nth-child(1),
.stats-orders-table td:nth-child(1) { width: 21%; }
.stats-orders-table th:nth-child(2),
.stats-orders-table td:nth-child(2) { width: 18%; }
.stats-orders-table th:nth-child(3),
.stats-orders-table td:nth-child(3) { width: 22%; }
.stats-orders-table th:nth-child(4),
.stats-orders-table td:nth-child(4) { width: 14%; }
.stats-orders-table th:nth-child(5),
.stats-orders-table td:nth-child(5) { width: 11%; }
.stats-orders-table th:nth-child(6),
.stats-orders-table td:nth-child(6) { width: 14%; }
.order-main,
.customer-main,
.tracking-main {
    min-width: 0;
}
.order-title,
.customer-main strong,
.tracking-main strong {
    display: block;
    color: #101828;
    font-weight: 950;
    overflow-wrap: anywhere;
}
.order-meta,
.customer-main span,
.finance-sub,
.tracking-sub {
    display: block;
    margin-top: 4px;
    color: #667085;
    font-size: 12px;
    font-weight: 800;
    overflow-wrap: anywhere;
}
.money-strong {
    display: block;
    color: #101828;
    font-weight: 950;
}
.profit-positive { color: #079455; }
.profit-negative { color: #c01048; }
.stats-badge,
.tracking-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    border-radius: 999px;
    padding: 6px 9px;
    font-size: 12px;
    font-weight: 950;
    white-space: normal;
}
.stats-badge.pending { background: #fff7ed; color: #b54708; }
.stats-badge.completed { background: #dcfae6; color: #067647; }
.stats-badge.returned { background: #f4e8ff; color: #6941c6; }
.stats-badge.cancelled { background: #fee4e2; color: #b42318; }
.stats-badge.processing,
.stats-badge.default { background: #eff4ff; color: #175cd3; }
.tracking-badge.delivered { background: #dcfae6; color: #067647; }
.tracking-badge.returned,
.tracking-badge.warning { background: #fee4e2; color: #b42318; }
.tracking-badge.transit { background: #fef0c7; color: #93370d; }
.tracking-badge.waiting,
.tracking-badge.empty { background: #f2f4f7; color: #475467; }
.row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.row-actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #172033;
    padding: 0 9px;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
}
.row-actions .ok { border-color: #0f9488; background: #0f9488; color: #fff; }
.row-actions .danger { border-color: #fda29b; background: #fff5f5; color: #b42318; }
.empty-state {
    padding: 34px 18px;
    text-align: center;
    color: #667085;
    font-weight: 850;
}
@media (max-width: 1300px) {
    .stats-kpis,
    .stats-filter-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 900px) {
    .stats-kpis,
    .stats-filter-grid {
        grid-template-columns: 1fr;
    }
    .stats-orders-table,
    .stats-orders-table thead,
    .stats-orders-table tbody,
    .stats-orders-table th,
    .stats-orders-table td,
    .stats-orders-table tr {
        display: block;
        width: 100% !important;
    }
    .stats-orders-table thead {
        display: none;
    }
    .stats-orders-table tr {
        border-bottom: 1px solid #d9e4f0;
        padding: 10px 0;
    }
    .stats-orders-table td {
        border-bottom: 0;
        padding: 8px 12px;
    }
    .stats-orders-table td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 4px;
        color: #667085;
        font-size: 11px;
        font-weight: 950;
    }
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>إحصائيات الطلبات</h1>
    </div>
</section>

<section class="content stats-pro-page">
    <div class="stats-shell">
        <div class="stats-topbar">
            <div class="stats-title">
                <h2>إحصائيات الطلبات والتتبع</h2>
                <p>الطلبات، الأرباح، وحالة الشحنة تظهر في نفس الصف حتى لا تضطر لفتح صفحة تتبع منفصلة.</p>
            </div>
            <div class="stats-actions">
                <a href="order.php"><i class="fa fa-list"></i> إدارة الطلبات</a>
                <a href="ecotrack-diagnostics.php"><i class="fa fa-stethoscope"></i> ECOTRACK</a>
                <button type="button" class="primary" onclick="window.print()"><i class="fa fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="stats-kpis">
            <div class="stats-kpi blue"><span>إجمالي الطلبات</span><strong><?php echo number_format($total_orders); ?></strong></div>
            <div class="stats-kpi green"><span>طلبات مكتملة</span><strong><?php echo number_format($completed_orders); ?></strong></div>
            <div class="stats-kpi orange"><span>قيد التأكيد</span><strong><?php echo number_format($pending_orders); ?></strong></div>
            <div class="stats-kpi red"><span>مرتجعة</span><strong><?php echo number_format($returned_orders); ?></strong></div>
            <div class="stats-kpi"><span>المبيعات المكتملة</span><strong><?php echo stats_money($total_revenue); ?></strong></div>
            <div class="stats-kpi green"><span>صافي الربح</span><strong><?php echo stats_money($total_profit); ?></strong></div>
        </div>

        <div class="stats-kpis">
            <div class="stats-kpi blue"><span>شحنات لها تتبع</span><strong><?php echo number_format($tracked_orders); ?></strong></div>
            <div class="stats-kpi green"><span>تم التسليم</span><strong><?php echo number_format($delivered_shipments); ?></strong></div>
            <div class="stats-kpi orange"><span>تحتاج متابعة</span><strong><?php echo number_format($followup_shipments); ?></strong></div>
            <div class="stats-kpi red"><span>شحنات مرتجعة</span><strong><?php echo number_format($returned_shipments); ?></strong></div>
            <div class="stats-kpi"><span>المعروض في القائمة</span><strong><?php echo number_format($shown_orders); ?></strong></div>
            <div class="stats-kpi"><span>المصاريف</span><strong><?php echo stats_money($other_expenses); ?></strong></div>
        </div>

        <form class="stats-filter" method="get" action="order-statistics.php">
            <div class="stats-filter-grid">
                <div class="stats-field">
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="<?php echo stats_h($_GET['date_from'] ?? ''); ?>">
                </div>
                <div class="stats-field">
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="<?php echo stats_h($_GET['date_to'] ?? ''); ?>">
                </div>
                <div class="stats-field">
                    <label>حالة الطلب</label>
                    <select name="status">
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo stats_h($value); ?>" <?php echo (($_GET['status'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo stats_h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="stats-field">
                    <label>حالة التتبع</label>
                    <select name="shipping_status">
                        <?php foreach ($shipping_options as $value => $label): ?>
                            <option value="<?php echo stats_h($value); ?>" <?php echo (($_GET['shipping_status'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo stats_h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="stats-field">
                    <label>المنتج</label>
                    <select name="product_name">
                        <option value="">كل المنتجات</option>
                        <?php foreach ($product_options as $product_name): ?>
                            <?php $product_name = stats_text($product_name); ?>
                            <option value="<?php echo stats_h($product_name); ?>" <?php echo (($_GET['product_name'] ?? '') === $product_name) ? 'selected' : ''; ?>><?php echo stats_h($product_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="stats-field">
                    <label>بحث باسم/هاتف/تتبع</label>
                    <input type="search" name="q" value="<?php echo stats_h($_GET['q'] ?? ''); ?>" placeholder="اسم، هاتف، رقم طلب أو تتبع">
                </div>
                <div class="stats-field">
                    <label>مصاريف إضافية</label>
                    <input type="number" name="other_expenses" min="0" step="0.01" value="<?php echo stats_h($_GET['other_expenses'] ?? '0'); ?>">
                </div>
                <div class="stats-filter-actions">
                    <button class="primary" type="submit"><i class="fa fa-filter"></i> تطبيق</button>
                    <a href="order-statistics.php"><i class="fa fa-refresh"></i> تصفير</a>
                </div>
            </div>
            <div class="stats-chips">
                <?php if ($active_filters): ?>
                    <?php foreach ($active_filters as $active_filter): ?>
                        <span class="stats-chip"><?php echo stats_h($active_filter); ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="stats-chip">لا توجد فلاتر مفعلة. يتم عرض آخر 200 طلب.</span>
                <?php endif; ?>
            </div>
        </form>

        <div class="stats-panel">
            <div class="stats-panel-head">
                <h3>قائمة الطلبات</h3>
                <span><?php echo number_format($shown_orders); ?> طلب ظاهر</span>
            </div>
            <?php if (!$orders): ?>
                <div class="empty-state">لا توجد طلبات مطابقة للفلاتر الحالية.</div>
            <?php else: ?>
                <div class="stats-table-wrap">
                    <table class="stats-orders-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>الطلب</th>
                                <th>العميل</th>
                                <th>التتبع والشحنة</th>
                                <th>المالية</th>
                                <th>الحالة</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $order_id = (int) ($order['id'] ?? 0);
                                $product_name = stats_text($order['p_name'] ?: ($order['product_name'] ?? ''));
                                $customer_name = stats_text($order['cust_name'] ?: ($order['customer_name'] ?? ''));
                                $customer_phone = stats_text($order['customer_phone'] ?? '');
                                $tracking_number = stats_text($order['ecotrack_tracking'] ?? '');
                                $remote_status = stats_text($order['ecotrack_remote_status'] ?? '');
                                $shipping_meta = stats_shipping_meta($remote_status, $tracking_number);
                                $status_meta = stats_status_meta($order['order_status'] ?? '');
                                $purchase_price = (float) ($order['purchase_price'] ?? 0);
                                $unit_price = (float) ($order['unit_price'] ?? 0);
                                $quantity = (int) ($order['quantity'] ?? 0);
                                $profit = $quantity * ($unit_price - $purchase_price);
                                $order_date = !empty($order['order_date']) ? date('d/m/Y H:i', strtotime($order['order_date'])) : '-';
                                ?>
                                <tr>
                                    <td data-label="الطلب">
                                        <div class="order-main">
                                            <span class="order-title">#<?php echo $order_id; ?> - <?php echo stats_h($product_name ?: 'منتج غير محدد'); ?></span>
                                            <span class="order-meta"><?php echo stats_h($order_date); ?> · الكمية <?php echo number_format($quantity); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="العميل">
                                        <div class="customer-main">
                                            <strong><?php echo stats_h($customer_name ?: 'عميل غير محدد'); ?></strong>
                                            <?php if ($customer_phone !== ''): ?><span><?php echo stats_h($customer_phone); ?></span><?php endif; ?>
                                            <span><?php echo stats_h(trim(($order['wilaya'] ?? '') . ' - ' . ($order['commune'] ?? ''), ' -')); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="التتبع والشحنة">
                                        <div class="tracking-main">
                                            <?php if ($tracking_number !== ''): ?>
                                                <strong><?php echo stats_h($tracking_number); ?></strong>
                                            <?php else: ?>
                                                <strong>بدون رقم تتبع</strong>
                                            <?php endif; ?>
                                            <span class="tracking-badge <?php echo stats_h($shipping_meta['class']); ?>">
                                                <i class="fa <?php echo stats_h($shipping_meta['icon']); ?>"></i>
                                                <?php echo stats_h($shipping_meta['label']); ?>
                                            </span>
                                            <?php if (!empty($order['ecotrack_remote_time'])): ?>
                                                <span class="tracking-sub"><?php echo stats_h(date('d/m/Y H:i', strtotime($order['ecotrack_remote_time']))); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="المالية">
                                        <span class="money-strong"><?php echo stats_money($order['total_price'] ?? 0); ?></span>
                                        <span class="finance-sub <?php echo $profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">الربح: <?php echo stats_money($profit); ?></span>
                                    </td>
                                    <td data-label="الحالة">
                                        <span class="stats-badge <?php echo stats_h($status_meta['class']); ?>"><?php echo stats_h($status_meta['label']); ?></span>
                                    </td>
                                    <td data-label="الإجراء">
                                        <div class="row-actions">
                                            <a href="order-details.php?id=<?php echo $order_id; ?>#tab_ecotrack"><i class="fa fa-truck"></i> تفاصيل الشحنة</a>
                                            <?php if (($order['order_status'] ?? '') === 'Pending'): ?>
                                                <a class="ok" href="order-change-status.php?id=<?php echo $order_id; ?>&status=Completed" onclick="return confirm('تأكيد هذا الطلب؟');">تأكيد</a>
                                                <a class="danger" href="order-change-status.php?id=<?php echo $order_id; ?>&status=Cancelled" onclick="return confirm('إلغاء هذا الطلب؟');">إلغاء</a>
                                            <?php elseif (($order['order_status'] ?? '') === 'Returned'): ?>
                                                <a class="danger" href="order-change-status.php?id=<?php echo $order_id; ?>&status=Cancelled" onclick="return confirm('إلغاء الطلب المرتجع؟');">إلغاء</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    "use strict";
    var table = document.getElementById("ordersTable");
    var search = document.querySelector('input[name="q"]');
    if (!table || !search) return;

    search.addEventListener("input", function () {
        var value = search.value.trim().toLowerCase();
        table.querySelectorAll("tbody tr").forEach(function (row) {
            row.style.display = row.innerText.toLowerCase().indexOf(value) === -1 ? "none" : "";
        });
    });
})();
</script>

<?php require_once('footer.php'); ?>
