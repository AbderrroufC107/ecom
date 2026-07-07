<?php
ob_start();
session_start();

require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/audit.php');

// Require authentication
if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
    header('location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Audit log the deletion
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
audit_log_order($pdo, $order_id, 'order_deleted', null, null, 'admin_panel', $_SESSION['user']['id'] ?? $_SESSION['store_user']['id'] ?? 0);
add_admin_notification($pdo, 'حذف طلب', 'تم حذف الطلب رقم #'.$order_id.' من قبل مستخدم.', 'danger');

if ($order_id <= 0) {
    admin_set_flash_message('orders', 'danger', 'تعذر حذف الطلب لأن المعرّف غير صالح.');
    header('Location: order.php');
    exit;
}

try {
    admin_ensure_order_call_log_table($pdo);
    admin_ensure_order_status_log_table($pdo);

    $statement = $dbRepo->prepare('SELECT id, customer_name, manager_id FROM tbl_order WHERE id = ? LIMIT 1');
    $statement->execute([$order_id]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('الطلب المطلوب غير موجود.');
    }

    if (!empty($order['manager_id']) && isset($_SESSION['user'])) {
        $current_manager_id = (int) ($_SESSION['user']['id'] ?? 0);
        $is_super_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin');
        if (!$is_super_admin && (int) $order['manager_id'] !== $current_manager_id) {
            throw new Exception('غير مصرح لك بحذف طلب تابع لمدير آخر.');
        }
    }

    $pdo->beginTransaction();

    $statement = $dbRepo->prepare('DELETE FROM tbl_order_call_log WHERE order_id = ?');
    $statement->execute([$order_id]);

    $statement = $dbRepo->prepare('DELETE FROM tbl_order_status_log WHERE order_id = ?');
    $statement->execute([$order_id]);

    $statement = $dbRepo->prepare('DELETE FROM tbl_order WHERE id = ?');
    $statement->execute([$order_id]);

    $pdo->commit();

    admin_set_flash_message('orders', 'success', 'تم حذف الطلب #' . $order_id . ' بنجاح.');
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    admin_set_flash_message('orders', 'danger', $exception->getMessage());
}

header('Location: order.php');
exit;
