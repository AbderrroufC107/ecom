<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
global $pdo;
$tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
$global_tables = [];
foreach ($tables as $t) {
    $cols = $pdo->query("SHOW COLUMNS FROM $t LIKE 'tenant_id'")->fetchAll();
    if (empty($cols)) {
        $global_tables[] = $t;
    }
}
echo "Global Tables (No tenant_id):\n";
echo implode("', '", $global_tables);
echo "\n";
