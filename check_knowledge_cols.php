<?php
require 'admin/inc/config.php';
$r = $pdo->query("SHOW COLUMNS FROM tbl_ai_knowledge");
print_r($r->fetchAll(PDO::FETCH_COLUMN));
