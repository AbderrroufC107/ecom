<?php
session_start();
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/stock_functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'Super Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Only managers can update stock.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$variant_id = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int)$_POST['variant_id'] : null;
$new_qty = isset($_POST['qty']) ? (int)$_POST['qty'] : -1;
$admin_id = (int)$_SESSION['user']['id'];

if ($product_id <= 0 || $new_qty < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
    exit;
}

try {
    stock_update_quantity($pdo, $product_id, $variant_id, $new_qty, 'Manual Update', 'admin', $admin_id, $admin_id, 'Updated via quick edit');
    
    // Recalculate available stock to return
    $reserved = stock_get_reserved($pdo, $product_id, $variant_id);
    $available = $new_qty - $reserved;

    echo json_encode([
        'success' => true, 
        'message' => 'Stock updated successfully.',
        'new_qty' => $new_qty,
        'reserved' => $reserved,
        'available' => $available
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
