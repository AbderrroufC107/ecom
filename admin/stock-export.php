<?php
require_once('inc/config.php');
require_once('inc/functions.php');

// Security check (only admin/manager)
if(!isset($_SESSION['user'])) {
    header('location: login.php');
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stock_export_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Product ID', 'Variant ID', 'Product Name', 'Variant Name', 'SKU', 'Barcode', 'Quantity', 'Reserved', 'Available']);

$stmt = $dbRepo->prepare("SELECT p.p_id, p.p_name, p.p_qty, p.p_sku, p.p_barcode FROM tbl_product p ORDER BY p.p_id DESC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($products as $p) {
    // Check for variants
    $v_stmt = $dbRepo->prepare("
        SELECT v.variant_id, v.qty, v.sku, v.barcode, s.size_name, c.color_name 
        FROM tbl_product_variant v
        LEFT JOIN tbl_size s ON v.size_id = s.size_id
        LEFT JOIN tbl_color c ON v.color_id = c.color_id
        WHERE v.p_id = ?
    ");
    $v_stmt->execute([$p['p_id']]);
    $variants = $v_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($variants)) {
        // Parent only
        $resStmt = $dbRepo->prepare("SELECT SUM(quantity) FROM tbl_order WHERE product_id = ? AND order_status IN ('Pending', 'Confirmed', 'Processing')");
        $resStmt->execute([$p['p_id']]);
        $reserved = (int)$resStmt->fetchColumn();
        $available = max(0, $p['p_qty'] - $reserved);

        fputcsv($output, [$p['p_id'], '', $p['p_name'], '', $p['p_sku'], $p['p_barcode'], $p['p_qty'], $reserved, $available]);
    } else {
        foreach($variants as $v) {
            $labels = [];
            if (!empty($v['size_name'])) $labels[] = $v['size_name'];
            if (!empty($v['color_name'])) $labels[] = $v['color_name'];
            $variant_name = implode(' / ', $labels);

            $resStmt = $dbRepo->prepare("SELECT SUM(quantity) FROM tbl_order WHERE product_id = ? AND order_size = ? AND order_status IN ('Pending', 'Confirmed', 'Processing')");
            $resStmt->execute([$p['p_id'], $variant_name]);
            $reserved = (int)$resStmt->fetchColumn();
            $available = max(0, $v['qty'] - $reserved);

            fputcsv($output, [$p['p_id'], $v['variant_id'], $p['p_name'], $variant_name, $v['sku'], $v['barcode'], $v['qty'], $reserved, $available]);
        }
    }
}
fclose($output);
exit;
?>
