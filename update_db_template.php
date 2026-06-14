<?php
// Try to connect with config credentials first, then fallback to root/empty
$credentials = [
    ['host' => 'localhost', 'name' => 'boomtsvp_ecommerceweb', 'user' => 'boomtsvp_boomstore', 'pass' => 'Chenna2002@'],
    ['host' => 'localhost', 'name' => 'boomtsvp_ecommerceweb', 'user' => 'root', 'pass' => '']
];

$pdo = null;

foreach ($credentials as $cred) {
    try {
        $pdo = new PDO("mysql:host={$cred['host']};dbname={$cred['name']};charset=utf8mb4", $cred['user'], $cred['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully using user: {$cred['user']}\n";
        break;
    } catch (PDOException $e) {
        echo "Failed with user {$cred['user']}: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    die("Could not connect to database with any credentials.\n");
}

try {
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM tbl_product LIKE 'product_template'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        // Add the column if it doesn't exist
        $sql = "ALTER TABLE tbl_product ADD COLUMN product_template VARCHAR(50) NOT NULL DEFAULT 'landing_page.php'";
        $pdo->exec($sql);
        echo "Column 'product_template' added successfully.\n";
    } else {
        echo "Column 'product_template' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
