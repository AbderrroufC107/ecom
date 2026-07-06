<?php
require 'admin/inc/config.php';
$tables = ['tbl_order_assignment', 'tbl_commission_payment', 'tbl_employee_commission'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";
    } catch (Exception $e) {
        echo "Table $table not found.\n";
    }
}
