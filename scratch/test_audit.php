<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
\SaaS\TenantContext::init(1);
require 'C:/xampp/htdocs/ecom/admin/inc/audit.php';
try {
    audit_log_security($pdo, 1, 'login_success', null, ['email' => 'test@test.com'], 'admin_panel');
    echo "audit_log_security OK\n";
} catch(Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
