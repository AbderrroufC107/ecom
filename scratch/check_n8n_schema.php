<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
print_r($pdo->query("SHOW COLUMNS FROM tbl_n8n_integrations")->fetchAll(PDO::FETCH_ASSOC));
print_r($pdo->query("SHOW COLUMNS FROM tbl_n8n_call_log")->fetchAll(PDO::FETCH_ASSOC));
