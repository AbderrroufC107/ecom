<?php
require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

$settings = ecotrack_normalize_settings(front_get_settings($pdo));

if (!ecotrack_is_configured($settings)) {
    die("Ecotrack not configured\n");
}

echo "Fetching wilayas...\n";
$wilayas = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/wilayas', [], null, 'bearer');
file_put_contents('eco_wilayas.json', json_encode($wilayas['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if (!empty($wilayas['json'][0]['wilaya_id'])) {
    echo "Fetching communes for wilaya 1...\n";
    $communes = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/communes', ['wilaya_id' => $wilayas['json'][0]['wilaya_id']], null, 'bearer');
    file_put_contents('eco_communes.json', json_encode($communes['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo "Fetching fees...\n";
$fees = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/fees', [], null, 'bearer');
file_put_contents('eco_fees.json', json_encode($fees['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Fetching centers (desks)...\n";
$centers = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/centers', [], null, 'bearer');
file_put_contents('eco_centers.json', json_encode($centers['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Done.\n";
