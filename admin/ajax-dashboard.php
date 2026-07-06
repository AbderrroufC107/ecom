<?php
require_once('inc/config.php');
if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$action = $_GET['action'] ?? '';
$range = $_GET['range'] ?? '30_days'; // today, 7_days, 30_days, 12_months

if ($action === 'charts') {
    $where_date = "";
    $group_by = "";
    $date_format = "";
    
    if ($range === 'today') {
        $where_date = "DATE(order_date) = CURDATE()";
        $group_by = "HOUR(order_date)";
        $date_format = "%H:00";
    } elseif ($range === '7_days') {
        $where_date = "order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
        $group_by = "DATE(order_date)";
        $date_format = "%Y-%m-%d";
    } elseif ($range === '30_days') {
        $where_date = "order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
        $group_by = "DATE(order_date)";
        $date_format = "%Y-%m-%d";
    } elseif ($range === '12_months') {
        $where_date = "order_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)";
        $group_by = "DATE_FORMAT(order_date, '%Y-%m')";
        $date_format = "%Y-%m";
    }

    // 1. Sales Chart Data
    // Assume Completed orders for sales chart? Or all valid orders? Usually Completed/Delivered.
    // Let's use Completed + Confirmed maybe? Let's use Completed for actual revenue.
    $sales_query = "
        SELECT DATE_FORMAT(order_date, '{$date_format}') as label, 
               SUM(total_price) as revenue 
        FROM tbl_order 
        WHERE {$where_date} AND order_status = 'Completed'
        GROUP BY label 
        ORDER BY order_date ASC
    ";
    
    // In case there's no data or missing dates, it's better to return the raw data and let JS handle it
    $sales_stmt = $dbRepo->query($sales_query);
    $sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Orders by Status
    $status_query = "
        SELECT order_status, COUNT(*) as count 
        FROM tbl_order 
        WHERE {$where_date}
        GROUP BY order_status
    ";
    $status_stmt = $dbRepo->query($status_query);
    $status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sales' => $sales_data,
        'status' => $status_data
    ]);
    exit;
}

if ($action === 'save_prefs') {
    $prefs = $_POST['prefs'] ?? '';
    if (!empty($prefs)) {
        $user_id = $_SESSION['user']['id'];
        $stmt = $dbRepo->prepare("UPDATE tbl_user SET dashboard_prefs = ? WHERE id = ?");
        $stmt->execute([$prefs, $user_id]);
        echo json_encode(['success' => true]);
    }
    exit;
}
