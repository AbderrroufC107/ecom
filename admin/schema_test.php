<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$stmt = $dbRepo->query('DESCRIBE tbl_language');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
