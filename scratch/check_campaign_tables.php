<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

// Check which Meta/campaign related tables exist
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "=== ALL TABLES ===\n";
foreach ($tables as $t) {
    if (strpos($t, 'campaign') !== false || strpos($t, 'omni') !== false || strpos($t, 'ad') !== false || strpos($t, 'meta') !== false || strpos($t, 'pixel') !== false || strpos($t, 'broadcast') !== false) {
        echo "  - $t\n";
    }
}
echo "\n=== FULL TABLE LIST ===\n";
foreach ($tables as $t) echo "  $t\n";
