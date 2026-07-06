<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$stores = $pdo->query('SELECT TABLE_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = "ecom" AND COLUMN_NAME = "store_id"')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables with store_id:\n"; print_r($stores);
$tenants = $pdo->query('SELECT TABLE_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = "ecom" AND COLUMN_NAME = "tenant_id"')->fetchAll(PDO::FETCH_COLUMN);
echo "\nTables with tenant_id:\n"; print_r($tenants);
