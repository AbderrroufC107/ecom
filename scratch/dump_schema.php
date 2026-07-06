<?php
require_once __DIR__ . '/../admin/inc/config.php';
$tables = ['tbl_payment', 'tbl_employee', 'tbl_order'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";
    } catch (Exception $e) {
        echo "Table $table not found.\n";
    }
}
