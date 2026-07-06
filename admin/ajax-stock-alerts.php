<?php
session_start();
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/stock_functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'Super Admin') {
    echo json_encode(['success' => false]);
    exit;
}

$action = $_GET['action'] ?? '';
$admin_id = (int)$_SESSION['user']['id'];

if ($action === 'check') {
    $alerts = stock_get_alerts($pdo, $admin_id);
    echo json_encode(['success' => true, 'alerts' => $alerts]);
    exit;
}

if ($action === 'dismiss') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $variant_id = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int)$_POST['variant_id'] : null;
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;

    if ($product_id > 0) {
        stock_dismiss_alert($pdo, $admin_id, $product_id, $variant_id, $qty);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
