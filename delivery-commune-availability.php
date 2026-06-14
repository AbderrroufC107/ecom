<?php
require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$wilaya = trim((string) ($_GET['wilaya'] ?? $_POST['wilaya'] ?? ''));
$commune = trim((string) ($_GET['commune'] ?? $_POST['commune'] ?? ''));
$product_id = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

if ($wilaya === '' || $commune === '') {
    echo json_encode([
        'success' => false,
        'office_available' => null,
        'message' => 'Missing wilaya or commune.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$product_delivery_settings = get_product_delivery_settings($pdo, $product_id);
if ($product_id > 0 && !product_delivery_company_has_office_for_wilaya($pdo, $product_delivery_settings['company_id'], $wilaya, $product_delivery_settings['delivery_mode'])) {
    echo json_encode([
        'success' => true,
        'office_available' => false,
        'fallback' => true,
        'message' => 'Office delivery is not enabled for this product delivery company and wilaya.',
        'resolved_delivery_type' => 'home',
        'company_id' => (int) $product_delivery_settings['company_id'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
$context = ecotrack_resolve_order_delivery_context($pdo, $settings, [
    'wilaya' => $wilaya,
    'commune' => $commune,
    'delivery_type' => 'office'
]);

echo json_encode([
    'success' => true,
    'office_available' => !empty($context['fallback']) ? false : $context['has_stop_desk'],
    'fallback' => !empty($context['fallback']),
    'message' => (string) ($context['fallback_message'] ?? ''),
    'resolved_delivery_type' => (string) (($context['order']['delivery_type'] ?? ''))
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
