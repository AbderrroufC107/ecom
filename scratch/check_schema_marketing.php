<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';
// Check settings columns
$cols = $pdo->query('SHOW COLUMNS FROM tbl_settings')->fetchAll(PDO::FETCH_COLUMN);
echo "SETTINGS COLS: " . implode(', ', $cols) . "\n";

// Check tbl_ai_campaign structure
$cols2 = $pdo->query('SHOW COLUMNS FROM tbl_ai_campaign')->fetchAll(PDO::FETCH_ASSOC);
echo "AI_CAMPAIGN COLS:\n";
foreach ($cols2 as $c) echo "  {$c['Field']} ({$c['Type']})\n";

// Check autoload pattern for Security namespace
echo "\nAutoload check - Security\\SecretManager exists? " . (class_exists('\\Security\\SecretManager') ? 'YES' : 'NO') . "\n";

// Check omni channels
$cols3 = $pdo->query('SHOW COLUMNS FROM tbl_omni_channels')->fetchAll(PDO::FETCH_ASSOC);
echo "\nOMNI_CHANNELS COLS:\n";
foreach ($cols3 as $c) echo "  {$c['Field']} ({$c['Type']})\n";

// Check tbl_customer
$cols4 = $pdo->query('SHOW COLUMNS FROM tbl_customer')->fetchAll(PDO::FETCH_ASSOC);
echo "\nCUSTOMER COLS:\n";
foreach ($cols4 as $c) echo "  {$c['Field']} ({$c['Type']})\n";
