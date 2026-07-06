<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
require 'C:/xampp/htdocs/ecom/admin/inc/LoginThrottle.php';
\SaaS\TenantContext::init(1);
try {
    $throttle = new LoginThrottle($pdo);
    $throttle->record_attempt('127.0.0.1', 'test@test.com', 'test', false);
    echo "record_attempt OK\n";
} catch(Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
