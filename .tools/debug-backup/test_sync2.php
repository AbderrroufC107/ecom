<?php
require_once('inc/config.php');
require_once('inc/functions.php');

$s = ecotrack_normalize_settings(front_get_settings($pdo));
$r = ecotrack_api_request($pdo, $s, 'GET', '/api/v1/get/trackings/info', ['trackings[]'=>'EC2PD62604131015294'], null, 'bearer');
echo json_encode($r['json']['results'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
