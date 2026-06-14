<?php
require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$wilaya = trim((string) ($_GET['wilaya'] ?? $_POST['wilaya'] ?? ''));
$communes_payload = $_POST['communes'] ?? ($_GET['communes'] ?? '');
$product_id = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

if ($wilaya === '') {
    echo json_encode([
        'success' => false,
        'wilaya' => '',
        'communes' => [],
        'message' => 'Missing wilaya.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
$wilaya_code = function_exists('ecotrack_algeria_wilaya_code') ? (int) (ecotrack_algeria_wilaya_code($wilaya) ?? 0) : 0;
if ($wilaya_code <= 0) {
    echo json_encode([
        'success' => false,
        'wilaya' => $wilaya,
        'communes' => [],
        'message' => 'Unknown wilaya code.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$product_delivery_settings = get_product_delivery_settings($pdo, $product_id);
if ($product_id > 0 && !product_delivery_company_has_office_for_wilaya($pdo, $product_delivery_settings['company_id'], $wilaya, $product_delivery_settings['delivery_mode'])) {
    echo json_encode([
        'success' => true,
        'wilaya' => $wilaya,
        'communes' => [],
        'company_id' => (int) $product_delivery_settings['company_id'],
        'message' => 'Office delivery is not enabled for this product delivery company and wilaya.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rows = ecotrack_read_cached_communes($wilaya_code);
if (empty($rows)) {
    $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/communes', ['wilaya_id' => $wilaya_code], null, 'bearer');
    if (!empty($request['success']) && is_array($request['json'])) {
        $rows = $request['json'];
        if (isset($rows['communes']) && is_array($rows['communes'])) {
            $rows = $rows['communes'];
        }
        if (is_array($rows)) {
            ecotrack_write_cached_communes($wilaya_code, $rows);
        }
    }
}

$communes_with_office = [];
// إذا أُرسلت بلديات الواجهة (العربية)، نتحقق من توفر المكتب لكل بلدية ثم نعيد نفس الأسماء العربية.
$requested_communes = [];
if (is_array($communes_payload)) {
    $requested_communes = $communes_payload;
} elseif (is_string($communes_payload) && trim($communes_payload) !== '') {
    $decoded = json_decode($communes_payload, true);
    if (is_array($decoded)) {
        $requested_communes = $decoded;
    }
}

if (!empty($requested_communes)) {
    foreach ($requested_communes as $commune_name_raw) {
        $commune_name = trim((string) $commune_name_raw);
        if ($commune_name === '') {
            continue;
        }
        $meta = ecotrack_find_commune_meta($pdo, $settings, $wilaya_code, $commune_name);
        $has_office = ecotrack_commune_has_stop_desk($meta);
        if ($has_office === true) {
            $communes_with_office[] = $commune_name;
        }
    }
} elseif (is_array($rows)) {
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['nom'] ?? $row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $has = ecotrack_commune_has_stop_desk($row);
        if ($has === true || (is_numeric($has) && (int) $has === 1)) {
            $communes_with_office[] = $name;
        }
    }
}

echo json_encode([
    'success' => true,
    'wilaya' => $wilaya,
    'communes' => array_values(array_unique($communes_with_office)),
    'message' => ''
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
