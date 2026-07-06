<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SELECT * FROM tbl_ai_tasks ORDER BY id DESC LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
