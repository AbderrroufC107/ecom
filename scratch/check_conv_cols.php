<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
print_r(array_column($pdo->query("SHOW COLUMNS FROM tbl_omni_conversations")->fetchAll(PDO::FETCH_ASSOC), 'Field'));
