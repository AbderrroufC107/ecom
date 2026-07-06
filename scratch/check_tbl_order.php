<?php
require 'c:/xampp/htdocs/ecom/admin/inc/config.php';
$stmt = $pdo->query("DESCRIBE tbl_order");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
