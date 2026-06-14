<?php
require_once('inc/config.php');
require_once('inc/functions.php');
$settings = ecotrack_normalize_settings(front_get_settings($pdo));

$t = 'EC2PD62604131015264';
$req1 = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', ['trackings[]' => '["'.$t.'"]'], null, 'bearer');
print_r($req1['json']);

// What if it's just trackings[]=EC123,EC456 ?
$req2 = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', ['trackings[]' => $t], null, 'bearer');
print_r($req2['json']);

// What if it's trackings[0]=EC... ?
$req3 = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', ['trackings' => [$t]], null, 'bearer');
print_r($req3['json']);
