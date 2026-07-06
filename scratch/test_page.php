<?php
// Simulate a real browser request (non-CLI)
$_SERVER['PHP_SELF'] = '/ecom/admin/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = '';
$_SERVER['SERVER_PORT'] = '80';

// Start session to simulate TenantMiddleware::boot()
session_start();
$_SESSION['tenant_id'] = 1;

require_once 'C:/xampp/htdocs/ecom/admin/inc/config.php';
echo "Config loaded OK. tenant_id=" . \SaaS\TenantContext::getTenantId() . "\n";

// Try loading a typical page include
require_once 'C:/xampp/htdocs/ecom/admin/inc/functions.php';
echo "functions.php loaded OK\n";
