<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SHOW CREATE TABLE tbl_ai_tasks');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
