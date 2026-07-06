<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

// Check tbl_ai_campaign structure
echo "=== tbl_ai_campaign COLUMNS ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM tbl_ai_campaign");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}

// Check tbl_pixel structure
echo "\n=== tbl_pixel COLUMNS ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM tbl_pixel");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}

// Sample campaigns data
echo "\n=== SAMPLE CAMPAIGNS DATA ===\n";
$stmt = $pdo->query("SELECT * FROM tbl_ai_campaign LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    print_r($rows[0]);
} else {
    echo "  (empty table)\n";
}
