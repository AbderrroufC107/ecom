<?php
session_start();
require_once('inc/config.php');

if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$is_admin = isset($_SESSION['user']);
$my_type = $is_admin ? 'admin_panel' : 'staff_portal';
$my_id = $is_admin ? $_SESSION['user']['id'] : $_SESSION['store_user']['id'];

// DataTables parameters
$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Filters
$f_user = $_POST['user'] ?? '';
$f_action = $_POST['action'] ?? '';
$f_risk = $_POST['risk'] ?? '';
$f_result = $_POST['result'] ?? '';
$f_date_from = $_POST['date_from'] ?? '';
$f_date_to = $_POST['date_to'] ?? '';

// Build Query
$where = ["1=1"];
$params = [];

if (!$is_admin) {
    // Employee can only see their own logs
    $where[] = "al.performed_by_type = 'staff_portal' AND al.performed_by_id = ?";
    $params[] = $my_id;
} else if ($f_user !== '') {
    list($ptype, $pid) = explode('_', $f_user, 2);
    if (strpos($f_user, 'admin_panel_') === 0) {
        $ptype = 'admin_panel';
        $pid = str_replace('admin_panel_', '', $f_user);
    } else {
        $ptype = 'staff_portal';
        $pid = str_replace('staff_portal_', '', $f_user);
    }
    $where[] = "al.performed_by_type = ? AND al.performed_by_id = ?";
    $params[] = $ptype;
    $params[] = $pid;
}

if ($f_action !== '') {
    $where[] = "al.action_type = ?";
    $params[] = $f_action;
}
if ($f_risk !== '') {
    $where[] = "al.risk_level = ?";
    $params[] = $f_risk;
}
if ($f_result !== '') {
    $where[] = "al.result = ?";
    $params[] = $f_result;
}
if ($f_date_from !== '') {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $f_date_from;
}
if ($f_date_to !== '') {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $f_date_to;
}
if ($search !== '') {
    $where[] = "(al.action_type LIKE ? OR al.audit_ref LIKE ? OR al.ip_address LIKE ? OR o.customer_name LIKE ? OR o.tracking_number LIKE ?)";
    $search_param = "%{$search}%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param);
}

$where_sql = implode(' AND ', $where);

$join_sql = "
    LEFT JOIN tbl_user u ON al.performed_by_type = 'admin_panel' AND al.performed_by_id = u.id
    LEFT JOIN tbl_employee e ON al.performed_by_type = 'staff_portal' AND al.performed_by_id = e.id
    LEFT JOIN tbl_order o ON al.entity_type = 'order' AND al.entity_id = o.id
";

// Order
$columns = ['al.id', 'al.audit_ref', 'al.created_at', 'al.performed_by_id', 'al.action_type', 'al.entity_type', 'al.risk_level', 'al.result', 'al.ip_address'];
$order_col = isset($_POST['order'][0]['column']) ? $columns[$_POST['order'][0]['column']] : 'al.id';
$order_dir = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';

// Total records
$stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_audit_log");
$total_records = $stmt->fetchColumn();

// Filtered records
$stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_audit_log al $join_sql WHERE $where_sql");
$stmt->execute($params);
$total_filtered = $stmt->fetchColumn();

// Fetch Data
$sql = "
    SELECT al.*, 
           COALESCE(u.full_name, e.name, 'System') as user_name
    FROM tbl_audit_log al
    $join_sql
    WHERE $where_sql
    ORDER BY $order_col $order_dir
    LIMIT $start, $length
";
$stmt = $dbRepo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format Output
$output_data = [];
foreach ($data as $row) {
    
    // Risk Badge
    $risk_badge = '<span class="label label-info">INFO</span>';
    if ($row['risk_level'] === 'WARNING') $risk_badge = '<span class="label label-warning">WARNING</span>';
    if ($row['risk_level'] === 'CRITICAL') $risk_badge = '<span class="label label-danger">CRITICAL</span>';

    // Result Badge
    $res_badge = $row['result'] === 'SUCCESS' ? '<span class="label label-success"><i class="fa fa-check"></i> نجاح</span>' : '<span class="label label-danger"><i class="fa fa-times"></i> فشل</span>';
    
    // Entity
    $entity = "{$row['entity_type']} #{$row['entity_id']}";
    if ($row['entity_type'] === 'order') {
        $entity = "<a href='order-details.php?id={$row['entity_id']}' target='_blank'>طلب #{$row['entity_id']} <i class='fa fa-external-link'></i></a>";
    } elseif ($row['entity_type'] === 'employee') {
        $entity = "<a href='employee-edit.php?id={$row['entity_id']}' target='_blank'>موظف #{$row['entity_id']} <i class='fa fa-external-link'></i></a>";
    } elseif ($row['entity_type'] === 'product') {
        $entity = "<a href='product-edit.php?id={$row['entity_id']}' target='_blank'>منتج #{$row['entity_id']} <i class='fa fa-external-link'></i></a>";
    }

    $safe_old = htmlspecialchars($row['old_value'] ?? '{}', ENT_QUOTES, 'UTF-8');
    $safe_new = htmlspecialchars($row['new_value'] ?? '{}', ENT_QUOTES, 'UTF-8');
    
    $btn = "<button class='btn btn-xs btn-primary view-details' data-old='{$safe_old}' data-new='{$safe_new}'><i class='fa fa-eye'></i> التفاصيل</button>";

    $output_data[] = [
        "id" => $row['id'],
        "audit_ref" => "<code>" . htmlspecialchars($row['audit_ref']) . "</code>",
        "created_at" => $row['created_at'],
        "user_name" => htmlspecialchars($row['user_name'] ?? 'Unknown') . " <br><small>({$row['performed_by_type']})</small>",
        "action_type" => "<strong>" . htmlspecialchars($row['action_type']) . "</strong>",
        "entity" => $entity,
        "risk_level" => $risk_badge,
        "result" => $res_badge,
        "network" => "IP: {$row['ip_address']}<br><small class='text-muted'>" . htmlspecialchars(substr($row['user_agent'], 0, 30)) . "...</small>",
        "actions" => $btn
    ];
}


echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_filtered,
    "data" => $output_data
]);
