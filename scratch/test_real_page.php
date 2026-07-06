<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/admin/index.php';

// Simulate standard bootstrap
require_once 'C:/xampp/htdocs/ecom/admin/inc/config.php';

// In CLI, we need to initialize context manually to test because boot() skips it
\SaaS\TenantContext::init(1);

require_once 'C:/xampp/htdocs/ecom/admin/inc/functions.php';
require_once 'C:/xampp/htdocs/ecom/admin/inc/header.php';

echo "Page loaded successfully.\n";
