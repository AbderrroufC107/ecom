<?php
require 'c:/xampp/htdocs/ecom/admin/inc/config.php';
try {
    $pdo->exec("ALTER TABLE tbl_order ADD COLUMN delivery_company_id INT NULL");
} catch(Exception $e) {}
try {
    $pdo->exec("ALTER TABLE tbl_order ADD COLUMN tracking_number VARCHAR(255) NULL");
} catch(Exception $e) {}
echo "Done";
