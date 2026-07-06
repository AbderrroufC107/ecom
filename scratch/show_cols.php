<?php
require_once 'C:/xampp/htdocs/ecom/admin/inc/config.php';
global $pdo;
foreach (['tbl_product','tbl_order','tbl_customer'] as $t) {
    $cols = $pdo->query("SHOW COLUMNS FROM $t")->fetchAll(\PDO::FETCH_COLUMN);
    echo "$t: " . implode(', ', $cols) . "\n\n";
}
