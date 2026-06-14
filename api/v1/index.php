<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

$store_id = 0;
$api_key_id = 0;
$key_name = '';

// Authenticate all requests
$key_data = api_authenticate($pdo);
if (!$key_data) {
    exit;
}

$store_id = (int) $key_data['store_id'];
$api_key_id = (int) $key_data['id'];
$key_name = $key_data['name'] ?? '';

// Check subscription (read-only mode blocks writes)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$is_write = in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true);

if ($is_write) {
    store_require_write_access($pdo, $store_id);
}

// Parse route
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$base_path = dirname($_SERVER['SCRIPT_NAME'] ?? '/api/v1');
$route = str_replace($base_path, '', parse_url($request_uri, PHP_URL_PATH));
$route = '/' . trim($route, '/');

// Extract path params
$parts = array_values(array_filter(explode('/', $route)));
$resource = $parts[0] ?? '';
$action = $parts[1] ?? '';
$sub_id = isset($parts[2]) ? api_validate_id($parts[2]) : 0;

// Log request (before response)
$start_time = microtime(true);

try {
    switch ($resource) {
        case 'orders':
            require __DIR__ . '/orders.php';
            break;
        case 'products':
            require __DIR__ . '/products.php';
            break;
        case 'customers':
            require __DIR__ . '/customers.php';
            break;
        case 'analytics':
            require __DIR__ . '/analytics.php';
            break;
        case 'performance':
            require __DIR__ . '/analytics.php';
            break;
        case 'recovery':
            require __DIR__ . '/analytics.php';
            break;
        case 'risk':
            require __DIR__ . '/analytics.php';
            break;
        case '':
            api_success([
                'name' => 'Ecom API v1',
                'version' => '1.0',
                'store_id' => $store_id,
                'key_name' => $key_name,
                'endpoints' => [
                    'orders' => '/api/v1/orders',
                    'products' => '/api/v1/products',
                    'customers' => '/api/v1/customers',
                    'analytics' => '/api/v1/analytics',
                    'performance' => '/api/v1/performance',
                    'recovery' => '/api/v1/recovery',
                    'risk' => '/api/v1/risk',
                ],
                'docs' => '/docs/api/',
            ]);
            return;
        default:
            api_error('الموجه غير موجودة: /' . $resource, 404, 'NOT_FOUND');
    }
} catch (Throwable $e) {
    api_error('خطأ داخلي في الخادم: ' . $e->getMessage(), 500, 'SERVER_ERROR');
}

// Log the API call
$elapsed = (int) ((microtime(true) - $start_time) * 1000);
$response_code = http_response_code() ?: 200;
store_log_api_call($pdo, $store_id, $api_key_id, $route, $method, $response_code);
