<?php
// Simulate an actual web login POST to see the exact SQL error
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/admin/login.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestAgent';
$_SERVER['REQUEST_METHOD'] = 'POST';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simulate POST
$_POST['form1'] = 1;
$_POST['email'] = 'abderraouftchanna@gmail.com';
$_POST['password'] = 'testpass123';

ob_start();
try {
    require_once 'C:/xampp/htdocs/ecom/admin/login.php';
} catch (\Throwable $e) {
    echo "\n=== FATAL ERROR ===\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
$output = ob_get_clean();
// Check for SQL error in output
if (strpos($output, 'SQLSTATE') !== false || strpos($output, 'SQL syntax') !== false) {
    preg_match('/SQLSTATE\[.*?\].*?(?=<|$)/s', $output, $m);
    echo "SQL ERROR IN PAGE: " . ($m[0] ?? 'found but could not extract') . "\n";
} elseif (strpos($output, 'Error') !== false) {
    echo "ERROR IN OUTPUT: " . substr(strip_tags($output), 0, 500) . "\n";
} else {
    echo "Page output OK (length: " . strlen($output) . ")\n";
    echo "First 300 chars: " . substr(strip_tags($output), 0, 300) . "\n";
}
