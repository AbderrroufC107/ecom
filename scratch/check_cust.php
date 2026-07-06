<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$cols = $pdo->query("SHOW COLUMNS FROM tbl_customer")->fetchAll(PDO::FETCH_ASSOC);
print_r(array_column($cols, 'Field'));
