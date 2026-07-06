<?php
declare(strict_types=1);

if (!function_exists('whatsapp_business_ensure_settings_columns')) {
    function whatsapp_business_ensure_settings_columns(PDO $pdo): void
    {
        if (function_exists('admin_add_column_if_missing')) {
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_business_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_business_access_token', 'TEXT NULL');
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_business_phone_number_id', "VARCHAR(80) NOT NULL DEFAULT ''");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_business_account_id', "VARCHAR(80) NOT NULL DEFAULT ''");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_business_graph_version', "VARCHAR(16) NOT NULL DEFAULT 'v25.0'");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_business_verify_token', "VARCHAR(190) NOT NULL DEFAULT ''");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_order_customer_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_order_admin_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_order_admin_phone', "VARCHAR(40) NOT NULL DEFAULT ''");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_order_template_name', "VARCHAR(190) NOT NULL DEFAULT ''");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'whatsapp_order_template_language', "VARCHAR(20) NOT NULL DEFAULT 'ar'");
            return;
        }

        try {
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_business_enabled TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_business_access_token TEXT NULL");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_business_phone_number_id VARCHAR(80) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_business_account_id VARCHAR(80) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_business_graph_version VARCHAR(16) NOT NULL DEFAULT 'v25.0'");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_business_verify_token VARCHAR(190) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_customer_enabled TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_admin_enabled TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_admin_phone VARCHAR(40) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_template_name VARCHAR(190) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_template_language VARCHAR(20) NOT NULL DEFAULT 'ar'");
        } catch (Throwable $e) {
            error_log('WhatsApp Business settings migration failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('whatsapp_business_normalize_graph_version')) {
    function whatsapp_business_normalize_graph_version($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'v25.0';
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            $value = 'v' . $value;
        }
        return preg_match('/^v\d+(?:\.\d+)?$/', $value) ? $value : 'v25.0';
    }
}

if (!function_exists('whatsapp_business_normalize_phone')) {
    function whatsapp_business_normalize_phone($phone, string $defaultCountryCode = '213'): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }
        if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
            $digits = $defaultCountryCode . substr($digits, 1);
        }
        return $digits;
    }
}

if (!function_exists('whatsapp_business_is_configured')) {
    function whatsapp_business_is_configured(array $settings): bool
    {
        return !empty($settings['whatsapp_business_enabled'])
            && trim((string) ($settings['whatsapp_business_access_token'] ?? '')) !== ''
            && trim((string) ($settings['whatsapp_business_phone_number_id'] ?? '')) !== '';
    }
}

if (!function_exists('whatsapp_business_api_url')) {
    function whatsapp_business_api_url(array $settings, string $edge = 'messages'): string
    {
        $version = whatsapp_business_normalize_graph_version($settings['whatsapp_business_graph_version'] ?? 'v25.0');
        $phoneNumberId = rawurlencode(trim((string) ($settings['whatsapp_business_phone_number_id'] ?? '')));
        return 'https://graph.facebook.com/' . $version . '/' . $phoneNumberId . '/' . ltrim($edge, '/');
    }
}

if (!function_exists('whatsapp_business_request')) {
    function whatsapp_business_request(array $settings, array $payload, int $timeout = 8): array
    {
        $token = trim((string) ($settings['whatsapp_business_access_token'] ?? ''));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'body' => '', 'error' => 'Could not encode WhatsApp payload.'];
        }

        $url = whatsapp_business_api_url($settings, 'messages');
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'data' => null, 'body' => '', 'error' => 'Could not initialize cURL.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_POSTFIELDS => $json,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = is_string($body) ? '' : (string) curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
                    'content' => $json,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            $status = 0;
            if (!empty($http_response_header) && preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $matches)) {
                $status = (int) $matches[1];
            }
            $error = is_string($body) ? '' : 'HTTP request failed.';
        }

        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        $message = $error;
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $message = (string) $decoded['error']['message'];
        }

        return [
            'ok' => $status >= 200 && $status < 300 && is_array($decoded),
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : null,
            'body' => is_string($body) ? $body : '',
            'error' => $message,
        ];
    }
}

if (!function_exists('whatsapp_business_format_order_text')) {
    function whatsapp_business_format_order_text(array $orderData): string
    {
        $lines = [
            'New order #' . (string) ($orderData['order_id'] ?? '-'),
            'Customer: ' . (string) ($orderData['customer_name'] ?? '-'),
            'Phone: ' . (string) ($orderData['customer_phone'] ?? '-'),
            'Product: ' . (string) ($orderData['product_name'] ?? '-'),
            'Qty: ' . (string) ($orderData['quantity'] ?? '1'),
            'Total: ' . (string) ($orderData['total_price'] ?? '-') . ' DZD',
            'Delivery: ' . trim((string) ($orderData['wilaya'] ?? '') . ' / ' . (string) ($orderData['commune'] ?? '')),
        ];
        return implode("\n", $lines);
    }
}

if (!function_exists('whatsapp_business_template_payload')) {
    function whatsapp_business_template_payload(string $to, string $templateName, string $languageCode, array $parameters = []): array
    {
        $params = [];
        foreach ($parameters as $value) {
            $params[] = ['type' => 'text', 'text' => (string) $value];
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode !== '' ? $languageCode : 'ar'],
        ];
        if (!empty($params)) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => $params,
            ]];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ];
    }
}

if (!function_exists('whatsapp_business_text_payload')) {
    function whatsapp_business_text_payload(string $to, string $text): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ];
    }
}

if (!function_exists('whatsapp_business_send_order')) {
    function whatsapp_business_send_order(PDO $pdo, array $orderData, string $target = 'customer'): array
    {
        $settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];
        if (!whatsapp_business_is_configured($settings)) {
            return ['sent' => false, 'skipped' => true, 'reason' => 'WhatsApp Business API is not configured.'];
        }

        $rawPhone = $target === 'admin'
            ? ($settings['whatsapp_order_admin_phone'] ?? '')
            : ($orderData['customer_phone'] ?? '');
        $to = whatsapp_business_normalize_phone($rawPhone);
        if ($to === '') {
            return ['sent' => false, 'skipped' => true, 'reason' => 'Missing WhatsApp recipient phone.'];
        }

        $templateName = trim((string) ($settings['whatsapp_order_template_name'] ?? ''));
        $languageCode = trim((string) ($settings['whatsapp_order_template_language'] ?? 'ar'));
        if ($templateName !== '') {
            $payload = whatsapp_business_template_payload($to, $templateName, $languageCode, [
                $orderData['customer_name'] ?? '',
                $orderData['order_id'] ?? '',
                $orderData['product_name'] ?? '',
                $orderData['total_price'] ?? '',
            ]);
        } else {
            $payload = whatsapp_business_text_payload($to, whatsapp_business_format_order_text($orderData));
        }

        $response = whatsapp_business_request($settings, $payload);
        if (!$response['ok']) {
            error_log('WhatsApp Business send failed: HTTP ' . $response['status'] . ' ' . $response['body'] . ' ' . $response['error']);
        }

        return [
            'sent' => $response['ok'],
            'skipped' => false,
            'status' => $response['status'],
            'data' => $response['data'],
            'error' => $response['error'],
        ];
    }
}
