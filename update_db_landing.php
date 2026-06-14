<?php
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
        echo "Connected successfully using user: {$cred['user']}\n";
        break;
    } catch (PDOException $e) {
        echo "Failed with user {$cred['user']}: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    die("Could not connect to database.\n");
}

try {
    $columns = ['landing_photo_1', 'landing_photo_2', 'landing_photo_3'];
    foreach ($columns as $col) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM tbl_product LIKE ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN $col VARCHAR(255) DEFAULT ''");
            echo "Added column $col\n";
        } else {
            echo "Column $col already exists\n";
        }
    }
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
