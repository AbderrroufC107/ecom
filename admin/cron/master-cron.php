<?php
// Master Cron Job for periodic tasks
// Usage via CLI: php -f /path/to/admin/cron/master-cron.php

// Ensure it's run from CLI to prevent web access abuse (optional, but good for security)
if (php_sapi_name() !== 'cli' && !isset($_GET['secure_token'])) {
    die('Access Denied. Run via CLI or provide secure token.');
}

// Emulate web environment basics so included scripts don't fail
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../');
$admin_dir = realpath(__DIR__ . '/../');
chdir($admin_dir); // Change working directory to admin folder

require_once $admin_dir . '/inc/config.php';
require_once $admin_dir . '/inc/functions.php';

// Disable output buffering / set time limit for heavy tasks
set_time_limit(0);
ini_set('memory_limit', '1024M');

echo "Starting Master Cron Job: " . date('Y-m-d H:i:s') . "\n";

// 1. Sync EcoTrack
echo "-> Syncing EcoTrack...\n";
try {
    require $admin_dir . '/ajax-ecotrack-autosync.php';
} catch (Exception $e) {
    echo "Error in EcoTrack sync: " . $e->getMessage() . "\n";
}

// 2. Sync ZR Express
echo "-> Syncing ZR Express...\n";
try {
    require $admin_dir . '/ajax-zrexpress-autosync.php';
} catch (Exception $e) {
    echo "Error in ZR Express sync: " . $e->getMessage() . "\n";
}

// Add any other recurring tasks here

echo "Master Cron Job finished: " . date('Y-m-d H:i:s') . "\n";
