<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SHOW CREATE TABLE tbl_audit_log');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
