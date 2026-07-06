<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM tbl_faq');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
