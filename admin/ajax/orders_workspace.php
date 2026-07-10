<?php
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/functions.php');
require_once(__DIR__ . '/../inc/CSRF_Protect.php');
$csrf = new CSRF_Protect();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$is_admin = ($_SESSION['user']['role'] === 'Admin' || $_SESSION['user']['role'] === 'Super Admin' || $_SESSION['user']['role'] === 'Manager');
$current_user_id = (int)($_SESSION['user']['id'] ?? 0);
$is_super_admin = ($_SESSION['user']['role'] === 'Super Admin');
$my_employee_id = 0;
if (!$is_admin && $_SESSION['user']['role'] === 'Employee') {
    require_once('../inc/employee_functions.php');
    $emp = employee_get_current_admin_employee($pdo);
    if ($emp) {
        $my_employee_id = (int)$emp['id'];
    }
}

// DataTables parameters
$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 50;
$search_value = isset($_POST['search']['value']) ? trim((string)$_POST['search']['value']) : '';

// Custom filters
$tab = isset($_POST['tab']) ? trim((string)$_POST['tab']) : 'all';
$employee_filter = isset($_POST['employee']) ? trim((string)$_POST['employee']) : '';

// Base query
$select_sql = "
    SELECT o.id, o.order_date, o.customer_name, o.customer_phone, 
           o.product_name, o.quantity, o.total_price AS amount, o.order_status, 
           o.delivery_type, o.delivery_company_id, o.manager_id,
           e.full_name AS employee_name, e.id AS employee_id,
           COALESCE(cs.call_count, 0) AS call_count, 
           cl.call_status AS last_call_status
    FROM tbl_order o
    LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id AND oa.status = 'active'
    LEFT JOIN tbl_employee e ON e.id = oa.employee_id
    LEFT JOIN (
        SELECT order_id, COUNT(*) AS call_count, MAX(id) AS last_call_id 
        FROM tbl_order_call_log GROUP BY order_id
    ) cs ON cs.order_id = o.id
    LEFT JOIN tbl_order_call_log cl ON cl.id = cs.last_call_id
";

$where_conditions = [];
$params = [];

// Apply Employee Filter
if (!$is_admin) {
    $where_conditions[] = "oa.employee_id = ?";
    $params[] = $my_employee_id;
} else {
    // A regular Manager/Admin (not Super Admin) must only ever see their own
    // team's orders - orders explicitly stamped with another manager's id.
    // This used to be cosmetic only (dimmed via row-other-manager in the
    // client), so a manager could still see every other manager's customer
    // names, phone numbers and order details in the raw list.
    if (!$is_super_admin) {
        $where_conditions[] = "(o.manager_id = ? OR o.manager_id IS NULL OR o.manager_id = 0)";
        $params[] = $current_user_id;
    }

    if ($employee_filter === 'unassigned') {
        $where_conditions[] = "oa.employee_id IS NULL";
    } elseif ($employee_filter === 'my') {
        require_once('../inc/employee_functions.php');
        $my_emp = employee_get_current_admin_employee($pdo);
        if ($my_emp) {
            $where_conditions[] = "oa.employee_id = ?";
            $params[] = (int)$my_emp['id'];
        }
    } elseif ($employee_filter !== '' && ctype_digit($employee_filter)) {
        $where_conditions[] = "oa.employee_id = ?";
        $params[] = (int)$employee_filter;
    }
}

// Apply Tab Filters
switch ($tab) {
    case 'new':
        $where_conditions[] = "o.order_status = 'Pending' AND COALESCE(cs.call_count, 0) = 0";
        break;
    case 'needs_conf':
        $where_conditions[] = "o.order_status = 'Pending' AND COALESCE(cs.call_count, 0) > 0 AND cl.call_status != 'answered'";
        break;
    case 'follow_up':
        // Custom logic: e.g. Pending + call log exists
        $where_conditions[] = "o.order_status = 'Pending' AND COALESCE(cs.call_count, 0) > 0";
        break;
    case 'waiting_dispatch':
        $where_conditions[] = "o.order_status = 'Completed' AND (o.ecotrack_status IS NULL OR o.ecotrack_status = '') AND (o.zrexpress_status IS NULL OR o.zrexpress_status = '')";
        break;
    case 'dispatched':
        $where_conditions[] = "(o.ecotrack_status != '' OR o.zrexpress_status != '')";
        break;
    case 'issues':
        $where_conditions[] = "o.order_status IN ('Failed', 'Error')";
        break;
    case 'returns':
        $where_conditions[] = "o.order_status = 'Returned'";
        break;
    case 'completed':
        $where_conditions[] = "o.order_status IN ('Completed', 'Delivered')";
        break;
    case 'all':
    default:
        $where_conditions[] = "o.order_status NOT IN ('Cancelled', 'Delivered', 'Completed')";
        break;
}

// Apply Search
if ($search_value !== '') {
    $search_cond = "(
        o.id LIKE ? OR 
        o.customer_name LIKE ? OR 
        o.customer_phone LIKE ? OR 
        o.product_name LIKE ? OR 
        e.full_name LIKE ?
    )";
    $search_term = "%{$search_value}%";
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
    $where_conditions[] = $search_cond;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = " WHERE " . implode(' AND ', $where_conditions);
}

// Total records (before filtering)
$total_stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_order");
$recordsTotal = $total_stmt->fetchColumn();

// Total records (after filtering)
$filter_stmt = $dbRepo->prepare("
    SELECT COUNT(DISTINCT o.id) 
    FROM tbl_order o
    LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id AND oa.status = 'active'
    LEFT JOIN tbl_employee e ON e.id = oa.employee_id
    LEFT JOIN (
        SELECT order_id, COUNT(*) AS call_count, MAX(id) AS last_call_id 
        FROM tbl_order_call_log GROUP BY order_id
    ) cs ON cs.order_id = o.id
    LEFT JOIN tbl_order_call_log cl ON cl.id = cs.last_call_id
    $where_sql
");
$filter_stmt->execute($params);
$recordsFiltered = $filter_stmt->fetchColumn();

// Order By
$order_col_idx = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';

// Map DataTable column index to DB column
$columns_map = [
    0 => 'o.id',
    1 => 'o.customer_name',
    2 => 'o.product_name',
    3 => 'e.full_name',
    4 => 'o.delivery_type',
    5 => 'o.order_status',
    6 => 'o.id' // Actions
];
$order_by_col = isset($columns_map[$order_col_idx]) ? $columns_map[$order_col_idx] : 'o.id';

$sql = $select_sql . $where_sql . " ORDER BY $order_by_col $order_dir LIMIT " . (int)$start . ", " . (int)$length;
$stmt = $dbRepo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($orders as $o) {
    // Check if order belongs to another manager
    $order_manager_id = !empty($o['manager_id']) ? (int)$o['manager_id'] : 0;
    $is_other_manager = ($order_manager_id > 0 && $order_manager_id !== $current_user_id && !$is_super_admin);
    $row_class = $is_other_manager ? 'row-other-manager' : '';

    // 1. Order ID + Date
    $date = date('d/m/Y', strtotime($o['order_date']));
    $col_id = "<div><span class='text-bold'>#{$o['id']}</span><br><small class='text-muted' style='white-space:nowrap;'>{$date}</small></div>";

    // 2. Customer
    $col_customer = "<div><span class='text-bold'>".htmlspecialchars($o['customer_name'])."</span><br><small dir='ltr' class='text-muted' style='white-space:nowrap;'>".htmlspecialchars($o['customer_phone'])."</small></div>";

    // 3. Product
    $prod_name = htmlspecialchars($o['product_name']);
    $qty = (int)$o['quantity'];
    $amount = number_format((float)$o['amount'], 2);
    $col_product = "<div class='product-cell' title='{$prod_name}' style='max-width:200px; white-space:normal; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;'>
                        <div class='product-name' style='font-size:13px; font-weight:600;'>{$prod_name}</div>
                        <div class='product-meta' style='margin-top:2px;'><span class='badge bg-gray' style='font-size:11px;'>x{$qty}</span> <span class='text-success' style='font-weight:bold;'>{$amount}</span></div>
                    </div>";

    // 4. Employee
    $emp_name = $o['employee_name'] ? htmlspecialchars($o['employee_name']) : 'غير موزع';
    $emp_class = $o['employee_name'] ? 'bg-blue' : 'bg-gray';
    $col_employee = "<span class='badge {$emp_class}' style='white-space:nowrap;'>{$emp_name}</span>";

    // 5. Delivery Company
    $del_type = htmlspecialchars($o['delivery_type'] ?: 'غير محدد');
    $col_delivery = "<span class='badge bg-purple delivery-badge' style='white-space:nowrap;'><i class='fa fa-truck'></i> {$del_type}</span>";

    // 6. Status
    $status = $o['order_status'];
    $st_class = 'bg-yellow';
    if ($status === 'Completed') $st_class = 'bg-green';
    if ($status === 'Returned' || $status === 'Cancelled' || $status === 'Failed') $st_class = 'bg-red';
    $status_ar = $status;
    if ($status === 'Pending') $status_ar = 'قيد المراجعة';
    if ($status === 'Completed') $status_ar = 'مؤكد';
    if ($status === 'Returned') $status_ar = 'مرتجع';
    if ($status === 'Cancelled') $status_ar = 'ملغي';

    $col_status = "<span class='badge {$st_class}' style='white-space:nowrap;'>{$status_ar}</span>";

    // 7. Actions (Buttons)
    $order_id = (int) $o['id'];
    $details_title = htmlspecialchars("تعديل وتفاصيل الطلب #{$order_id}", ENT_QUOTES, 'UTF-8');
    $delete_confirm = htmlspecialchars('هل أنت متأكد من حذف هذا الطلب؟', ENT_QUOTES, 'UTF-8');
    $actions = "<div class='workspace-actions'>";
    $actions .= "<button type='button' class='btn btn-xs btn-primary js-order-action' data-action='view' data-id='{$order_id}'><i class='fa fa-eye'></i> عرض</button>";
    if (!$is_other_manager) {
        $actions .= "<button type='button' class='btn btn-xs btn-default js-order-action' data-action='manage' data-id='{$order_id}' data-title='{$details_title}' data-url='order-details.php?id={$order_id}'><i class='fa fa-pencil'></i> إدارة الطلب</button>";
        $actions .= "<button type='button' class='btn btn-xs btn-danger js-order-action' data-action='delete' data-id='{$order_id}' data-confirm='{$delete_confirm}'><i class='fa fa-trash'></i> حذف</button>";
    } else {
        $actions .= "<span class='label label-default' style='font-size:10px;'><i class='fa fa-lock'></i> طلب مدير آخر</span>";
    }
    $actions .= "</div>";

    $row_attrs = [];
    $row_attrs['DT_RowId'] = 'order_row_' . $o['id'];
    if ($row_class) {
        $row_attrs['DT_RowClass'] = $row_class;
    }

    $data[] = array_merge([
        $col_id,
        $col_customer,
        $col_product,
        $col_employee,
        $col_delivery,
        $col_status,
        $actions,
    ], $row_attrs);
}

$response = [
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data
];

echo json_encode($response);
exit;
