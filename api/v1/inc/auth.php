<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../admin/inc/config.php';
require_once __DIR__ . '/../../../admin/inc/store.php';
require_once __DIR__ . '/../../../admin/inc/audit.php';

store_ensure_tables($pdo);

if (!function_exists('api_authenticate')) {
    function api_authenticate(PDO $pdo, string $required_permission = ''): ?array
    {
        $api_key = '';

        // Check Authorization header (Bearer token)
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth_header, $m)) {
            $api_key = trim($m[1]);
        }

        // Fallback: X-API-Key header
        if ($api_key === '') {
            $api_key = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
        }

        // Fallback: api_key query param
        if ($api_key === '') {
            $api_key = trim((string) ($_GET['api_key'] ?? ''));
        }

        if ($api_key === '') {
            api_error('مطلوب مفتاح API', 401, 'API_KEY_MISSING');
            return null;
        }

        $key_data = store_validate_api_key($pdo, $api_key, $required_permission);
        if (!$key_data) {
            api_error('مفتاح API غير صالح أو صلاحيات غير كافية', 403, 'API_KEY_INVALID');
            return null;
        }

        return $key_data;
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message, int $status = 400, string $code = 'ERROR'): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('api_success')) {
    function api_success($data, int $status = 200, array $meta = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        $response = ['success' => true, 'data' => $data];
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('api_read_payload')) {
    function api_read_payload(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode((string) $raw, true);
            return is_array($json) ? $json : [];
        }
        return $_POST;
    }
}

if (!function_exists('api_validate_id')) {
    function api_validate_id($value): int
    {
        return ctype_digit((string) $value) ? (int) $value : 0;
    }
}

// CORS + OPTIONS handling
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
