<?php
/**
 * CRON SCRIPT FOR DELIVERY API SYNC (ERP Phase 8)
 * This script should be run every 5-10 minutes.
 * It loops through all pending/shipped orders, checks their status via API, and updates the ERP.
 */

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/functions.php';
require_once __DIR__ . '/../admin/inc/integration/DeliveryManager.php';

// Fetch active orders that need syncing (Pending, or Failed with a scheduled retry time)
$stmt = $pdo->prepare("
    SELECT id, delivery_company_id, tracking_number, order_status 
    FROM tbl_order 
    WHERE order_status NOT IN ('Delivered', 'Returned', 'Cancelled') 
    AND tracking_number IS NOT NULL 
    AND tracking_number != ''
    AND delivery_company_id IS NOT NULL
    AND (
        sync_status = 'Pending' 
        OR 
        (sync_status = 'Failed' AND next_sync_time IS NOT NULL AND next_sync_time <= NOW())
    )
");
$stmt->execute();
$orders_to_sync = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders_to_sync)) {
    echo "No orders require synchronization at this time.\n";
    exit;
}

$success_count = 0;
$fail_count = 0;

foreach ($orders_to_sync as $order) {
    echo "Syncing Order #{$order['id']} (Tracking: {$order['tracking_number']})... ";
    try {
        $result = DeliveryManager::syncOrder($pdo, $order['id']);
        echo "OK! New Status: {$result['new_status']}\n";
        $success_count++;
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $fail_count++;
    }
}

echo "\nSync Complete. Success: {$success_count}, Failed: {$fail_count}\n";
