<?php
require_once('inc/config.php');
require_once('inc/functions.php');

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
if (!ecotrack_is_configured($settings)) {
    die("Ecotrack not configured");
}

$sql = "SELECT id, ecotrack_tracking, ecotrack_remote_status FROM tbl_order WHERE ecotrack_tracking IS NOT NULL AND ecotrack_tracking != '' LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die("No active trackings to sync.");
}

$query = [];
foreach ($orders as $o) {
    $query['trackings[]'] = $o['ecotrack_tracking'];
}

echo "Requesting:\n";
print_r($query);

$request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', $query, null, 'bearer');

echo "\nResponse:\n";
print_r($request);
