<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
\SaaS\TenantContext::init(1);
$sqls = [
    "INSERT INTO tbl_login_attempts (ip_address, success) VALUES ('127.0.0.1', 0)",
    "INSERT INTO some_table (col1) VALUES (?)",
    "INSERT INTO some_table (col1) VALUES ( ? )"
];
foreach($sqls as $sql) {
    try {
        // Output the transformed SQL
        $dbRepo->prepare($sql);
        echo "OK\n";
    } catch(Exception $e) {
        echo 'ERR: ' . $e->getMessage() . "\n";
    }
}
