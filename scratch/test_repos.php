<?php
// Quick sanity check: can we load all SaaS classes?
$baseDir = 'C:/xampp/htdocs/ecom/admin/inc/SaaS';
require_once $baseDir . '/TenantContext.php';
require_once $baseDir . '/TenantMiddleware.php';
require_once $baseDir . '/Repositories/BaseRepository.php';

$repos = glob($baseDir . '/Repositories/*.php');
foreach ($repos as $f) {
    require_once $f;
    echo basename($f) . " - loaded OK\n";
}

// Test TenantContext
\SaaS\TenantContext::init(1);
echo "\nTenantContext::getTenantId() = " . \SaaS\TenantContext::getTenantId() . "\n";
echo "All SaaS classes load correctly.\n";
