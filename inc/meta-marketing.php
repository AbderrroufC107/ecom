<?php
declare(strict_types=1);

if (!function_exists('meta_marketing_ensure_settings_columns')) {
    function meta_marketing_ensure_settings_columns(PDO $pdo): void
    {
        if (function_exists('admin_add_column_if_missing')) {
            admin_add_column_if_missing($pdo, 'tbl_settings', 'meta_marketing_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
            admin_add_column_if_missing($pdo, 'tbl_settings', 'meta_marketing_access_token', 'TEXT NULL');
            admin_add_column_if_missing($pdo, 'tbl_settings', 'meta_marketing_ad_account_id', "VARCHAR(64) NOT NULL DEFAULT ''");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'meta_marketing_graph_version', "VARCHAR(16) NOT NULL DEFAULT 'v25.0'");
            admin_add_column_if_missing($pdo, 'tbl_settings', 'meta_marketing_test_event_code', "VARCHAR(120) NOT NULL DEFAULT ''");
            return;
        }

        try {
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS meta_marketing_enabled TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS meta_marketing_access_token TEXT NULL");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS meta_marketing_ad_account_id VARCHAR(64) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS meta_marketing_graph_version VARCHAR(16) NOT NULL DEFAULT 'v25.0'");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS meta_marketing_test_event_code VARCHAR(120) NOT NULL DEFAULT ''");
        } catch (Throwable $e) {
            error_log('Meta Marketing settings migration failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('meta_marketing_normalize_graph_version')) {
    function meta_marketing_normalize_graph_version($value): string
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

if (!function_exists('meta_marketing_resolve_access_token')) {
    /**
     * Resolve the Meta CAPI/Marketing access token from wherever it's stored.
     *
     * There are two configuration flows that save the token in different places:
     *  1. The legacy meta-marketing.php page: a plain column
     *     tbl_settings.meta_marketing_access_token.
     *  2. The newer "Marketing Center" (marketing-accounts.php): the token is
     *     stored ENCRYPTED via SecretManager, keyed per linked ad account
     *     ("meta_marketing_{account}_{tenant}"), and tbl_settings only keeps the
     *     ad account + pixel. This page never writes meta_marketing_access_token.
     *
     * The CAPI sender used to read only (1), so anyone who set things up through
     * the Marketing Center had a "fully configured" UI but events silently never
     * fired (no token found). This checks (1) then (2).
     */
    function meta_marketing_resolve_access_token(PDO $pdo, array $settings): string
    {
        $token = trim((string) ($settings['meta_marketing_access_token'] ?? ''));
        if ($token !== '') {
            return $token;
        }

        try {
            $secretManagerPath = dirname(__DIR__) . '/admin/inc/Security/SecretManager.php';
            if (!class_exists('\\Security\\SecretManager') && is_file($secretManagerPath)) {
                require_once $secretManagerPath;
            }
            if (!class_exists('\\Security\\SecretManager') || !defined('APP_SECRET_KEY')) {
                return '';
            }

            // Default to tenant 1 (single-store installs). TenantContext throws
            // if it wasn't initialized (e.g. delivery-sync / cron contexts), so
            // never let that abort token resolution.
            $tenantId = 1;
            if (class_exists('\\SaaS\\TenantContext') && method_exists('\\SaaS\\TenantContext', 'getTenantId')) {
                try {
                    $tenantId = (int) \SaaS\TenantContext::getTenantId();
                } catch (Throwable $e) {
                    $tenantId = 1;
                }
            }
            if ($tenantId <= 0) {
                $tenantId = 1;
            }

            $secretManager = new \Security\SecretManager($pdo);

            // Prefer the token of a linked, active ad account.
            $accountId = '';
            try {
                $stmt = $pdo->prepare("SELECT account_id FROM tbl_meta_ad_accounts WHERE tenant_id = ? AND (is_deleted IS NULL OR is_deleted = 0) AND (status IS NULL OR status = 'ACTIVE') ORDER BY id DESC LIMIT 1");
                $stmt->execute([$tenantId]);
                $accountId = (string) ($stmt->fetchColumn() ?: '');
            } catch (Throwable $e) {
                $accountId = '';
            }

            if ($accountId !== '') {
                $t = $secretManager->getSecret("meta_marketing_{$accountId}_{$tenantId}");
                if ($t) {
                    return trim($t);
                }
            }

            $t = $secretManager->getSecret("meta_marketing_{$tenantId}");
            if ($t) {
                return trim($t);
            }
        } catch (Throwable $e) {
            error_log('meta_marketing_resolve_access_token: ' . $e->getMessage());
        }

        return '';
    }
}

if (!function_exists('meta_marketing_is_configured')) {
    function meta_marketing_is_configured(array $settings): bool
    {
        $enabled = !empty($settings['meta_marketing_enabled']) || !empty($settings['marketing_api_enabled']);
        $hasPixel = trim((string) ($settings['facebook_pixel_id'] ?? '')) !== '';
        $hasToken = trim((string) ($settings['meta_marketing_access_token'] ?? '')) !== '';
        return $enabled && $hasPixel && $hasToken;
    }
}

if (!function_exists('meta_marketing_hash')) {
    function meta_marketing_hash($value): string
    {
        $value = strtolower(trim((string) $value));
        return $value === '' ? '' : hash('sha256', $value);
    }
}

if (!function_exists('meta_marketing_client_ip')) {
    function meta_marketing_client_ip(): string
    {
        if (function_exists('site_security_client_ip')) {
            return site_security_client_ip();
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}

if (!function_exists('meta_marketing_event_url')) {
    function meta_marketing_event_url(array $settings): string
    {
        $version = meta_marketing_normalize_graph_version($settings['meta_marketing_graph_version'] ?? 'v25.0');
        $pixelId = rawurlencode(trim((string) ($settings['facebook_pixel_id'] ?? '')));
        return 'https://graph.facebook.com/' . $version . '/' . $pixelId . '/events';
    }
}

if (!function_exists('meta_marketing_normalize_ad_account_id')) {
    function meta_marketing_normalize_ad_account_id($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        return strpos($value, 'act_') === 0 ? $value : 'act_' . preg_replace('/\D+/', '', $value);
    }
}

if (!function_exists('meta_marketing_request')) {
    function meta_marketing_request(string $url, array $payload, int $timeout = 6): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not encode Meta payload.'];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not initialize cURL.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = is_string($body) ? '' : (string) curl_error($ch);
            curl_close($ch);
            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => is_string($body) ? $body : '',
                'error' => $error,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (!empty($http_response_header) && preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $matches)) {
            $status = (int) $matches[1];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => is_string($body) ? '' : 'HTTP request failed.',
        ];
    }
}

if (!function_exists('meta_marketing_get_request')) {
    function meta_marketing_get_request(string $url, int $timeout = 8): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'data' => null, 'body' => '', 'error' => 'Could not initialize cURL.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = is_string($body) ? '' : (string) curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\n",
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

if (!function_exists('meta_marketing_api_url')) {
    function meta_marketing_api_url(array $settings, string $path, array $query = []): string
    {
        $version = meta_marketing_normalize_graph_version($settings['meta_marketing_graph_version'] ?? 'v25.0');
        $query['access_token'] = trim((string) ($settings['meta_marketing_access_token'] ?? ''));
        return 'https://graph.facebook.com/' . $version . '/' . ltrim($path, '/') . '?' . http_build_query($query);
    }
}

if (!function_exists('meta_marketing_fetch_insights')) {
    function meta_marketing_fetch_insights(array $settings, string $datePreset = 'last_30d'): array
    {
        $accountId = meta_marketing_normalize_ad_account_id($settings['meta_marketing_ad_account_id'] ?? '');
        if ($accountId === '') {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Missing Meta ad account ID.'];
        }

        $allowedPresets = ['today', 'yesterday', 'last_7d', 'last_14d', 'last_30d', 'this_month', 'last_month'];
        if (!in_array($datePreset, $allowedPresets, true)) {
            $datePreset = 'last_30d';
        }

        $url = meta_marketing_api_url($settings, $accountId . '/insights', [
            'date_preset' => $datePreset,
            'fields' => 'spend,impressions,clicks,ctr,cpc,cpm,actions,action_values,purchase_roas',
            'limit' => 1,
        ]);

        return meta_marketing_get_request($url);
    }
}

if (!function_exists('meta_marketing_fetch_campaigns')) {
    function meta_marketing_fetch_campaigns(array $settings, string $datePreset = 'last_30d', int $limit = 25): array
    {
        $accountId = meta_marketing_normalize_ad_account_id($settings['meta_marketing_ad_account_id'] ?? '');
        if ($accountId === '') {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Missing Meta ad account ID.'];
        }

        $allowedPresets = ['today', 'yesterday', 'last_7d', 'last_14d', 'last_30d', 'this_month', 'last_month'];
        if (!in_array($datePreset, $allowedPresets, true)) {
            $datePreset = 'last_30d';
        }

        $limit = max(1, min(100, $limit));
        $url = meta_marketing_api_url($settings, $accountId . '/campaigns', [
            'fields' => 'id,name,status,effective_status,objective,created_time,updated_time,insights.date_preset(' . $datePreset . '){spend,impressions,clicks,ctr,cpc,cpm,actions,purchase_roas}',
            'limit' => $limit,
        ]);

        return meta_marketing_get_request($url);
    }
}

if (!function_exists('meta_marketing_metric_from_actions')) {
    function meta_marketing_metric_from_actions($actions, array $types): float
    {
        if (!is_array($actions)) {
            return 0.0;
        }
        foreach ($actions as $action) {
            $type = (string) ($action['action_type'] ?? '');
            if (in_array($type, $types, true)) {
                return (float) ($action['value'] ?? 0);
            }
        }
        return 0.0;
    }
}

if (!function_exists('meta_marketing_send_event')) {
    function meta_marketing_send_event(PDO $pdo, string $eventName, array $eventData): array
    {
        $settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];
        if (!meta_marketing_is_configured($settings)) {
            return ['sent' => false, 'skipped' => true, 'reason' => 'Meta Marketing API is not configured.'];
        }

        $eventId = trim((string) ($eventData['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = 'evt_' . bin2hex(random_bytes(12));
        }

        $userData = [
            'client_ip_address' => meta_marketing_client_ip(),
            'client_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ];
        if (!empty($eventData['email'])) {
            $userData['em'] = [meta_marketing_hash($eventData['email'])];
        }
        if (!empty($eventData['phone'])) {
            $userData['ph'] = [meta_marketing_hash(preg_replace('/\D+/', '', (string) $eventData['phone']))];
        }
        if (!empty($eventData['first_name'])) {
            $userData['fn'] = [meta_marketing_hash($eventData['first_name'])];
        }
        if (!empty($eventData['last_name'])) {
            $userData['ln'] = [meta_marketing_hash($eventData['last_name'])];
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $eventData['event_source_url'] ?? ((function_exists('next_base_url') ? next_base_url() : '') . '/'),
            'user_data' => array_filter($userData, static fn($value) => $value !== '' && $value !== []),
            'custom_data' => array_filter([
                'currency' => $eventData['currency'] ?? 'DZD',
                'value' => isset($eventData['value']) ? (float) $eventData['value'] : null,
                'content_ids' => $eventData['content_ids'] ?? null,
                'content_name' => $eventData['content_name'] ?? null,
                'content_type' => $eventData['content_type'] ?? 'product',
                'num_items' => isset($eventData['num_items']) ? (int) $eventData['num_items'] : null,
                'order_id' => isset($eventData['order_id']) ? (string) $eventData['order_id'] : null,
            ], static fn($value) => $value !== null && $value !== '' && $value !== []),
        ];

        $payload = [
            'data' => [$event],
            'access_token' => trim((string) $settings['meta_marketing_access_token']),
        ];
        $testCode = trim((string) ($settings['meta_marketing_test_event_code'] ?? ''));
        if ($testCode !== '') {
            $payload['test_event_code'] = $testCode;
        }

        $response = meta_marketing_request(meta_marketing_event_url($settings), $payload);
        if (!$response['ok']) {
            error_log('Meta Marketing event failed: HTTP ' . $response['status'] . ' ' . $response['body'] . ' ' . $response['error']);
        }

        return [
            'sent' => $response['ok'],
            'skipped' => false,
            'event_id' => $eventId,
            'status' => $response['status'],
            'body' => $response['body'],
            'error' => $response['error'],
        ];
    }
}

if (!function_exists('meta_marketing_send_event_for_product')) {
    /**
     * Send a server-side (Conversions API) event to every Facebook pixel linked to a product
     * (tbl_pixel/tbl_product_pixel), falling back to the single global pixel in tbl_settings
     * when the product has no pixels of its own. A product can run on several ad accounts at
     * once, each with its own pixel, so the same real-world event (e.g. an order actually
     * delivered) must be reported to all of them.
     */
    function meta_marketing_send_event_for_product(PDO $pdo, int $productId, string $eventName, array $eventData): array
    {
        $settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];
        // Token may live in tbl_settings (legacy page) or in SecretManager per
        // linked ad account (new Marketing Center) - resolve from both.
        $accessToken = meta_marketing_resolve_access_token($pdo, $settings);
        // Graph version: new Marketing Center saves graph_api_version; legacy saves
        // meta_marketing_graph_version. Accept either.
        $graphVersion = meta_marketing_normalize_graph_version($settings['meta_marketing_graph_version'] ?? ($settings['graph_api_version'] ?? 'v25.0'));
        $testCode = trim((string) ($settings['meta_marketing_test_event_code'] ?? ''));

        $pixelIds = [];
        try {
            $stmt = $pdo->prepare("SELECT tp.pixel_id FROM tbl_pixel tp JOIN tbl_product_pixel tpp ON tp.id = tpp.pixel_id WHERE tpp.product_id = ? AND LOWER(tp.pixel_network) = 'facebook'");
            $stmt->execute([$productId]);
            $pixelIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'pixel_id');
        } catch (Throwable $e) {
            $pixelIds = [];
        }
        if (!$pixelIds) {
            $globalPixel = trim((string) ($settings['facebook_pixel_id'] ?? ''));
            if ($globalPixel !== '') {
                $pixelIds = [$globalPixel];
            }
        }
        $pixelIds = array_values(array_unique(array_filter($pixelIds, static fn($v) => trim((string) $v) !== '')));

        if (!$pixelIds || $accessToken === '') {
            return ['sent' => false, 'skipped' => true, 'reason' => 'No Facebook pixel or access token configured.', 'pixels' => []];
        }

        $eventId = trim((string) ($eventData['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = 'evt_' . bin2hex(random_bytes(12));
        }

        $userData = [];
        if (!empty($eventData['phone'])) {
            $userData['ph'] = [meta_marketing_hash(preg_replace('/\D+/', '', (string) $eventData['phone']))];
        }
        if (!empty($eventData['email'])) {
            $userData['em'] = [meta_marketing_hash($eventData['email'])];
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'system_generated',
            'user_data' => array_filter($userData, static fn($v) => $v !== '' && $v !== []),
            'custom_data' => array_filter([
                'currency' => $eventData['currency'] ?? 'DZD',
                'value' => isset($eventData['value']) ? (float) $eventData['value'] : null,
                'order_id' => isset($eventData['order_id']) ? (string) $eventData['order_id'] : null,
            ], static fn($v) => $v !== null && $v !== ''),
        ];

        $results = [];
        foreach ($pixelIds as $pixelId) {
            $url = 'https://graph.facebook.com/' . $graphVersion . '/' . rawurlencode((string) $pixelId) . '/events';
            $payload = ['data' => [$event], 'access_token' => $accessToken];
            if ($testCode !== '') {
                $payload['test_event_code'] = $testCode;
            }
            $response = meta_marketing_request($url, $payload);
            if (!$response['ok']) {
                error_log("Meta CAPI event failed for pixel {$pixelId}: HTTP {$response['status']} {$response['body']} {$response['error']}");
            }
            $results[$pixelId] = $response['ok'];
        }

        return [
            'sent' => in_array(true, $results, true),
            'skipped' => false,
            'event_id' => $eventId,
            'pixels' => $results,
        ];
    }
}
