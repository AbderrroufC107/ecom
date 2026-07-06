<?php
require_once('header.php');
require_once('inc/stock_functions.php');

if(!isset($_GET['id'])) {
    header('location: purchase-orders.php');
    exit;
}

$po_id = (int)$_GET['id'];

// Check if PO is pending
$stmt = $dbRepo->prepare("SELECT * FROM tbl_purchase_order WHERE id = ? AND status = 'Pending'");
$stmt->execute([$po_id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$po) {
    header('location: purchase-orders.php');
    exit;
}

// Fetch PO items
$stmt = $dbRepo->prepare("SELECT * FROM tbl_purchase_order_item WHERE po_id = ?");
$stmt->execute([$po_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admin_id = $_SESSION['user']['id'] ?? 0;

$pdo->beginTransaction();
try {
    // Mark PO as Received
    $stmtUpdate = $dbRepo->prepare("UPDATE tbl_purchase_order SET status = 'Received' WHERE id = ?");
    $stmtUpdate->execute([$po_id]);

    // Increase Stock for each item
    foreach($items as $item) {
        $pid = $item['product_id'];
        $vid = $item['variant_id'];
        $qty_added = $item['qty'];
        
        // Get current qty
        $current_qty = _stock_get_current_qty($pdo, $pid, $vid);
        $new_qty = $current_qty + $qty_added;

        // Update stock and log
        stock_update_quantity($pdo, $pid, $vid, $new_qty, 'Supplier Delivery', 'purchase_order', $po_id, $admin_id, "PO #{$po_id} received.");
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'تم استلام بضاعة طلب الشراء وتحديث المخزون بنجاح.';
} catch(Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'حدث خطأ أثناء الاستلام: ' . $e->getMessage();
}

header('location: purchase-orders.php');
exit;
?>
