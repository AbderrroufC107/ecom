<?php
// Debug login error by simulating the actual login POST
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/admin/login.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

// Capture all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'C:/xampp/htdocs/ecom/admin/inc/config.php';
    echo "Config OK\n";
    
    require_once 'C:/xampp/htdocs/ecom/admin/inc/functions.php';
    echo "Functions OK\n";
    
    require_once 'C:/xampp/htdocs/ecom/admin/inc/store.php';
    echo "Store OK\n";
    
    require_once 'C:/xampp/htdocs/ecom/admin/inc/LoginThrottle.php';
    echo "LoginThrottle OK\n";
    
    store_ensure_tables($pdo);
    echo "store_ensure_tables OK\n";
    
    $throttle = new LoginThrottle($pdo);
    echo "LoginThrottle instance OK\n";
    
    $ip = '127.0.0.1';
    $email = 'test@test.com';
    
    $locked = $throttle->is_locked_out($ip, $email);
    echo "is_locked_out OK: " . ($locked ? 'YES' : 'NO') . "\n";
    
    // Test the actual user query
    $statement = $dbRepo->prepare("SELECT * FROM tbl_user WHERE email=? AND status=1");
    $statement->execute([$email]);
    $admin_result = $statement->fetchAll(\PDO::FETCH_ASSOC);
    echo "tbl_user query OK, results: " . count($admin_result) . "\n";
    
} catch (\Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
