<?php
require 'admin/inc/config.php';
$r = $pdo->query('SHOW TABLES LIKE "tbl_ai%"');
print_r($r->fetchAll(PDO::FETCH_COLUMN));
$r2 = $pdo->query('SHOW TABLES LIKE "tbl_omni%"');
print_r($r2->fetchAll(PDO::FETCH_COLUMN));
$r3 = $pdo->query('SHOW TABLES LIKE "tbl_n8n%"');
print_r($r3->fetchAll(PDO::FETCH_COLUMN));
