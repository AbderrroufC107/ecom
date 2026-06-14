<?php
// Mock environment for CLI
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['PHP_SELF'] = '/ecom/verify_implementation.php';

// Include necessary files
// require_once('admin/inc/config.php'); // Skipping this to use custom connection logic
require_once('inc/encryption.php');

echo "Starting Verification...\n";
echo "--------------------------------------------------\n";

// Robust Connection Logic
$credentials = [
    ['host' => 'localhost', 'name' => 'boomtsvp_ecommerceweb', 'user' => 'boomtsvp_boomstore', 'pass' => 'Chenna2002@'],
    ['host' => 'localhost', 'name' => 'boomtsvp_ecommerceweb', 'user' => 'root', 'pass' => '']
];

$pdo = null;

foreach ($credentials as $cred) {
    try {
        $pdo = new PDO("mysql:host={$cred['host']};dbname={$cred['name']};charset=utf8mb4", $cred['user'], $cred['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully to DB using user: {$cred['user']}\n";
        break;
    } catch (PDOException $e) {
        echo "Failed with user {$cred['user']}: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    die("Could not connect to database with any credentials.\n");
}

try {
    // 1. Clean up potential leftovers
    $pdo->exec("DELETE FROM tbl_product WHERE p_name LIKE 'TEST_VERIFY_%'");

    // 2. Insert Test Product 1 (Landing Page)
    echo "Inserting Test Product 1 (Landing Page)...\n";
    $stmt = $pdo->prepare("INSERT INTO tbl_product (
        p_name, p_old_price, p_current_price, p_qty, p_featured_photo, p_description, p_short_description, p_feature, p_condition, p_return_policy, p_total_view, p_is_featured, p_is_active, ecat_id, product_template
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        'TEST_VERIFY_LANDING', 100, 80, 10, 'test.jpg', 'Desc', 'Short Desc', '', '', '', 0, 0, 1, 1, 'landing_page.php'
    ]);
    $id1 = $pdo->lastInsertId();
    echo "Product 1 ID: $id1\n";

    // 3. Insert Test Product 2 (Standard Page)
    echo "Inserting Test Product 2 (Standard Page)...\n";
    $stmt->execute([
        'TEST_VERIFY_STANDARD', 200, 150, 5, 'test2.jpg', 'Desc', 'Short Desc', '', '', '', 0, 0, 1, 1, 'buy-now.php'
    ]);
    $id2 = $pdo->lastInsertId();
    echo "Product 2 ID: $id2\n";

    // 4. Verify Database Values
    echo "\nVerifying Database Values:\n";
    $checkStmt = $pdo->prepare("SELECT p_id, p_name, product_template FROM tbl_product WHERE p_id IN (?, ?)");
    
    // Re-fetch full rows for detailed check
    $checkStmt->execute([$id1, $id2]);
    $rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        echo "[ID: {$row['p_id']}] Template: {$row['product_template']} ... ";
        if (($row['p_id'] == $id1 && $row['product_template'] == 'landing_page.php') ||
            ($row['p_id'] == $id2 && $row['product_template'] == 'buy-now.php')) {
            echo "OK\n";
        } else {
            echo "FAIL\n";
        }
    }

    // 5. Verify Link Generation
    echo "\nVerifying Link Generation:\n";
    
    $link1 = create_secure_product_link($id1, 'landing_page.php');
    echo "Link 1 (Expect landing_page.php): $link1 ... ";
    if (strpos($link1, 'landing_page.php') !== false) echo "PASS\n"; else echo "FAIL\n";

    $link2 = create_secure_product_link($id2, 'buy-now.php');
    echo "Link 2 (Expect buy-now.php): $link2 ... ";
    if (strpos($link2, 'buy-now.php') !== false) echo "PASS\n"; else echo "FAIL\n";

    // Test Default Behavior (should be buy-now.php based on function definition)
    $linkDefault = create_secure_product_link($id2); 
    echo "Link Default (Expect buy-now.php): $linkDefault ... ";
    if (strpos($linkDefault, 'buy-now.php') !== false) echo "PASS\n"; else echo "FAIL\n";

    // 6. Cleanup
    echo "\nCleaning up...\n";
    $pdo->exec("DELETE FROM tbl_product WHERE p_name LIKE 'TEST_VERIFY_%'");
    echo "Done.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
