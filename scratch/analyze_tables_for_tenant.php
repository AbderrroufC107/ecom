<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$exclude_tables = [
    'tbl_wilaya', 
    'tbl_commune', 
    'tbl_language', 
    'tbl_store', // Single store table maybe?
    'tbl_stores', // Maybe rename this to tbl_tenants
    'tbl_tenants' // The one we will create
];

$target_tables = array_diff($tables, $exclude_tables);

echo "Tables to isolate: " . count($target_tables) . "\n";
print_r($target_tables);
