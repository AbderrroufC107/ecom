<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SELECT role, COUNT(*) FROM tbl_user GROUP BY role');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
