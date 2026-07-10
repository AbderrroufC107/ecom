<?php require_once('header.php'); ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
        file_put_contents(__DIR__ . '/php_error.log', date('Y-m-d H:i:s') . ' FATAL: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL, FILE_APPEND);
    }
});

if (!function_exists('admin_dashboard_status_meta')) {
    function admin_dashboard_status_meta($status) { global $dbRepo;

        $map = array(
            'Pending' => array('label' => 'معلّق', 'class' => 'is-pending', 'icon' => 'fa-clock-o', 'color' => '#b45309', 'bg' => '#fef3c7'),
            'Confirmed' => array('label' => 'مؤكد', 'class' => 'is-confirmed', 'icon' => 'fa-check-circle-o', 'color' => '#1d4ed8', 'bg' => '#dbeafe'),
            'Shipped' => array('label' => 'قيد الشحن', 'class' => 'is-shipped', 'icon' => 'fa-truck', 'color' => '#3730a3', 'bg' => '#e0e7ff'),
            'Delivered' => array('label' => 'مُسلّم', 'class' => 'is-completed', 'icon' => 'fa-check-circle', 'color' => '#166534', 'bg' => '#dcfce7'),
            'Completed' => array('label' => 'مكتمل', 'class' => 'is-completed', 'icon' => 'fa-check-circle', 'color' => '#166534', 'bg' => '#dcfce7'),
            'Returned' => array('label' => 'مرتجع', 'class' => 'is-returned', 'icon' => 'fa-undo', 'color' => '#9d174d', 'bg' => '#fce7f3'),
            'Cancelled' => array('label' => 'ملغي', 'class' => 'is-cancelled', 'icon' => 'fa-times-circle', 'color' => '#991b1b', 'bg' => '#fee2e2')
        );
        return isset($map[$status]) ? $map[$status] : array('label' => 'غير محدد', 'class' => 'is-neutral', 'icon' => 'fa-info-circle', 'color' => '#475569', 'bg' => '#f1f5f9');
    }
}
if (!function_exists('admin_format_amount')) {
    function admin_format_amount($amount) { global $dbRepo;

        return number_format((float) $amount, 0, '.', ' ') . ' د.ج';
    }
}

// Prefs
$user_id = $_SESSION['user']['id'];
$prefs = [];
try {
    $prefs_stmt = $dbRepo->prepare("SELECT dashboard_prefs FROM tbl_user WHERE id = ?");
    $prefs_stmt->execute([$user_id]);
    $dashboard_prefs_raw = $prefs_stmt->fetchColumn();
    $prefs = $dashboard_prefs_raw ? json_decode($dashboard_prefs_raw, true) : [];
    if (!is_array($prefs)) $prefs = [];
} catch (Exception $e) {
    $prefs = [];
}

    function is_widget_visible($key) { global $dbRepo;

        global $prefs;
    return !isset($prefs[$key]) || $prefs[$key] === true || $prefs[$key] === 'true';
}

// This dashboard is also the whitelisted landing page for Employee sessions
// (see header.php's $employee_allowed_pages). Employees must only ever see
// their own assigned orders here - never store-wide revenue, profit, or
// other employees' data. $__isEmployee is set by header.php, which this file
// require_once()s above, so it's already in scope here.
$__currentEmployeeId = 0;
if (!empty($__isEmployee)) {
    $__currentEmployeeId = (int) str_replace('emp_', '', (string) ($_SESSION['user']['id'] ?? ''));
}

// Cache file must be scoped separately per employee (and separately from the
// store-wide admin view) - a shared cache file would leak one employee's/the
// admin's numbers to whoever loads the dashboard next within the TTL.
$cache_file = $__currentEmployeeId > 0
    ? __DIR__ . '/cache/dashboard_kpis_cache_emp_' . $__currentEmployeeId . '.json'
    : __DIR__ . '/cache/dashboard_kpis_cache.json';
$cache_ttl = 300; // 5 minutes cache
$data_cached = false;

// Manual refresh: lets an admin force-recompute the KPIs immediately (e.g. after
// editing data directly in the database) instead of waiting out the 5-minute cache.
if (($_GET['action'] ?? '') === 'refresh_kpis') {
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }
    header('Location: index.php');
    exit;
}

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    $cache_data = json_decode(file_get_contents($cache_file), true);
    if ($cache_data) {
        extract($cache_data);
        $data_cached = true;
    }
}

if (!$data_cached && $__currentEmployeeId > 0) {
    // Employee view: every figure is scoped to this employee's own assigned
    // orders. No store-wide revenue, profit, customer counts, or peer
    // rankings - those belong to managers/admins only.
    $unassigned_count = 0;
    $stuck_count = 0;
    $unsent_count = 0;
    try {
        $unsent_count = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order o JOIN tbl_order_assignment oa ON oa.order_id = o.id WHERE oa.employee_id = ? AND (o.sync_status = 'failed' OR o.ecotrack_status = 'failed')");
        $unsent_count->execute([$__currentEmployeeId]);
        $unsent_count = $unsent_count->fetchColumn();
    } catch (Exception $e) { $unsent_count = 0; }

    $low_stock_count = 0;
    $out_stock_count = 0;
    $new_returns = 0;
    try {
        $new_returns_stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order o JOIN tbl_order_assignment oa ON oa.order_id = o.id WHERE oa.employee_id = ? AND o.order_status = 'Returned' AND DATE(o.order_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)");
        $new_returns_stmt->execute([$__currentEmployeeId]);
        $new_returns = $new_returns_stmt->fetchColumn();
    } catch (Exception $e) {}

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $this_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));

    try {
        $stmt = $dbRepo->prepare("
            SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN DATE(o.order_date) = '{$today}' THEN 1 ELSE 0 END) as today_orders,
                SUM(CASE WHEN DATE(o.order_date) = '{$yesterday}' THEN 1 ELSE 0 END) as yesterday_orders,
                SUM(CASE WHEN DATE(o.order_date) = '{$today}' AND o.order_status IN ('Completed', 'Delivered') THEN o.total_price ELSE 0 END) as today_sales,
                SUM(CASE WHEN DATE(o.order_date) = '{$yesterday}' AND o.order_status IN ('Completed', 'Delivered') THEN o.total_price ELSE 0 END) as yesterday_sales,
                SUM(CASE WHEN DATE_FORMAT(o.order_date, '%Y-%m') = '{$this_month}' AND o.order_status IN ('Completed', 'Delivered') THEN o.total_price ELSE 0 END) as month_sales,
                SUM(CASE WHEN DATE_FORMAT(o.order_date, '%Y-%m') = '{$last_month}' AND o.order_status IN ('Completed', 'Delivered') THEN o.total_price ELSE 0 END) as last_month_sales,
                SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.order_status IN ('Shipped', 'In Transit') THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) as returned_orders,
                SUM(CASE WHEN o.order_status IN ('Completed', 'Delivered') THEN 1 ELSE 0 END) as completed_orders
            FROM tbl_order o
            JOIN tbl_order_assignment oa ON oa.order_id = o.id
            WHERE oa.employee_id = ?
        ");
        $stmt->execute([$__currentEmployeeId]);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $kpis = ['total_orders'=>0,'today_orders'=>0,'yesterday_orders'=>0,'today_sales'=>0,'yesterday_sales'=>0,'month_sales'=>0,'last_month_sales'=>0,'pending_orders'=>0,'shipped_orders'=>0,'returned_orders'=>0,'completed_orders'=>0];
    }

    // No profit/margin data, and no peer ranking, for an employee's own view.
    $gross_profit = 0;
    $top_employees = [];
    $top_company = null;

    try {
        $recent_stmt = $dbRepo->prepare("
            SELECT o.id, o.customer_name, c.name as company_name, o.order_status, o.total_price, o.order_date
            FROM tbl_order o
            JOIN tbl_order_assignment oa ON oa.order_id = o.id
            LEFT JOIN tbl_delivery_company c ON o.delivery_company_id = c.id
            WHERE oa.employee_id = ?
            ORDER BY o.id DESC LIMIT 10
        ");
        $recent_stmt->execute([$__currentEmployeeId]);
        $recent_orders = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recent_orders = [];
    }
} elseif (!$data_cached) {
    // Check Unassigned Orders (If employee system is used)
    // We assume an order is unassigned if it's Pending/Confirmed and not in tbl_order_assignment with status active
    $unassigned_count = 0;
    try {
        $unassigned_stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_order o LEFT JOIN tbl_order_assignment oa ON o.id = oa.order_id WHERE o.order_status IN ('Pending', 'Confirmed') AND oa.id IS NULL");
        $unassigned_count = $unassigned_stmt->fetchColumn();
    } catch (Exception $e) {}

    try {
    // Stuck orders (Pending for more than 2 days)
    $stuck_count = $dbRepo->query("SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Pending' AND order_date < DATE_SUB(NOW(), INTERVAL 2 DAY)")->fetchColumn();

    // Low stock
    $low_stock_count = $dbRepo->query("SELECT COUNT(*) FROM tbl_product WHERE p_qty <= 5 AND p_is_active = 1")->fetchColumn();
    $out_stock_count = $dbRepo->query("SELECT COUNT(*) FROM tbl_product WHERE p_qty <= 0")->fetchColumn();

    // Unsent to delivery company (ecotrack/zrexpress failed)
    $unsent_count = 0;
    try { $unsent_count = $dbRepo->query("SELECT COUNT(*) FROM tbl_order WHERE sync_status = 'failed' OR ecotrack_status = 'failed'")->fetchColumn(); } catch (Exception $e) {}

    // New Returns (last 48 hours)
    $new_returns = $dbRepo->query("SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Returned' AND DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)")->fetchColumn();

    // Data variables
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $this_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));

    $stmt = $dbRepo->query("
        SELECT
            COUNT(*) as total_orders,
            SUM(CASE WHEN DATE(order_date) = '{$today}' THEN 1 ELSE 0 END) as today_orders,
            SUM(CASE WHEN DATE(order_date) = '{$yesterday}' THEN 1 ELSE 0 END) as yesterday_orders,
            SUM(CASE WHEN DATE(order_date) = '{$today}' AND order_status IN ('Completed', 'Delivered') THEN total_price ELSE 0 END) as today_sales,
            SUM(CASE WHEN DATE(order_date) = '{$yesterday}' AND order_status IN ('Completed', 'Delivered') THEN total_price ELSE 0 END) as yesterday_sales,
            SUM(CASE WHEN DATE_FORMAT(order_date, '%Y-%m') = '{$this_month}' AND order_status IN ('Completed', 'Delivered') THEN total_price ELSE 0 END) as month_sales,
            SUM(CASE WHEN DATE_FORMAT(order_date, '%Y-%m') = '{$last_month}' AND order_status IN ('Completed', 'Delivered') THEN total_price ELSE 0 END) as last_month_sales,
            SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN order_status IN ('Shipped', 'In Transit') THEN 1 ELSE 0 END) as shipped_orders,
            SUM(CASE WHEN order_status = 'Returned' THEN 1 ELSE 0 END) as returned_orders,
            SUM(CASE WHEN order_status IN ('Completed', 'Delivered') THEN 1 ELSE 0 END) as completed_orders
        FROM tbl_order
    ");
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

    // Profit (Gross for now)
    $tenant_id_val = 1; // Default
    if (class_exists('\SaaS\TenantContext') && method_exists('\SaaS\TenantContext', 'getTenantId')) {
        $tenant_id_val = \SaaS\TenantContext::getTenantId();
    }

    $profit_stmt = $dbRepo->query("
        SELECT SUM((o.unit_price - COALESCE(p.purchase_price, 0)) * o.quantity) as gross_profit
        FROM tbl_order o
        LEFT JOIN tbl_product p ON o.product_id = p.p_id
        WHERE o.tenant_id = " . intval($tenant_id_val) . "
        AND o.order_status IN ('Completed', 'Delivered')
    ");
    $gross_profit = $profit_stmt->fetchColumn() ?: 0;

    // Recent Orders
    $recent_orders = $dbRepo->query("
        SELECT o.id, o.customer_name, c.name as company_name, o.order_status, o.total_price, o.order_date
        FROM tbl_order o
        LEFT JOIN tbl_delivery_company c ON o.delivery_company_id = c.id
        ORDER BY o.id DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top Employees
    $top_employees = [];
    if (file_exists('inc/performance_functions.php')) {
        try {
            require_once('inc/performance_functions.php');
            if (function_exists('performance_get_ranking')) {
                performance_ensure_tables($pdo);
                $top_employees = performance_get_ranking($pdo, 5);
            }
        } catch (Exception $e) {}
    }

    // Delivery Company
    $top_company = $dbRepo->query("
        SELECT c.name, COUNT(o.id) as total_shipments,
               SUM(CASE WHEN o.order_status IN ('Completed', 'Delivered') THEN 1 ELSE 0 END) as successful,
               SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) as returned
        FROM tbl_order o
        JOIN tbl_delivery_company c ON o.delivery_company_id = c.id
        WHERE DATE(o.order_date) = CURDATE() AND o.order_status NOT IN ('Pending', 'Cancelled')
        GROUP BY c.id ORDER BY total_shipments DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stuck_count = 0; $low_stock_count = 0; $out_stock_count = 0;
        $unsent_count = 0; $new_returns = 0; $gross_profit = 0;
        $recent_orders = []; $top_employees = []; $top_company = null;
        $kpis = ['total_orders'=>0,'today_orders'=>0,'yesterday_orders'=>0,'today_sales'=>0,'yesterday_sales'=>0,'month_sales'=>0,'last_month_sales'=>0,'pending_orders'=>0,'shipped_orders'=>0,'returned_orders'=>0,'completed_orders'=>0];
    }

    $cache_data = compact(
        'unassigned_count', 'stuck_count', 'low_stock_count', 'out_stock_count', 
        'unsent_count', 'new_returns', 'kpis', 'gross_profit', 
        'recent_orders', 'top_employees', 'top_company'
    );
    
    // Ensure cache dir exists
    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0777, true);
    }
    file_put_contents($cache_file, json_encode($cache_data));
}

$alerts = [];
if (($low_stock_count ?? 0) > 0) $alerts[] = ['type' => 'warning', 'icon' => 'fa-cube', 'title' => 'نقص في المخزون', 'desc' => "هناك {$low_stock_count} منتج قارب على النفاد."];
if (($unsent_count ?? 0) > 0) $alerts[] = ['type' => 'danger', 'icon' => 'fa-exchange', 'title' => 'فشل مزامنة الشحن', 'desc' => "يوجد {$unsent_count} طلب فشل إرساله."];
if (($unassigned_count ?? 0) > 0) $alerts[] = ['type' => 'warning', 'icon' => 'fa-user-times', 'title' => 'طلبات غير معينة', 'desc' => "هناك {$unassigned_count} طلب لم يتم تعيينها لموظف."];
if (($stuck_count ?? 0) > 0) $alerts[] = ['type' => 'danger', 'icon' => 'fa-clock-o', 'title' => 'طلبات متأخرة', 'desc' => "يوجد {$stuck_count} طلب في حالة 'معلق' لأكثر من 48 ساعة."];
if (($new_returns ?? 0) > 0) $alerts[] = ['type' => 'info', 'icon' => 'fa-undo', 'title' => 'مرتجعات جديدة', 'desc' => "تم تسجيل {$new_returns} مرتجع مؤخراً."];

if (!function_exists('get_percent_change')) {
    function get_percent_change($current, $previous) { 
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
$orders_change = get_percent_change($kpis['today_orders'] ?? 0, $kpis['yesterday_orders'] ?? 0);
$sales_change = get_percent_change($kpis['today_sales'] ?? 0, $kpis['yesterday_sales'] ?? 0);
$month_sales_change = get_percent_change($kpis['month_sales'] ?? 0, $kpis['last_month_sales'] ?? 0);

if (!function_exists('format_change_badge')) {
    function format_change_badge($change) { 
        if ($change > 0) return '<span class="d-badge up"><i class="fa fa-arrow-up"></i> +' . $change . '%</span>';
        if ($change < 0) return '<span class="d-badge down"><i class="fa fa-arrow-down"></i> ' . $change . '%</span>';
        return '<span class="d-badge neutral">0%</span>';
    }
}

$comp_orders = $kpis['completed_orders'] ?? 0;
$ret_orders = $kpis['returned_orders'] ?? 0;
$month_sales = $kpis['month_sales'] ?? 0;

$avg_order_value = $comp_orders > 0 ? ($month_sales / $comp_orders) : 0;
$success_rate = ($comp_orders + $ret_orders) > 0 ? round(($comp_orders / ($comp_orders + $ret_orders)) * 100, 1) : 0;

?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-main: #f3f4f6;
    --card-bg: #ffffff;
    --text-main: #111827;
    --text-muted: #6b7280;
    --border-color: #e5e7eb;
    --primary: #4f46e5;
    --primary-hover: #4338ca;
    --radius: 12px;
}
body { background-color: var(--bg-main); }
.dashboard-wrapper { font-family: 'Cairo', 'Inter', sans-serif; padding: 24px; max-width: 1600px; margin: 0 auto; direction: rtl; }
.d-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.d-header h1 { margin: 0; font-size: 26px; font-weight: 800; color: var(--text-main); }
.d-btn { background: #fff; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; color: var(--text-main); font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 14px; }
.d-btn:hover { background: #f9fafb; border-color: #d1d5db; }
.d-btn-primary { background: var(--primary); color: #fff; border: none; }
.d-btn-primary:hover { background: var(--primary-hover); color: #fff; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
.kpi-card { background: var(--card-bg); border-radius: var(--radius); padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; }
.kpi-header { display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 14px; font-weight: 600; margin-bottom: 12px; }
.kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.kpi-value { font-size: 28px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; line-height: 1; }
.kpi-footer { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }

.d-badge { padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; direction: ltr; }
.d-badge.up { background: #dcfce7; color: #166534; }
.d-badge.down { background: #fee2e2; color: #991b1b; }
.d-badge.neutral { background: #f3f4f6; color: #4b5563; }
.d-badge.soft { background: #f3f4f6; color: #6b7280; font-weight: 600; direction: rtl; }

.alerts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px; }
.alert-box { display: flex; gap: 16px; padding: 16px; border-radius: var(--radius); align-items: flex-start; }
.alert-box.warning { background: #fffbeb; border: 1px solid #fef3c7; }
.alert-box.danger { background: #fef2f2; border: 1px solid #fee2e2; }
.alert-box.info { background: #eff6ff; border: 1px solid #dbeafe; }
.alert-icon-wrap { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.alert-box.warning .alert-icon-wrap { background: #fef3c7; color: #b45309; }
.alert-box.danger .alert-icon-wrap { background: #fee2e2; color: #b91c1c; }
.alert-box.info .alert-icon-wrap { background: #dbeafe; color: #1d4ed8; }
.alert-content h4 { margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: var(--text-main); }
.alert-content p { margin: 0; font-size: 13px; color: var(--text-muted); font-weight: 500; }

.charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
.panel { background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
.panel-header { padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.panel-title { font-size: 16px; font-weight: 700; color: var(--text-main); margin: 0; }
.panel-body { padding: 20px; }
.chart-select { border: 1px solid var(--border-color); border-radius: 6px; padding: 4px 8px; font-family: inherit; font-size: 13px; outline: none; }

.main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
.table-responsive { overflow-x: auto; }
.d-table { width: 100%; border-collapse: collapse; text-align: right; }
.d-table th { padding: 12px 20px; font-size: 13px; font-weight: 700; color: var(--text-muted); background: #f9fafb; border-bottom: 1px solid var(--border-color); }
.d-table td { padding: 16px 20px; font-size: 14px; font-weight: 600; color: var(--text-main); border-bottom: 1px solid var(--border-color); }
.d-table tr:last-child td { border-bottom: none; }

.status-tag { padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }

.d-list { margin: 0; padding: 0; list-style: none; }
.d-list li { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
.d-list li:last-child { border-bottom: none; }
.d-list-main strong { display: block; font-size: 14px; color: var(--text-main); margin-bottom: 4px; }
.d-list-main span { font-size: 13px; color: var(--text-muted); font-weight: 500; }
.d-list-side { text-align: left; }
.d-list-side strong { display: block; font-size: 14px; color: var(--text-main); }

/* Custom Modal */
#customizeModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.c-modal { background: #fff; width: 100%; max-width: 480px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.c-modal h3 { margin: 0 0 20px; font-size: 20px; font-weight: 800; }
.c-toggle { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
.c-toggle span { font-size: 14px; font-weight: 600; color: #374151; }

.switch { position: relative; display: inline-block; width: 40px; height: 22px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: var(--primary); }
input:checked + .slider:before { transform: translateX(18px); }

@media (max-width: 1024px) {
    .charts-grid, .main-grid { grid-template-columns: 1fr; }
}
</style>

<div class="dashboard-wrapper admin-dashboard">
    <div class="d-header">
        <h1>لوحة القيادة التنفيذية</h1>
        <div style="display:flex; gap:8px;">
            <a href="index.php?action=refresh_kpis" class="d-btn" title="أعد حساب الأرقام الآن بدل انتظار 5 دقائق"><i class="fa fa-refresh"></i> تحديث الآن</a>
            <button class="d-btn" onclick="document.getElementById('customizeModal').style.display='flex'"><i class="fa fa-sliders"></i> تخصيص الواجهة</button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <?php if(is_widget_visible('kpi_orders')): ?>
        <div class="kpi-card">
            <div class="kpi-header">طلبات اليوم <div class="kpi-icon" style="background:#f3f4f6; color:#4b5563;"><i class="fa fa-shopping-bag"></i></div></div>
            <div class="kpi-value"><?php echo number_format($kpis['today_orders'] ?? 0); ?></div>
            <div class="kpi-footer"><?php echo format_change_badge($orders_change); ?> <span style="color:var(--text-muted);">مقارنة بالأمس</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_sales')): ?>
        <div class="kpi-card">
            <div class="kpi-header">مبيعات اليوم <div class="kpi-icon" style="background:#ecfdf5; color:#047857;"><i class="fa fa-money"></i></div></div>
            <div class="kpi-value"><?php echo admin_format_amount($kpis['today_sales']); ?></div>
            <div class="kpi-footer"><?php echo format_change_badge($sales_change); ?> <span style="color:var(--text-muted);">مقارنة بالأمس</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_sales_month')): ?>
        <div class="kpi-card">
            <div class="kpi-header">مبيعات الشهر <div class="kpi-icon" style="background:#eff6ff; color:#1d4ed8;"><i class="fa fa-line-chart"></i></div></div>
            <div class="kpi-value"><?php echo admin_format_amount($kpis['month_sales']); ?></div>
            <div class="kpi-footer"><?php echo format_change_badge($month_sales_change); ?> <span style="color:var(--text-muted);">مقارنة بالشهر الماضي</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_profit')): ?>
        <div class="kpi-card">
            <div class="kpi-header">إجمالي الأرباح <div class="kpi-icon" style="background:#fef3c7; color:#b45309;"><i class="fa fa-diamond"></i></div></div>
            <div class="kpi-value"><?php echo admin_format_amount($gross_profit); ?></div>
            <div class="kpi-footer"><span class="d-badge soft">قريباً: الأرباح الصافية الحقيقية</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_pending')): ?>
        <div class="kpi-card">
            <div class="kpi-header">طلبات معلقة <div class="kpi-icon" style="background:#fff7ed; color:#c2410c;"><i class="fa fa-clock-o"></i></div></div>
            <div class="kpi-value"><?php echo number_format($kpis['pending_orders'] ?? 0); ?></div>
            <div class="kpi-footer"><span class="d-badge soft">بانتظار المراجعة</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_shipped')): ?>
        <div class="kpi-card">
            <div class="kpi-header">طلبات قيد الشحن <div class="kpi-icon" style="background:#e0e7ff; color:#4338ca;"><i class="fa fa-truck"></i></div></div>
            <div class="kpi-value"><?php echo number_format($kpis['shipped_orders'] ?? 0); ?></div>
            <div class="kpi-footer"><span class="d-badge soft">مع شركات التوصيل</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_returned')): ?>
        <div class="kpi-card">
            <div class="kpi-header">طلبات مرتجعة <div class="kpi-icon" style="background:#fce7f3; color:#be185d;"><i class="fa fa-undo"></i></div></div>
            <div class="kpi-value"><?php echo number_format($kpis['returned_orders'] ?? 0); ?></div>
            <div class="kpi-footer"><span class="d-badge soft">إجمالي المرتجعات</span></div>
        </div>
        <?php endif; ?>

        <?php if(is_widget_visible('kpi_lowstock')): ?>
        <div class="kpi-card">
            <div class="kpi-header">قليلة المخزون <div class="kpi-icon" style="background:#fef2f2; color:#b91c1c;"><i class="fa fa-cube"></i></div></div>
            <div class="kpi-value"><?php echo number_format($low_stock_count ?? 0); ?></div>
            <div class="kpi-footer"><span class="d-badge soft">تحتاج لإعادة تزويد</span></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Alerts -->
    <?php if(!empty($alerts)): ?>
    <div class="alerts-grid">
        <?php foreach($alerts as $alert): ?>
        <div class="alert-box <?php echo $alert['type']; ?>">
            <div class="alert-icon-wrap"><i class="<?php echo $alert['icon']; ?>"></i></div>
            <div class="alert-content">
                <h4><?php echo $alert['title']; ?></h4>
                <p><?php echo $alert['desc']; ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Charts -->
    <?php if(is_widget_visible('charts')): ?>
    <div class="charts-grid">
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">تطور المبيعات</h3>
                <select class="chart-select" id="salesRange" onchange="loadCharts()">
                    <option value="today">اليوم</option>
                    <option value="7_days">آخر 7 أيام</option>
                    <option value="30_days" selected>آخر 30 يوماً</option>
                    <option value="12_months">آخر 12 شهراً</option>
                </select>
            </div>
            <div class="panel-body" style="height: 300px; display:flex; justify-content:center; align-items:center;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">الطلبات حسب الحالة</h3>
            </div>
            <div class="panel-body" style="height: 300px; display:flex; justify-content:center; align-items:center;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Grid -->
    <div class="main-grid">
        <!-- Left Column -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <?php if(is_widget_visible('recent_orders')): ?>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">آخر الطلبات</h3>
                    <a href="order.php" class="d-btn">عرض جميع الطلبات</a>
                </div>
                <div class="table-responsive">
                    <table class="d-table">
                        <thead>
                            <tr>
                                <th>الطلب</th>
                                <th>العميل</th>
                                <th>شركة التوصيل</th>
                                <th>الحالة</th>
                                <th>المبلغ</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_orders as $ro): 
                                $st = admin_dashboard_status_meta($ro['order_status']);
                            ?>
                            <tr>
                                <td><a href="order-details.php?id=<?php echo $ro['id']; ?>" style="color:var(--primary); font-weight:700;">#<?php echo $ro['id']; ?></a></td>
                                <td><?php echo htmlspecialchars($ro['customer_name']); ?></td>
                                <td><?php echo $ro['company_name'] ?: 'غير محدد'; ?></td>
                                <td>
                                    <span class="status-tag" style="background:<?php echo $st['bg']; ?>; color:<?php echo $st['color']; ?>;">
                                        <i class="<?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
                                    </span>
                                </td>
                                <td style="font-weight:800;"><?php echo admin_format_amount($ro['total_price']); ?></td>
                                <td style="color:var(--text-muted); font-size:13px;"><?php echo date('Y-m-d H:i', strtotime($ro['order_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($recent_orders)): ?>
                            <tr><td colspan="6" style="text-align:center;">لا توجد طلبات بعد</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <?php if(is_widget_visible('top_employees')): ?>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">أفضل الموظفين</h3>
                </div>
                <ul class="d-list">
                    <?php foreach($top_employees as $te): 
                        $success = $te['total_assigned'] > 0 ? round(($te['completed'] / $te['total_assigned'])*100) : 0;
                    ?>
                    <li>
                        <div class="d-list-main">
                            <strong><?php echo htmlspecialchars($te['full_name']); ?></strong>
                            <span>مؤكدة: <?php echo $te['total_assigned']; ?> | نجاح: <?php echo $success; ?>%</span>
                            <span style="display:block; margin-top:2px; font-size:11px; color:#9ca3af;">قريباً: متوسط زمن المعالجة</span>
                        </div>
                        <div class="d-list-side">
                            <strong style="color:var(--primary);"><?php echo $te['score']; ?> pt</strong>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if(empty($top_employees)): ?>
                    <li style="justify-content:center; color:var(--text-muted);">لا توجد بيانات للموظفين</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if(is_widget_visible('top_companies')): ?>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">أداء التوصيل (اليوم)</h3>
                </div>
                <div class="panel-body">
                    <?php if(!empty($top_company)): ?>
                    <div style="text-align:center; margin-bottom:16px;">
                        <h4 style="margin:0; font-size:20px; font-weight:800;"><?php echo htmlspecialchars($top_company['name'] ?: 'بدون شركة'); ?></h4>
                        <span style="color:var(--text-muted); font-size:13px; font-weight:600;">الشركة الأكثر نشاطاً اليوم</span>
                    </div>
                    <div style="display:flex; gap:12px; text-align:center;">
                        <div style="flex:1; background:var(--bg-main); padding:10px; border-radius:8px;">
                            <strong style="display:block; font-size:18px; color:var(--text-main);"><?php echo $top_company['total_shipments']; ?></strong>
                            <span style="font-size:12px; color:var(--text-muted);">شحنة</span>
                        </div>
                        <div style="flex:1; background:#ecfdf5; padding:10px; border-radius:8px;">
                            <strong style="display:block; font-size:18px; color:#047857;"><?php echo $top_company['successful']; ?></strong>
                            <span style="font-size:12px; color:#065f46;">ناجحة</span>
                        </div>
                        <div style="flex:1; background:#fef2f2; padding:10px; border-radius:8px;">
                            <strong style="display:block; font-size:18px; color:#b91c1c;"><?php echo $top_company['returned']; ?></strong>
                            <span style="font-size:12px; color:#991b1b;">مرتجعة</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center; color:var(--text-muted);">لا يوجد نشاط توصيل اليوم</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(is_widget_visible('inventory_summary')): ?>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">حالة المخزون</h3>
                    <a href="product.php" class="d-btn">إدارة المخزون</a>
                </div>
                <div class="panel-body" style="display:flex; gap:16px;">
                    <div style="flex:1; border:1px solid #fef3c7; background:#fffbeb; padding:16px; border-radius:12px; text-align:center;">
                        <strong style="display:block; font-size:24px; color:#b45309;"><?php echo $low_stock_count; ?></strong>
                        <span style="font-size:13px; font-weight:700; color:#92400e;">قليل المخزون</span>
                    </div>
                    <div style="flex:1; border:1px solid #fee2e2; background:#fef2f2; padding:16px; border-radius:12px; text-align:center;">
                        <strong style="display:block; font-size:24px; color:#b91c1c;"><?php echo $out_stock_count; ?></strong>
                        <span style="font-size:13px; font-weight:700; color:#991b1b;">منتهي تماماً</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if(is_widget_visible('performance')): ?>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">مؤشرات الأداء العامة</h3>
                </div>
                <ul class="d-list">
                    <li>
                        <div class="d-list-main"><strong>إجمالي طلبات اليوم</strong></div>
                        <div class="d-list-side"><strong><?php echo number_format($kpis['today_orders'] ?? 0); ?></strong></div>
                    </li>
                    <li>
                        <div class="d-list-main"><strong>نسبة نجاح التوصيل</strong><span>(المكتملة / (المكتملة + المرتجعة))</span></div>
                        <div class="d-list-side"><strong style="color:#047857;"><?php echo $success_rate; ?>%</strong></div>
                    </li>
                    <li>
                        <div class="d-list-main"><strong>متوسط قيمة الطلب</strong><span>لطلبات هذا الشهر المكتملة</span></div>
                        <div class="d-list-side"><strong><?php echo admin_format_amount($avg_order_value); ?></strong></div>
                    </li>
                    <li>
                        <div class="d-list-main"><strong>متوسط زمن المعالجة</strong><span>يعتمد على Order Timeline</span></div>
                        <div class="d-list-side"><span class="d-badge soft">جاري التجميع</span></div>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="customizeModal">
    <div class="c-modal">
        <h3>تخصيص الواجهة</h3>
        <form id="prefsForm">
            <?php
            $widgets = [
                'kpi_orders' => 'طلبات اليوم',
                'kpi_sales' => 'مبيعات اليوم',
                'kpi_sales_month' => 'مبيعات الشهر',
                'kpi_profit' => 'إجمالي الأرباح',
                'kpi_pending' => 'الطلبات المعلقة',
                'kpi_shipped' => 'الطلبات قيد الشحن',
                'kpi_returned' => 'الطلبات المرتجعة',
                'kpi_lowstock' => 'المنتجات قليلة المخزون',
                'charts' => 'الرسوم البيانية (المبيعات والحالة)',
                'recent_orders' => 'آخر الطلبات (10 طلبات)',
                'top_employees' => 'أفضل 5 موظفين',
                'top_companies' => 'أداء شركات التوصيل',
                'inventory_summary' => 'حالة المخزون السريعة',
                'performance' => 'مؤشرات الأداء العامة'
            ];
            foreach($widgets as $key => $label):
                $checked = is_widget_visible($key) ? 'checked' : '';
            ?>
            <div class="c-toggle">
                <span><?php echo $label; ?></span>
                <label class="switch">
                    <input type="checkbox" name="<?php echo $key; ?>" <?php echo $checked; ?>>
                    <span class="slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:24px; display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="d-btn" onclick="document.getElementById('customizeModal').style.display='none'">إلغاء</button>
                <button type="button" class="d-btn d-btn-primary" onclick="saveCustomize()">حفظ الإعدادات</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let salesChart = null;
let statusChart = null;

function loadCharts() {
    if (!document.getElementById('salesChart')) return;
    
    const range = document.getElementById('salesRange').value;
    
    // Show loading
    document.getElementById('salesChart').style.opacity = '0.5';
    document.getElementById('statusChart').style.opacity = '0.5';

    fetch('ajax-dashboard.php?action=charts&range=' + range)
        .then(res => res.json())
        .then(data => {
            document.getElementById('salesChart').style.opacity = '1';
            document.getElementById('statusChart').style.opacity = '1';
            
            // Sales Chart
            const ctx1 = document.getElementById('salesChart').getContext('2d');
            if(salesChart) salesChart.destroy();
            salesChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: data.sales.map(i => i.label),
                    datasets: [{
                        label: 'الإيرادات',
                        data: data.sales.map(i => i.revenue),
                        backgroundColor: '#4f46e5',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });

            // Status Chart
            const ctx2 = document.getElementById('statusChart').getContext('2d');
            if(statusChart) statusChart.destroy();
            const colors = {
                'Completed': '#166534', 'Delivered': '#166534',
                'Pending': '#d97706',
                'Confirmed': '#2563eb',
                'Shipped': '#4f46e5', 'In Transit': '#4f46e5',
                'Returned': '#be185d',
                'Cancelled': '#b91c1c'
            };
            statusChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: data.status.map(i => i.order_status),
                    datasets: [{
                        data: data.status.map(i => i.count),
                        backgroundColor: data.status.map(i => colors[i.order_status] || '#9ca3af'),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { position: 'right', rtl: true } }
                }
            });
        });
}

document.addEventListener("DOMContentLoaded", function() {
    loadCharts();
});

function saveCustomize() {
    const form = document.getElementById('prefsForm');
    const prefsObj = {};
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        prefsObj[cb.name] = cb.checked;
    });

    const fd = new FormData();
    fd.append('prefs', JSON.stringify(prefsObj));

    fetch('ajax-dashboard.php?action=save_prefs', {
        method: 'POST',
        body: fd
    }).then(() => {
        window.location.reload();
    });
}
</script>


<script>
// Dashboard Real-time Polling for Critical Notifications
$(document).ready(function() {
    function fetchNotifications() {
        $.ajax({
            url: 'ajax-notifications.php',
            type: 'POST',
            dataType: 'json',
            success: function(data) {
                if (data.status === 'success') {
                    // Update header bell if element exists
                    if ($('.notification-badge').length) {
                        $('.notification-badge').text(data.count > 0 ? data.count : '');
                    }
                    
                    if (data.count > 0) {
                        // Play sound if enabled
                        if (data.sound == 1) {
                            var audio = new Audio('../assets/sounds/notification.mp3');
                            audio.play().catch(function(){}); // Catch browser autoplay policies
                        }
                        
                        // Show popup
                        data.notifications.forEach(function(notif) {
                            if (typeof toastr !== 'undefined') {
                                toastr[notif.type || 'info'](notif.message, notif.title);
                            } else {
                                alert(notif.title + '\n' + notif.message);
                            }
                        });
                        
                        // Mark as read so we don't spam
                        $.post('ajax-notifications.php', {action: 'mark_read'});
                    }
                    
                    // Setup next poll based on user settings
                    var interval = (data.interval && data.interval >= 10) ? data.interval * 1000 : 30000;
                    setTimeout(fetchNotifications, interval);
                }
            },
            error: function() {
                setTimeout(fetchNotifications, 60000); // Retry after 1 min on error
            }
        });
    }
    
    // Start polling ONLY on dashboard
    setTimeout(fetchNotifications, 5000); // First poll after 5s
});
</script>

<?php require_once('footer.php'); ?>
