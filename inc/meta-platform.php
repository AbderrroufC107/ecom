<?php
declare(strict_types=1);

if (!function_exists('meta_platform_ensure_settings_columns')) {
    function meta_platform_ensure_settings_columns(PDO $pdo): void
    {
        $columns = [
            'meta_app_id' => "VARCHAR(120) NOT NULL DEFAULT ''",
            'meta_app_secret' => 'TEXT NULL',
            'meta_webhook_verify_token' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'meta_platform_graph_version' => "VARCHAR(16) NOT NULL DEFAULT 'v25.0'",
            'instagram_platform_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'instagram_access_token' => 'TEXT NULL',
            'instagram_business_account_id' => "VARCHAR(120) NOT NULL DEFAULT ''",
            'messenger_platform_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'messenger_page_id' => "VARCHAR(120) NOT NULL DEFAULT ''",
            'messenger_page_access_token' => 'TEXT NULL',
            'messenger_order_admin_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'messenger_admin_psid' => "VARCHAR(190) NOT NULL DEFAULT ''",
        ];

        foreach ($columns as $column => $definition) {
            if (function_exists('admin_add_column_if_missing')) {
                admin_add_column_if_missing($pdo, 'tbl_settings', $column, $definition);
            } else {
                try {
                    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS {$column} {$definition}");
                } catch (Throwable $e) {
                    error_log('Meta Platform settings migration failed for ' . $column . ': ' . $e->getMessage());
                }
            }
        }
    }
}

if (!function_exists('meta_platform_ensure_webhook_log_table')) {
    function meta_platform_ensure_webhook_log_table(PDO $pdo): void
    {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_meta_webhook_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    object_type VARCHAR(80) NOT NULL DEFAULT '',
                    event_field VARCHAR(120) NOT NULL DEFAULT '',
                    sender_id VARCHAR(190) NOT NULL DEFAULT '',
                    recipient_id VARCHAR(190) NOT NULL DEFAULT '',
                    payload LONGTEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_object_type (object_type),
                    INDEX idx_event_field (event_field),
                    INDEX idx_sender_id (sender_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            error_log('Meta Platform webhook table migration failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('meta_platform_graph_version')) {
    function meta_platform_graph_version($value): string
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

if (!function_exists('meta_platform_settings')) {
    function meta_platform_settings(PDO $pdo): array
    {
        meta_platform_ensure_settings_columns($pdo);
        return function_exists('front_get_settings') ? front_get_settings($pdo) : [];
    }
}

if (!function_exists('meta_platform_api_get')) {
    function meta_platform_api_get(array $settings, string $path, array $query = [], string $tokenKey = 'instagram_access_token'): array
    {
        $token = trim((string) ($settings[$tokenKey] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Missing access token.'];
        }
        $query['access_token'] = $token;
        $version = meta_platform_graph_version($settings['meta_platform_graph_version'] ?? 'v25.0');
        $url = 'https://graph.facebook.com/' . $version . '/' . ltrim($path, '/') . '?' . http_build_query($query);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Could not initialize cURL.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = is_string($body) ? '' : (string) curl_error($ch);
            curl_close($ch);
        } else {
            $body = @file_get_contents($url);
            $status = 0;
            $error = is_string($body) ? '' : 'HTTP request failed.';
        }

        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $error = (string) $decoded['error']['message'];
        }

        return [
            'ok' => $status >= 200 && $status < 300 && is_array($decoded),
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : null,
            'error' => $error,
        ];
    }
}

if (!function_exists('meta_platform_instagram_profile')) {
    function meta_platform_instagram_profile(array $settings): array
    {
        $igId = trim((string) ($settings['instagram_business_account_id'] ?? ''));
        if ($igId === '') {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Missing Instagram business account ID.'];
        }
        return meta_platform_api_get($settings, $igId, [
            'fields' => 'id,username,name,account_type,media_count,followers_count,follows_count,profile_picture_url',
        ], 'instagram_access_token');
    }
}

if (!function_exists('meta_platform_instagram_media')) {
    function meta_platform_instagram_media(array $settings, int $limit = 12): array
    {
        $igId = trim((string) ($settings['instagram_business_account_id'] ?? ''));
        if ($igId === '') {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Missing Instagram business account ID.'];
        }
        return meta_platform_api_get($settings, $igId . '/media', [
            'fields' => 'id,caption,media_type,media_url,permalink,timestamp,like_count,comments_count',
            'limit' => max(1, min(50, $limit)),
        ], 'instagram_access_token');
    }
}

if (!function_exists('meta_platform_messenger_send_text')) {
    function meta_platform_messenger_send_text(PDO $pdo, string $recipientPsid, string $text): array
    {
        $settings = meta_platform_settings($pdo);
        if (empty($settings['messenger_platform_enabled'])) {
            return ['sent' => false, 'skipped' => true, 'error' => 'Messenger Platform is disabled.'];
        }
        $token = trim((string) ($settings['messenger_page_access_token'] ?? ''));
        if ($token === '' || trim($recipientPsid) === '') {
            return ['sent' => false, 'skipped' => true, 'error' => 'Missing Messenger token or PSID.'];
        }

        $version = meta_platform_graph_version($settings['meta_platform_graph_version'] ?? 'v25.0');
        $url = 'https://graph.facebook.com/' . $version . '/me/messages?access_token=' . rawurlencode($token);
        $payload = [
            'messaging_type' => 'UPDATE',
            'recipient' => ['id' => $recipientPsid],
            'message' => ['text' => $text],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['sent' => false, 'skipped' => false, 'error' => 'Could not initialize cURL.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = is_string($body) ? '' : (string) curl_error($ch);
            curl_close($ch);
        } else {
            $body = false;
            $status = 0;
            $error = 'cURL is required for Messenger send.';
        }

        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $error = (string) $decoded['error']['message'];
        }
        $ok = $status >= 200 && $status < 300 && is_array($decoded);
        if (!$ok) {
            error_log('Messenger send failed: HTTP ' . $status . ' ' . (is_string($body) ? $body : '') . ' ' . $error);
        }
        return ['sent' => $ok, 'skipped' => false, 'status' => $status, 'data' => $decoded, 'error' => $error];
    }
}

if (!function_exists('meta_platform_order_message')) {
    function meta_platform_order_message(array $orderData): string
    {
        return implode("\n", [
            'New order #' . (string) ($orderData['order_id'] ?? '-'),
            'Customer: ' . (string) ($orderData['customer_name'] ?? '-'),
            'Phone: ' . (string) ($orderData['customer_phone'] ?? '-'),
            'Product: ' . (string) ($orderData['product_name'] ?? '-'),
            'Total: ' . (string) ($orderData['total_price'] ?? '-') . ' DZD',
        ]);
    }
}

if (!function_exists('meta_platform_verify_signature')) {
    function meta_platform_verify_signature(string $rawBody, string $appSecret, string $signatureHeader): bool
    {
        if ($appSecret === '') {
            return true;
        }
        if (substr($signatureHeader, 0, 7) !== 'sha256=') {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signatureHeader);
    }
}
