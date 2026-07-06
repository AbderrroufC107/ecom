<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$stmt = $pdo->query("SELECT id, workflow_name FROM tbl_n8n_integrations LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
