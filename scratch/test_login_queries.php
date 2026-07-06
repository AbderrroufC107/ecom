<?php
// Test what SQL is being generated for the login queries
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/admin/login.php';

require_once 'C:/xampp/htdocs/ecom/admin/inc/config.php';
\SaaS\TenantContext::init(1);

// Test the problematic query types from login.php

// 1. Test SELECT * FROM tbl_user WHERE email=? AND status=1
$sql1 = "SELECT * FROM tbl_user WHERE email=? AND status=1";
echo "=== Query 1: tbl_user ===\n";
try {
    $stmt = $dbRepo->prepare($sql1);
    echo "OK - prepared\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 2. Test SELECT * FROM tbl_employee WHERE email=? AND is_active=1
$sql2 = "SELECT * FROM tbl_employee WHERE email=? AND is_active=1";
echo "\n=== Query 2: tbl_employee ===\n";
try {
    $stmt = $dbRepo->prepare($sql2);
    echo "OK - prepared\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 3. Test the LoginThrottle queries
$sql3 = "SELECT COUNT(*) FROM tbl_login_attempts WHERE (ip_address = ? OR login_identifier = ?) AND attempt_time >= ? AND success = 0";
echo "\n=== Query 3: tbl_login_attempts ===\n";
try {
    $stmt = $dbRepo->prepare($sql3);
    echo "OK - prepared\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 4. Test INSERT
$sql4 = "INSERT INTO tbl_login_attempts (ip_address, user_agent, login_identifier, success) VALUES (?, ?, ?, ?)";
echo "\n=== Query 4: INSERT tbl_login_attempts ===\n";
try {
    $stmt = $dbRepo->prepare($sql4);
    echo "OK - prepared\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
