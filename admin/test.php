<?php
require 'inc/config.php';
$stmt = $pdo->query('SELECT * FROM tbl_n8n_integrations');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
