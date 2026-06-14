<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$scope = trim((string) ($_GET['scope'] ?? ''));
$options = [];

if (isset($_GET['order_id'])) {
    $options['order_id'] = (int) $_GET['order_id'];
}

echo json_encode([
    'success' => true,
    'scope' => $scope,
    'version' => admin_live_refresh_version($pdo, $scope, $options),
    'checked_at' => gmdate('c')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
