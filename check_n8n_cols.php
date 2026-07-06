<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM tbl_n8n_integrations');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
