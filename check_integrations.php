<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SELECT id, environment, label, base_url FROM tbl_n8n_integrations');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
