<?php
require 'c:/xampp/htdocs/ecom/admin/inc/config.php';
$sql = file_get_contents('c:/xampp/htdocs/ecom/admin/sql/erp_tables.sql');
$pdo->exec($sql);
echo "SQL Executed Successfully.";
