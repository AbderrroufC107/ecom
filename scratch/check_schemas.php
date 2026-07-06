<?php
require_once('C:/xampp/htdocs/ecom/admin/inc/config.php');

$stores = $pdo->query("SHOW COLUMNS FROM tbl_stores")->fetchAll(PDO::FETCH_ASSOC);
echo "=== tbl_stores ===\n";
print_r($stores);

$store = $pdo->query("SHOW COLUMNS FROM tbl_store")->fetchAll(PDO::FETCH_ASSOC);
echo "=== tbl_store ===\n";
print_r($store);

$orders = $pdo->query("SHOW COLUMNS FROM tbl_order")->fetchAll(PDO::FETCH_ASSOC);
echo "=== tbl_order ===\n";
print_r($orders);

