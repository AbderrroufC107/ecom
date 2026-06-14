<?php
require_once('inc/config.php');
require_once('inc/functions.php');

$settings = ecotrack_normalize_settings(front_get_settings($pdo));

$sql = "
    SELECT id, ecotrack_tracking, ecotrack_remote_status 
    FROM tbl_order 
    WHERE ecotrack_tracking IS NOT NULL 
      AND ecotrack_tracking != '' 
      AND (ecotrack_remote_status IS NULL OR ecotrack_remote_status = '')
    LIMIT 10
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die("No active trackings stuck on syncing.");
}

$trackings = [];
foreach ($orders as $o) {
    $trackings[] = trim($o['ecotrack_tracking']);
}

echo "Stuck Tracking numbers:\n";
print_r($trackings);

$query = [];
foreach ($trackings as $t) {
    $query['trackings[]'] = $t;
}

$request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', $query, null, 'bearer');

echo "\nAPI Response JSON:\n";
print_r($request['json']);

// Also query individually
echo "\n--- INDIVIDUAL QUERIES ---\n";
foreach($trackings as $t) {
    $r = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/tracking/info', ['tracking' => $t], null, 'bearer');
    echo "Tracking: $t => ";
    if(isset($r['json']['message'])) {
        echo "Message: " . $r['json']['message'] . "\n";
    } elseif(isset($r['json']['current_status'])) {
        echo "Status: " . $r['json']['current_status'] . "\n";
    } elseif(isset($r['json']['status'])) {
        echo "Status: " . $r['json']['status'] . "\n";
    } else {
        echo "Unknown response: " . json_encode($r['json']) . "\n";
    }
}
