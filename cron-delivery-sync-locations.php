<?php
// cron-delivery-sync-locations.php
// This script is meant to be run via cron (e.g., daily or hourly)
// It synchronizes all delivery companies cache data.

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/inc/delivery_cache_functions.php';

// Ensure it can run without timeouts
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "Starting Delivery Cache Synchronization...\n";

$results = delivery_cache_sync_all($pdo);

foreach ($results as $company_code => $result) {
    if ($result['success']) {
        echo "[{$company_code}] Sync Successful!\n";
        echo "   - Added: " . $result['stats']['added'] . "\n";
        echo "   - Updated: " . $result['stats']['updated'] . "\n";
        echo "   - Deleted: " . $result['stats']['deleted'] . "\n";
    } else {
        echo "[{$company_code}] Sync Failed: " . $result['message'] . "\n";
    }
}

echo "Synchronization complete.\n";
