<?php

// ─── Tenant Isolation Helpers ──────────────────────────────────────────────
if (!function_exists('get_current_tenant_id')) {
    function get_current_tenant_id(): int { global $dbRepo;
        // In a SaaS environment, the tenant_id is derived from the authenticated session.
        // Currently single-tenant: always 1. Ready for multi-tenant extension.
        if (session_status() === PHP_SESSION_NONE) session_start();
        return (int)($_SESSION['tenant_id'] ?? 1);
    }
}

if (!function_exists('pdo_fetch_all_for_tenant')) {
    /**
     * Execute a SELECT query scoped to the current tenant.
     * Automatically appends AND tenant_id = ? if not already present.
     */
    function pdo_fetch_all_for_tenant(PDO $pdo, string $sql, array $params = []): array { global $dbRepo;
        $tenant_id = get_current_tenant_id();
        if (stripos($sql, 'tenant_id') === false && stripos($sql, 'FROM tbl_tenants') === false) {
            // Determine the alias of the primary table to avoid ambiguous column errors on JOINs
            $tenant_col = 'tenant_id';
            if (preg_match('/FROM\s+([a-zA-Z0-9_`]+)(?:\s+(?:AS\s+)?([a-zA-Z0-9_`]+))?(?:\s+WHERE|\s+JOIN|\s+LEFT|\s+RIGHT|\s+INNER|\s+ON|\s+ORDER|\s+GROUP|\s+LIMIT|\s*$)/i', $sql, $matches)) {
                $tableName = strtolower(trim($matches[1], '`'));
                $globalTables = [
                    'tbl_commune', 'tbl_country', 'tbl_delivery_company', 
                    'tbl_language', 'tbl_store', 'tbl_stores', 'tbl_tenants', 
                    'tbl_test_logs', 'tbl_wilaya', 'information_schema', 'tbl_plans', 'tbl_page'
                ];
                
                if (in_array($tableName, $globalTables)) {
                    // Driving table is global; skip auto-injection
                    $stmt = $dbRepo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $aliasCandidate = !empty($matches[2]) ? $matches[2] : $matches[1];
                if (preg_match('/^(WHERE|JOIN|LEFT|RIGHT|INNER|ON|ORDER|GROUP|LIMIT|HAVING|ASC|DESC)$/i', $aliasCandidate)) {
                    $aliasCandidate = $matches[1];
                }
                $tenant_col = $aliasCandidate . '.tenant_id';
            }
            if (stripos($sql, 'WHERE') !== false) {
                $sql = preg_replace('/\bWHERE\b/i', "WHERE {$tenant_col} = " . (int)$tenant_id . ' AND ', $sql, 1);
            } elseif (preg_match('/\b(LIMIT|ORDER BY|GROUP BY)\b/i', $sql)) {
                $sql = preg_replace('/\b(LIMIT|ORDER BY|GROUP BY)\b/i', "WHERE {$tenant_col} = " . (int)$tenant_id . ' $1', $sql, 1);
            } else {
                $sql .= " WHERE {$tenant_col} = " . (int)$tenant_id;
            }
        }
        $stmt = $dbRepo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('get_ext')) {
    function get_ext($pdo, $fname)
    { global $dbRepo;
    global $dbRepo;

        $up_filename = $_FILES[$fname]['name'] ?? '';
        return (string)substr($up_filename, strripos($up_filename, '.'));
    }
}

if (!function_exists('ext_check')) {
    function ext_check($pdo, $allowed_ext, $my_ext)
    { global $dbRepo;
    global $dbRepo;

        $allowed = array_map(static function ($ext) {
            return '.' . ltrim(trim($ext), '.');
        }, explode('|', (string)$allowed_ext));

        return in_array((string)$my_ext, $allowed, true);
    }
}

if (!function_exists('get_ai_id')) {
    function get_ai_id($pdo, $tbl_name)
    { global $dbRepo;
    global $dbRepo;

        $statement = $dbRepo->prepare("SHOW TABLE STATUS LIKE '$tbl_name'");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            return $row['Auto_increment'];
        }
        return null;
    }
}

if (!function_exists('is_external_image_url')) {
    function is_external_image_url($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }

        if (preg_match('#^(https?:)?//#i', $value)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('is_valid_image_url')) {
    function is_valid_image_url($url)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '') {
            return false;
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }
}

if (!function_exists('is_probable_direct_image_url')) {
    function is_probable_direct_image_url($url)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '' || !is_valid_image_url($url)) {
            return false;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        $direct_hosts = [
            'i.ibb.co',
            'i.imgur.com',
            'images.unsplash.com',
            'lh3.googleusercontent.com',
            'res.cloudinary.com'
        ];
        if (in_array($host, $direct_hosts, true)) {
            return true;
        }

        $path = (string)parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }
        return (bool)preg_match('/\.(?:jpe?g|png|gif|webp|avif|bmp|svg|ico|heic|heif)(?:$|\?)/i', $path);
    }
}

if (!function_exists('can_resolve_image_from_meta_host')) {
    function can_resolve_image_from_meta_host($host)
    { global $dbRepo;
    global $dbRepo;

        $host = strtolower(trim((string)$host));
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        $supported_hosts = [
            'ibb.co',
            'imgbb.com',
            'imgur.com',
            'postimg.cc'
        ];

        return in_array($host, $supported_hosts, true);
    }
}

if (!function_exists('get_image_url_lookup_cache_file')) {
    function get_image_url_lookup_cache_file($url)
    { global $dbRepo;
    global $dbRepo;

        $cache_dir = rtrim((string)sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ecom-image-url-cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0777, true);
        }
        return $cache_dir . DIRECTORY_SEPARATOR . md5((string)$url) . '.json';
    }
}

if (!function_exists('read_cached_resolved_image_url')) {
    function read_cached_resolved_image_url($url, $ttl_seconds = 604800)
    { global $dbRepo;
    global $dbRepo;

        $cache_file = get_image_url_lookup_cache_file($url);
        if (!is_file($cache_file)) {
            return '';
        }

        $modified_at = (int)@filemtime($cache_file);
        if ($modified_at <= 0 || (time() - $modified_at) > (int)$ttl_seconds) {
            @unlink($cache_file);
            return '';
        }

        $raw = @file_get_contents($cache_file);
        if ($raw === false || $raw === '') {
            return '';
        }

        $decoded = @json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['resolved'])) {
            return '';
        }

        return trim((string)$decoded['resolved']);
    }
}

if (!function_exists('write_cached_resolved_image_url')) {
    function write_cached_resolved_image_url($url, $resolved_url)
    { global $dbRepo;
    global $dbRepo;

        $cache_file = get_image_url_lookup_cache_file($url);
        $payload = json_encode(['resolved' => trim((string)$resolved_url)], JSON_UNESCAPED_SLASHES);
        if ($payload !== false) {
            @file_put_contents($cache_file, $payload);
        }
    }
}

if (!function_exists('http_fetch_page_html')) {
    function http_fetch_page_html($url, $timeout_seconds = 4)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return '';
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(2, (int)$timeout_seconds - 2));
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout_seconds);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html,application/xhtml+xml']);
            $html = curl_exec($ch);
            if (!is_string($html) || $html === '') {
                $error_message = (string)curl_error($ch);
                $error_code = (int)curl_errno($ch);
                if (stripos($error_message, 'SSL certificate') !== false || in_array($error_code, [60, 77], true)) {
                    // Fallback for local stacks missing CA bundle (common on Windows/XAMPP).
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    $html = curl_exec($ch);
                }
            }
            curl_close($ch);

            return is_string($html) ? $html : '';
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => (int)$timeout_seconds,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header' => 'User-Agent: ' . $user_agent . "\r\nAccept: text/html,application/xhtml+xml\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $html = @file_get_contents($url, false, $context);
        return is_string($html) ? $html : '';
    }
}

if (!function_exists('extract_image_url_from_html_meta')) {
    function extract_image_url_from_html_meta($html)
    { global $dbRepo;
    global $dbRepo;

        $html = (string)$html;
        if ($html === '') {
            return '';
        }

        $patterns = [
            '/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*property=["\']og:image["\'][^>]*>/i',
            '/<meta[^>]*name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']twitter:image["\'][^>]*>/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) && !empty($matches[1])) {
                $candidate = html_entity_decode(trim((string)$matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }
}

if (!function_exists('resolve_external_image_url')) {
    function resolve_external_image_url($url, $allow_meta_lookup = false)
    { global $dbRepo;
    global $dbRepo;

        static $memory_cache = [];

        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if (!is_valid_image_url($url)) {
            return '';
        }

        if (is_probable_direct_image_url($url)) {
            return $url;
        }

        if (isset($memory_cache[$url])) {
            return $memory_cache[$url];
        }

        $cached = read_cached_resolved_image_url($url);
        if ($cached !== '') {
            if ($cached !== $url || is_probable_direct_image_url($cached)) {
                $memory_cache[$url] = $cached;
                return $cached;
            }
        }

        if (!$allow_meta_lookup) {
            $memory_cache[$url] = $url;
            return $url;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        if (!can_resolve_image_from_meta_host($host)) {
            $memory_cache[$url] = $url;
            return $url;
        }

        $html = http_fetch_page_html($url);
        $candidate = extract_image_url_from_html_meta($html);
        if (strpos($candidate, '//') === 0) {
            $candidate = 'https:' . $candidate;
        }

        if (is_valid_image_url($candidate)) {
            $memory_cache[$url] = $candidate;
            write_cached_resolved_image_url($url, $candidate);
            return $candidate;
        }

        $memory_cache[$url] = $url;
        write_cached_resolved_image_url($url, $url);
        return $url;
    }
}

if (!function_exists('normalize_external_image_url')) {
    function normalize_external_image_url($url, $allow_meta_lookup = false)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if (!is_valid_image_url($url) && !is_external_image_url($url)) {
            return '';
        }

        if (is_external_image_url($url)) {
            return resolve_external_image_url($url, $allow_meta_lookup);
        }

        return $url;
    }
}

if (!function_exists('normalize_image_value')) {
    function normalize_image_value($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string)$value);
        if ($value !== '' && strpos($value, '//') === 0) {
            $value = 'https:' . $value;
        }
        return $value;
    }
}

if (!function_exists('normalize_product_delivery_mode')) {
    function normalize_product_delivery_mode($value)
    { global $dbRepo;
    global $dbRepo;

        $value = strtolower(trim((string)$value));
        $allowed = ['free', 'home_only', 'home_office'];
        if (!in_array($value, $allowed, true)) {
            return 'home_office';
        }
        return $value;
    }
}

if (!function_exists('admin_fix_broken_arabic_text')) {
    function admin_fix_broken_arabic_text($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $value)) {
            return $value;
        }

        $candidates = [$value];
        $encodings = ['Windows-1252', 'ISO-8859-1'];

        for ($index = 0; $index < count($candidates) && $index < 10; $index++) {
            $candidate = $candidates[$index];
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $candidate)) {
                return $candidate;
            }

            foreach ($encodings as $encoding) {
                try {
                    $converted = mb_convert_encoding($candidate, $encoding, 'UTF-8');
                } catch (Throwable $exception) {
                    $converted = '';
                }

                if (is_string($converted) && $converted !== '') {
                    $converted = trim($converted);
                    if ($converted !== '' && !in_array($converted, $candidates, true)) {
                        $candidates[] = $converted;
                    }
                }
            }

            if (function_exists('iconv')) {
                foreach ($encodings as $encoding) {
                    $converted = @iconv('UTF-8', $encoding . '//IGNORE', $candidate);
                    if (is_string($converted) && $converted !== '') {
                        $converted = trim($converted);
                        if ($converted !== '' && !in_array($converted, $candidates, true)) {
                            $candidates[] = $converted;
                        }
                    }
                }
            }
        }

        return $value;
    }
}

if (!function_exists('admin_delivery_type_labels')) {
    function admin_delivery_type_labels() {        return [
            'home' => "\u{0645}\u{0646}\u{0632}\u{0644}",
            'office' => "\u{0645}\u{0643}\u{062A}\u{0628}",
            'free' => "\u{0645}\u{062C}\u{0627}\u{0646}\u{064A}",
        ];
    }
}

if (!function_exists('admin_normalize_delivery_type_text')) {
    function admin_normalize_delivery_type_text($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $labels = admin_delivery_type_labels();
        $fixed_value = admin_fix_broken_arabic_text($value);
        $normalized = strtolower($fixed_value);

        if ($fixed_value === $labels['home'] || $normalized === 'home' || $normalized === 'home_only') {
            return $labels['home'];
        }

        if ($fixed_value === $labels['office'] || $normalized === 'office' || $normalized === 'stopdesk' || $normalized === 'stop_desk') {
            return $labels['office'];
        }

        if ($fixed_value === $labels['free'] || $normalized === 'free') {
            return $labels['free'];
        }

        return $fixed_value;
    }
}

if (!function_exists('resolve_delivery_type_by_mode')) {
    function resolve_delivery_type_by_mode($requested_type, $delivery_mode)
    { global $dbRepo;
    global $dbRepo;

        $delivery_mode = normalize_product_delivery_mode($delivery_mode);
        $labels = admin_delivery_type_labels();
        $normalized_type = admin_normalize_delivery_type_text($requested_type);
        $requested_lc = strtolower(trim((string) $requested_type));

        if ($delivery_mode === 'free') {
            return $labels['free'];
        }

        if ($delivery_mode === 'home_only') {
            return $labels['home'];
        }

        if ($normalized_type === '') {
            return $labels['home'];
        }

        if ($requested_lc === 'office' || $normalized_type === $labels['office']) {
            return $labels['office'];
        }

        if ($requested_lc === 'home' || $normalized_type === $labels['home']) {
            return $labels['home'];
        }

        if ($requested_lc === 'free' || $normalized_type === $labels['free']) {
            return $labels['home'];
        }

        return $labels['home'];

        $delivery_mode = normalize_product_delivery_mode($delivery_mode);
        $type = trim((string)$requested_type);
        $type_lc = strtolower($type);

        $free_value = 'Ù…Ø¬Ø§Ù†ÙŠ';
        $home_value = 'Ù…Ù†Ø²Ù„';
        $office_value = 'Ù…ÙƒØªØ¨';

        if ($delivery_mode === 'free') {
            return $free_value;
        }

        if ($delivery_mode === 'home_only') {
            return $home_value;
        }

        if ($type === '') {
            return $home_value;
        }

        // Office markers first; for home_office mode this is the only non-home outcome.
        $is_office = (
            $type_lc === 'office'
            || strpos($type, $office_value) !== false
            || strpos($type, 'Ã™â€¦Ã™Æ’Ã˜ÂªÃ˜Â¨') !== false
            || strpos($type, 'Ãƒâ„¢Ã¢â‚¬Â¦Ãƒâ„¢Ã†â€™ÃƒËœÃ‚ÂªÃƒËœÃ‚Â¨') !== false
        );
        if ($is_office) {
            return $office_value;
        }

        $is_home = (
            $type_lc === 'home'
            || strpos($type, $home_value) !== false
            || strpos($type, 'Ã™â€¦Ã™â€ Ã˜Â²Ã™â€ž') !== false
            || strpos($type, 'Ãƒâ„¢Ã¢â‚¬Â¦Ãƒâ„¢Ã¢â‚¬Â ÃƒËœÃ‚Â²Ãƒâ„¢Ã¢â‚¬Å¾') !== false
        );
        if ($is_home) {
            return $home_value;
        }

        // In home_office mode, any "free" style value falls back to home.
        $is_free = (
            $type_lc === 'free'
            || strpos($type, $free_value) !== false
            || strpos($type, 'Ã™â€¦Ã˜Â¬Ã˜Â§Ã™â€ Ã™Å ') !== false
            || strpos($type, 'Ãƒâ„¢Ã¢â‚¬Â¦ÃƒËœÃ‚Â¬ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬Â Ãƒâ„¢Ã…Â ') !== false
        );
        if ($is_free) {
            return $home_value;
        }

        return $home_value;
    }
}

if (!function_exists('get_available_delivery_prices_for_wilaya')) {
    function get_available_delivery_prices_for_wilaya(array $shippingData, $wilaya, $deliveryMode)
    { global $dbRepo;
    global $dbRepo;

        $deliveryMode = normalize_product_delivery_mode($deliveryMode);
        $labels = admin_delivery_type_labels();
        $wilaya = trim((string) $wilaya);

        if ($deliveryMode === 'free') {
            return [$labels['free'] => 0.0];
        }

        if ($wilaya === '' || !isset($shippingData[$wilaya])) {
            return [];
        }

        $entry = $shippingData[$wilaya];
        $available = [];

        if ($deliveryMode === 'home_only') {
            if (is_array($entry) && array_key_exists($labels['home'], $entry)) {
                $available[$labels['home']] = (float) $entry[$labels['home']];
            } elseif (!is_array($entry)) {
                $available[$labels['home']] = (float) $entry;
            }
            return $available;
        }

        if (is_array($entry)) {
            foreach ([$labels['home'], $labels['office']] as $type) {
                if (array_key_exists($type, $entry)) {
                    $available[$type] = (float) $entry[$type];
                }
            }
            return $available;
        }

        $available[$labels['home']] = (float) $entry;
        return $available;
    }
}

if (!function_exists('resolve_available_delivery_type_for_wilaya')) {
    function resolve_available_delivery_type_for_wilaya(array $shippingData, $wilaya, $deliveryMode, $requestedType = '')
    { global $dbRepo;
    global $dbRepo;

        $available = get_available_delivery_prices_for_wilaya($shippingData, $wilaya, $deliveryMode);
        if (empty($available)) {
            return '';
        }

        $resolvedRequested = resolve_delivery_type_by_mode($requestedType, $deliveryMode);
        if ($resolvedRequested !== '' && array_key_exists($resolvedRequested, $available)) {
            return $resolvedRequested;
        }

        return (string) array_key_first($available);
    }
}

if (!function_exists('ensure_product_delivery_company_column')) {
    function ensure_product_delivery_company_column(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/product_delivery_company_column.lock';
        if (file_exists($lock_file)) {
            return;
        }

        try {
            $column_check = $dbRepo->query("SHOW COLUMNS FROM tbl_product LIKE 'p_delivery_company_id'");
            if ($column_check->rowCount() === 0) {
                $dbRepo->executeCommand("ALTER TABLE tbl_product ADD COLUMN p_delivery_company_id INT NULL DEFAULT NULL");
            }
            @file_put_contents($lock_file, '1');
        } catch (PDOException $e) {
            error_log('Failed to ensure p_delivery_company_id column: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_product_offer_table')) {
    function ensure_product_offer_table(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        static $ensured = false;

        if ($ensured) {
            return;
        }

        $lock_file = __DIR__ . '/../cache/product_offer_table.lock';
        if (file_exists($lock_file)) {
            $ensured = true;
            return;
        }

        try {
            $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_product_offer (
                offer_id INT AUTO_INCREMENT PRIMARY KEY,
                p_id INT NOT NULL,
                offer_qty INT NOT NULL,
                offer_unit_price DECIMAL(10,2) NOT NULL,
                offer_type VARCHAR(20) NOT NULL DEFAULT 'quantity',
                offer_description TEXT NULL,
                offer_photo VARCHAR(255) NULL DEFAULT NULL,
                is_most_popular TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_product_offer_slot (p_id, offer_type, sort_order),
                KEY idx_product_qty (p_id, offer_qty),
                KEY idx_product (p_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {
            error_log('Failed to ensure tbl_product_offer table: ' . $e->getMessage());
        }

        $columns = [
            'offer_type' => "ALTER TABLE tbl_product_offer ADD COLUMN offer_type VARCHAR(20) NOT NULL DEFAULT 'quantity' AFTER offer_unit_price",
            'offer_description' => "ALTER TABLE tbl_product_offer ADD COLUMN offer_description TEXT NULL AFTER offer_type",
            'offer_photo' => "ALTER TABLE tbl_product_offer ADD COLUMN offer_photo VARCHAR(255) NULL DEFAULT NULL AFTER offer_description",
            'is_most_popular' => "ALTER TABLE tbl_product_offer ADD COLUMN is_most_popular TINYINT(1) NOT NULL DEFAULT 0 AFTER offer_photo"
        ];

        foreach ($columns as $column_name => $sql) {
            try {
                $column_check = $dbRepo->query("SHOW COLUMNS FROM tbl_product_offer LIKE " . $pdo->quote($column_name));
                if ($column_check->rowCount() === 0) {
                    $dbRepo->executeCommand($sql);
                }
            } catch (PDOException $e) {
                error_log('Failed to ensure ' . $column_name . ' column in tbl_product_offer: ' . $e->getMessage());
            }
        }

        try {
            $legacy_unique_check = $dbRepo->query("SHOW INDEX FROM tbl_product_offer WHERE Key_name = 'uniq_product_qty'");
            if ($legacy_unique_check && $legacy_unique_check->rowCount() > 0) {
                $dbRepo->executeCommand("ALTER TABLE tbl_product_offer DROP INDEX uniq_product_qty");
            }
        } catch (PDOException $e) {
            error_log('Failed to drop legacy uniq_product_qty index in tbl_product_offer: ' . $e->getMessage());
        }

        try {
            $slot_unique_check = $dbRepo->query("SHOW INDEX FROM tbl_product_offer WHERE Key_name = 'uniq_product_offer_slot'");
            if (!$slot_unique_check || $slot_unique_check->rowCount() === 0) {
                $dbRepo->executeCommand("ALTER TABLE tbl_product_offer ADD UNIQUE KEY uniq_product_offer_slot (p_id, offer_type, sort_order)");
            }
        } catch (PDOException $e) {
            error_log('Failed to ensure uniq_product_offer_slot index in tbl_product_offer: ' . $e->getMessage());
        }

        try {
            $qty_index_check = $dbRepo->query("SHOW INDEX FROM tbl_product_offer WHERE Key_name = 'idx_product_qty'");
            if (!$qty_index_check || $qty_index_check->rowCount() === 0) {
                $dbRepo->executeCommand("ALTER TABLE tbl_product_offer ADD KEY idx_product_qty (p_id, offer_qty)");
            }
        } catch (PDOException $e) {
            error_log('Failed to ensure idx_product_qty index in tbl_product_offer: ' . $e->getMessage());
        }

        @file_put_contents($lock_file, '1');
        $ensured = true;
    }
}

if (!function_exists('get_delivery_company_options')) {
    function get_delivery_company_options(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        try {
            $statement = $dbRepo->query("SELECT id, name, active FROM tbl_delivery_company ORDER BY active DESC, name ASC, id ASC");
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Failed to load delivery companies: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('resolve_product_delivery_company_id')) {
    function resolve_product_delivery_company_id(PDO $pdo, $preferredId = 0)
    { global $dbRepo;
    global $dbRepo;

        $preferredId = (int)$preferredId;

        if ($preferredId > 0) {
            try {
                $statement = $dbRepo->prepare("SELECT id FROM tbl_delivery_company WHERE id = ? LIMIT 1");
                $statement->execute([$preferredId]);
                $resolved = $statement->fetchColumn();
                if ($resolved) {
                    return (int)$resolved;
                }
            } catch (PDOException $e) {
                error_log('Failed to resolve preferred delivery company: ' . $e->getMessage());
            }
        }

        try {
            $statement = $dbRepo->query("SELECT id FROM tbl_delivery_company WHERE active = 1 ORDER BY id ASC LIMIT 1");
            $activeId = $statement->fetchColumn();
            if ($activeId) {
                return (int)$activeId;
            }

            $statement = $dbRepo->query("SELECT id FROM tbl_delivery_company ORDER BY id ASC LIMIT 1");
            $fallbackId = $statement->fetchColumn();
            if ($fallbackId) {
                return (int)$fallbackId;
            }
        } catch (PDOException $e) {
            error_log('Failed to resolve fallback delivery company: ' . $e->getMessage());
        }

        return 0;
    }
}

if (!function_exists('get_product_delivery_settings')) {
    function get_product_delivery_settings(PDO $pdo, $productId)
    { global $dbRepo;
    global $dbRepo;

        $productId = (int) $productId;
        $settings = [
            'product_id' => $productId,
            'delivery_mode' => 'home_office',
            'preferred_company_id' => 0,
            'company_id' => resolve_product_delivery_company_id($pdo, 0),
        ];

        if ($productId <= 0) {
            return $settings;
        }

        try {
            ensure_product_delivery_company_column($pdo);
            $statement = $dbRepo->prepare("SELECT p_delivery_mode, p_delivery_company_id FROM tbl_product WHERE p_id = ? LIMIT 1");
            $statement->execute([$productId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $settings;
            }

            $preferredCompanyId = (int) ($row['p_delivery_company_id'] ?? 0);
            $settings['delivery_mode'] = normalize_product_delivery_mode($row['p_delivery_mode'] ?? 'home_office');
            $settings['preferred_company_id'] = $preferredCompanyId;
            $settings['company_id'] = resolve_product_delivery_company_id($pdo, $preferredCompanyId);
        } catch (PDOException $e) {
            error_log('Failed to load product delivery settings: ' . $e->getMessage());
        }

        return $settings;
    }
}

if (!function_exists('product_delivery_company_has_office_for_wilaya')) {
    function product_delivery_company_has_office_for_wilaya(PDO $pdo, $companyId, $wilaya, $deliveryMode)
    { global $dbRepo;
    global $dbRepo;

        $companyId = (int) $companyId;
        $wilaya = trim((string) $wilaya);
        $deliveryMode = normalize_product_delivery_mode($deliveryMode);

        if ($companyId <= 0 || $wilaya === '' || $deliveryMode === 'free' || $deliveryMode === 'home_only') {
            return false;
        }

        $labels = admin_delivery_type_labels();

        try {
            $statement = $dbRepo->prepare("SELECT price, delivery_type FROM tbl_delivery_price WHERE company_id = ? AND wilaya = ?");
            $statement->execute([$companyId, $wilaya]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $type = resolve_delivery_type_by_mode($row['delivery_type'] ?? '', $deliveryMode);
                if ($type === $labels['office'] && (float) ($row['price'] ?? 0) > 0) {
                    return true;
                }
            }
        } catch (PDOException $e) {
            error_log('Failed to check product delivery office price: ' . $e->getMessage());
        }

        return false;
    }
}

if (!function_exists('is_cloudinary_url')) {
    function is_cloudinary_url($url)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '' || !is_valid_image_url($url)) {
            return false;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host === 'res.cloudinary.com';
    }
}

if (!function_exists('cloudinary_get_config')) {
    function cloudinary_get_config() {        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $cloud_name = trim((string)(defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : ''));
        $api_key = trim((string)(defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : ''));
        $api_secret = trim((string)(defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : ''));
        $upload_preset = trim((string)(defined('CLOUDINARY_UPLOAD_PRESET') ? CLOUDINARY_UPLOAD_PRESET : ''));
        $folder = trim((string)(defined('CLOUDINARY_FOLDER') ? CLOUDINARY_FOLDER : ''));
        $strict_mode = (bool)(defined('CLOUDINARY_STRICT_MODE') ? CLOUDINARY_STRICT_MODE : false);
        $timeout = (int)(defined('CLOUDINARY_HTTP_TIMEOUT') ? CLOUDINARY_HTTP_TIMEOUT : 20);
        $timeout = max(5, min(60, $timeout));

        $has_signed_auth = ($cloud_name !== '' && $api_key !== '' && $api_secret !== '');
        $has_unsigned_auth = ($cloud_name !== '' && $upload_preset !== '');
        $enabled = function_exists('curl_init') && ($has_signed_auth || $has_unsigned_auth);

        $config = [
            'cloud_name' => $cloud_name,
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'upload_preset' => $upload_preset,
            'folder' => $folder,
            'strict_mode' => $strict_mode,
            'timeout' => $timeout,
            'enabled' => $enabled,
            'signed' => $has_signed_auth,
            'endpoint' => $cloud_name !== '' ? ('https://api.cloudinary.com/v1_1/' . rawurlencode($cloud_name) . '/image/upload') : ''
        ];

        return $config;
    }
}

if (!function_exists('cloudinary_is_enabled')) {
    function cloudinary_is_enabled() {        $cfg = cloudinary_get_config();
        return !empty($cfg['enabled']);
    }
}

if (!function_exists('cloudinary_is_strict_mode')) {
    function cloudinary_is_strict_mode() {        $cfg = cloudinary_get_config();
        return !empty($cfg['strict_mode']);
    }
}

if (!function_exists('cloudinary_make_signature')) {
    function cloudinary_make_signature(array $params, $api_secret)
    { global $dbRepo;
    global $dbRepo;

        ksort($params);
        $sign_parts = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $sign_parts[] = $key . '=' . $value;
        }
        return sha1(implode('&', $sign_parts) . (string)$api_secret);
    }
}

if (!function_exists('cloudinary_execute_upload_request')) {
    function cloudinary_execute_upload_request($file_param, $public_id = '')
    { global $dbRepo;
    global $dbRepo;

        $cfg = cloudinary_get_config();
        if (empty($cfg['enabled']) || empty($cfg['endpoint'])) {
            return [false, '', 'Cloudinary is not configured.'];
        }

        $post_fields = [];
        $sign_params = [];
        $timestamp = time();

        if ($cfg['folder'] !== '') {
            $post_fields['folder'] = $cfg['folder'];
            $sign_params['folder'] = $cfg['folder'];
        }
        if ($public_id !== '') {
            $post_fields['public_id'] = $public_id;
            $sign_params['public_id'] = $public_id;
        }

        if (!empty($cfg['signed'])) {
            $post_fields['timestamp'] = $timestamp;
            $sign_params['timestamp'] = $timestamp;
            $post_fields['api_key'] = $cfg['api_key'];
            $post_fields['signature'] = cloudinary_make_signature($sign_params, $cfg['api_secret']);
        } else {
            if ($cfg['upload_preset'] === '') {
                return [false, '', 'Cloudinary auth is missing (API credentials or upload preset).'];
            }
            $post_fields['upload_preset'] = $cfg['upload_preset'];
            $post_fields['timestamp'] = $timestamp;
        }

        $post_fields['file'] = $file_param;

        $ch = curl_init($cfg['endpoint']);
        if ($ch === false) {
            return [false, '', 'Failed to initialize Cloudinary request.'];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$cfg['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(3, (int)$cfg['timeout'] - 5));

        $body = curl_exec($ch);
        if (!is_string($body) || $body === '') {
            $err = (string)curl_error($ch);
            $errno = (int)curl_errno($ch);
            if (stripos($err, 'SSL certificate') !== false || in_array($errno, [60, 77], true)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $body = curl_exec($ch);
            }
        }

        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = (string)curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $body === '') {
            return [false, '', ($curl_error !== '' ? $curl_error : 'Empty Cloudinary response.')];
        }

        $json = json_decode($body, true);
        if ($http_code >= 200 && $http_code < 300 && is_array($json) && !empty($json['secure_url'])) {
            return [true, trim((string)$json['secure_url']), ''];
        }

        $api_error = '';
        if (is_array($json) && isset($json['error']['message'])) {
            $api_error = (string)$json['error']['message'];
        }
        if ($api_error === '') {
            $api_error = $curl_error !== '' ? $curl_error : ('Cloudinary upload failed (HTTP ' . $http_code . ').');
        }

        return [false, '', $api_error];
    }
}

if (!function_exists('cloudinary_upload_local_image')) {
    function cloudinary_upload_local_image($tmp_file, $original_name, $public_id = '')
    { global $dbRepo;
    global $dbRepo;

        $tmp_file = trim((string)$tmp_file);
        $original_name = trim((string)$original_name);
        if ($tmp_file === '' || !is_file($tmp_file)) {
            return [false, '', 'Local image file is missing.'];
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string)@mime_content_type($tmp_file);
        }
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        if (function_exists('curl_file_create')) {
            $file_param = curl_file_create($tmp_file, $mime, $original_name !== '' ? $original_name : basename($tmp_file));
        } else {
            $file_param = new CURLFile($tmp_file, $mime, $original_name !== '' ? $original_name : basename($tmp_file));
        }

        return cloudinary_execute_upload_request($file_param, $public_id);
    }
}

if (!function_exists('cloudinary_upload_remote_image')) {
    function cloudinary_upload_remote_image($url, $public_id = '')
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '' || !is_valid_image_url($url)) {
            return [false, '', 'Remote image URL is invalid.'];
        }
        return cloudinary_execute_upload_request($url, $public_id);
    }
}

if (!function_exists('store_external_image_url')) {
    function store_external_image_url($url, &$error_message = '')
    { global $dbRepo;
    global $dbRepo;

        static $upload_cache = [];

        $url = normalize_external_image_url($url, true);
        if ($url === '' || !is_valid_image_url($url)) {
            return '';
        }

        if (!is_external_image_url($url) || is_cloudinary_url($url)) {
            return $url;
        }

        if (!cloudinary_is_enabled()) {
            if (cloudinary_is_strict_mode()) {
                $error_message .= 'Cloudinary strict mode is enabled. Configure Cloudinary credentials first.<br>';
                return '';
            }
            return $url;
        }

        if (isset($upload_cache[$url])) {
            return $upload_cache[$url];
        }

        list($ok, $cloud_url, $cloud_error) = cloudinary_upload_remote_image($url);
        if ($ok && $cloud_url !== '') {
            $upload_cache[$url] = $cloud_url;
            return $cloud_url;
        }

        if (cloudinary_is_strict_mode()) {
            $error_message .= 'Cloudinary upload failed for image URL.<br>';
            $upload_cache[$url] = '';
            return '';
        }

        if ($cloud_error !== '') {
            error_log('Cloudinary remote upload failed: ' . $cloud_error);
        }
        $upload_cache[$url] = $url;
        return $url;
    }
}

if (!function_exists('store_uploaded_image_file')) {
    function store_uploaded_image_file($file_tmp, $file_name, $target_basename, $upload_dir, &$error_message, $allowed_ext = null)
    { global $dbRepo;
    global $dbRepo;

        if ($allowed_ext === null) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        }

        $file_name = trim((string)$file_name);
        $file_tmp = trim((string)$file_tmp);
        $target_basename = trim((string)$target_basename);
        if ($target_basename === '') {
            $target_basename = 'image-' . time();
        }

        if ($file_name === '') {
            $error_message .= 'You must select a photo.<br>';
            return [false, ''];
        }

        $ext = strtolower((string)pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $error_message .= 'You must upload: ' . implode(', ', $allowed_ext) . '.<br>';
            return [false, ''];
        }

        if ($file_tmp === '' || !is_uploaded_file($file_tmp)) {
            $error_message .= 'Upload failed. Please try again.<br>';
            return [false, ''];
        }

        // File size limit: 10 MB
        $upload_max_size = 10 * 1024 * 1024;
        if (filesize($file_tmp) > $upload_max_size) {
            $error_message .= 'File size exceeds the maximum limit of 10 MB.<br>';
            return [false, ''];
        }

        // MIME type validation against allowed image types
        $allowed_mime = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
        if (!in_array($detected_mime, $allowed_mime, true)) {
            $error_message .= 'Invalid file type detected. Only image files are allowed.<br>';
            return [false, ''];
        }

        // Verify it's a valid image (dimensions readable)
        $image_info = @getimagesize($file_tmp);
        if ($image_info === false) {
            $error_message .= 'Uploaded file is not a valid image.<br>';
            return [false, ''];
        }

        if (cloudinary_is_enabled()) {
            $public_id = preg_replace('/[^a-zA-Z0-9_\\-\\/]+/', '-', $target_basename);
            $public_id = trim((string)$public_id, '-');
            list($ok_cloud, $cloud_url, $cloud_error) = cloudinary_upload_local_image($file_tmp, $file_name, $public_id);
            if ($ok_cloud && $cloud_url !== '') {
                return [true, $cloud_url];
            }

            if (cloudinary_is_strict_mode()) {
                $error_message .= 'Cloudinary upload failed. Please try again.<br>';
                return [false, ''];
            }

            if ($cloud_error !== '') {
                error_log('Cloudinary local upload failed: ' . $cloud_error);
            }
        } elseif (cloudinary_is_strict_mode()) {
            $error_message .= 'Cloudinary strict mode is enabled. Configure Cloudinary credentials first.<br>';
            return [false, ''];
        }

        $final_name = $target_basename . '.' . $ext;
        $destination = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR . $final_name;
        if (!move_uploaded_file($file_tmp, $destination)) {
            $error_message .= 'Upload failed. Please try again.<br>';
            return [false, ''];
        }

        return [true, $final_name];
    }
}

if (!function_exists('get_front_image_url')) {
    function get_front_image_url($value)
    { global $dbRepo;
    global $dbRepo;

        $value = normalize_image_value($value);
        if ($value === '') {
            return '';
        }

        if (is_external_image_url($value)) {
            return resolve_external_image_url($value, false);
        }

        return 'assets/uploads/' . ltrim($value, '/\\');
    }
}

if (!function_exists('front_get_settings')) {
    function front_get_settings(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        static $cache = [];

        $cache_key = spl_object_hash($pdo);
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $tenant_id = function_exists('get_current_tenant_id') ? get_current_tenant_id() : 1;
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE tenant_id = ? LIMIT 1");
        $statement->execute([$tenant_id]);
        $settings = $statement->fetch(PDO::FETCH_ASSOC);

        $cache[$cache_key] = is_array($settings) ? $settings : [];
        return $cache[$cache_key];
    }
}

if (!function_exists('admin_add_column_if_missing')) {
    function admin_add_column_if_missing(PDO $pdo, $table, $column, $definition)
    { global $dbRepo;
    global $dbRepo;

        try {
            $quoted_column = $pdo->quote($column);
            $statement = $dbRepo->query("SHOW COLUMNS FROM {$table} LIKE {$quoted_column}");
            $exists = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$exists) {
                $dbRepo->executeCommand("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (Exception $exception) {
            error_log('Failed to ensure column ' . $table . '.' . $column . ': ' . $exception->getMessage());
        }
    }
}

if (!function_exists('admin_ensure_ecotrack_setting_columns')) {
    function admin_ensure_ecotrack_setting_columns(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/ecotrack_setting_columns.lock';
        if (file_exists($lock_file)) {
            return;
        }

        admin_add_column_if_missing($pdo, 'tbl_settings', 'ecotrack_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        admin_add_column_if_missing($pdo, 'tbl_settings', 'ecotrack_api_token', 'TEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_settings', 'ecotrack_base_url', "VARCHAR(255) NOT NULL DEFAULT ''");

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('ecotrack_default_settings')) {
    function ecotrack_default_settings() {        return [
            'ecotrack_enabled' => 0,
            'ecotrack_api_token' => '',
            'ecotrack_base_url' => ''
        ];
    }
}

if (!function_exists('ecotrack_normalize_base_url_value')) {
    function ecotrack_normalize_base_url_value($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        $value = preg_replace('#/(?:public/)?api/v1(?:/.*)?$#i', '', $value);
        $value = preg_replace('#/index\\.php(?:/.*)?$#i', '', $value);

        return rtrim((string) $value, '/');
    }
}

if (!function_exists('ecotrack_normalize_settings')) {
    function ecotrack_normalize_settings(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = array_merge(ecotrack_default_settings(), $settings);
        $settings['ecotrack_enabled'] = !empty($settings['ecotrack_enabled']) ? 1 : 0;
        $settings['ecotrack_api_token'] = trim((string) ($settings['ecotrack_api_token'] ?? ''));
        $settings['ecotrack_base_url'] = ecotrack_normalize_base_url_value($settings['ecotrack_base_url'] ?? '');
        return $settings;
    }
}

if (!function_exists('ecotrack_is_configured')) {
    function ecotrack_is_configured(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = ecotrack_normalize_settings($settings);
        return $settings['ecotrack_enabled'] === 1 && $settings['ecotrack_api_token'] !== '';
    }
}

if (!function_exists('ecotrack_build_headers')) {
    function ecotrack_build_headers(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = ecotrack_normalize_settings($settings);
        if ($settings['ecotrack_api_token'] === '') {
            return [];
        }

        return [
            'Authorization: Bearer ' . $settings['ecotrack_api_token'],
            'Accept: application/json'
        ];
    }
}

if (!function_exists('ecotrack_candidate_base_urls')) {
    function ecotrack_candidate_base_urls() {        return [
            'https://app.ecotrack.dz',
            'https://api.ecotrack.dz',
            'https://ecotrack.dz',
            'https://www.ecotrack.dz'
        ];
    }
}

if (!function_exists('ecotrack_json_decode')) {
    function ecotrack_json_decode($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('ecotrack_json_encode')) {
    function ecotrack_json_encode($value, $pretty = false)
    { global $dbRepo;
    global $dbRepo;

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (!empty($pretty)) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($value, $flags);
        return $encoded !== false ? $encoded : '';
    }
}

if (!function_exists('ecotrack_response_to_text')) {
    function ecotrack_response_to_text($response, $json = null)
    { global $dbRepo;
    global $dbRepo;

        if (is_array($json)) {
            $pretty = ecotrack_json_encode($json, true);
            if ($pretty !== '') {
                return $pretty;
            }
        }

        $decoded = ecotrack_json_decode($response);
        if (is_array($decoded)) {
            $pretty = ecotrack_json_encode($decoded, true);
            if ($pretty !== '') {
                return $pretty;
            }
        }

        return trim((string) $response);
    }
}

if (!function_exists('ecotrack_messages_to_array')) {
    function ecotrack_messages_to_array($value, $prefix = '')
    { global $dbRepo;
    global $dbRepo;

        $messages = [];

        if (is_array($value)) {
            $is_list = array_keys($value) === range(0, count($value) - 1);
            foreach ($value as $key => $item) {
                $next_prefix = $prefix;
                if (!$is_list && !in_array((string) $key, ['success', 'tracking'], true)) {
                    $next_prefix = $prefix !== '' ? $prefix . ': ' . $key : (string) $key;
                }
                $messages = array_merge($messages, ecotrack_messages_to_array($item, $next_prefix));
            }

            return $messages;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        if ($prefix !== '') {
            return [$prefix . ' - ' . $value];
        }

        return [$value];
    }
}

if (!function_exists('ecotrack_messages_to_text')) {
    function ecotrack_messages_to_text($value)
    { global $dbRepo;
    global $dbRepo;

        $messages = ecotrack_messages_to_array($value);
        $messages = array_values(array_unique(array_filter(array_map('trim', $messages))));
        return implode(PHP_EOL, $messages);
    }
}

if (!function_exists('ecotrack_find_first_value_by_keys')) {
    function ecotrack_find_first_value_by_keys($data, array $keys)
    { global $dbRepo;
    global $dbRepo;

        if (!is_array($data)) {
            return '';
        }

        foreach ($keys as $key) {
            if (isset($data[$key]) && !is_array($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $found = ecotrack_find_first_value_by_keys($value, $keys);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }
}

if (!function_exists('ecotrack_find_tracking_record')) {
    function ecotrack_find_tracking_record($data, $tracking)
    { global $dbRepo;
    global $dbRepo;

        $tracking = trim((string) $tracking);
        if ($tracking === '' || !is_array($data)) {
            return null;
        }

        if (isset($data[$tracking]) && is_array($data[$tracking])) {
            return $data[$tracking];
        }

        if (isset($data['tracking']) && trim((string) $data['tracking']) === $tracking) {
            return $data;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $found = ecotrack_find_tracking_record($value, $tracking);
            if (is_array($found)) {
                return $found;
            }
        }

        return null;
    }
}

if (!function_exists('ecotrack_extract_remote_status')) {
    function ecotrack_extract_remote_status($data, $tracking = '')
    { global $dbRepo;
    global $dbRepo;

        $status_keys = ['current_status', 'status', 'state', 'etat', 'last_status', 'lastState', 'status_label'];
        $record = ecotrack_find_tracking_record($data, $tracking);
        if (is_array($record)) {
            $record_status = ecotrack_find_first_value_by_keys($record, $status_keys);
            if ($record_status !== '') {
                return $record_status;
            }
        }

        return ecotrack_find_first_value_by_keys($data, $status_keys);
    }
}

if (!function_exists('ecotrack_extract_remote_note')) {
    function ecotrack_extract_remote_note($data, $tracking = '')
    { global $dbRepo;
    global $dbRepo;

        $note_keys = ['note', 'notes', 'comment', 'comments', 'content', 'message', 'reason', 'motif', 'observation', 'description'];
        $record = ecotrack_find_tracking_record($data, $tracking);
        if (is_array($record)) {
            $record_note = ecotrack_find_first_value_by_keys($record, $note_keys);
            if ($record_note !== '') {
                return $record_note;
            }
        }

        return ecotrack_find_first_value_by_keys($data, $note_keys);
    }
}

if (!function_exists('admin_ensure_telegram_order_status_columns')) {
    function admin_ensure_telegram_order_status_columns(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/telegram_order_status_columns.lock';
        if (file_exists($lock_file)) {
            return;
        }

        admin_add_column_if_missing($pdo, 'tbl_settings', 'telegram_order_status_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        admin_add_column_if_missing($pdo, 'tbl_settings', 'telegram_order_status_chat_id', "VARCHAR(255) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_settings', 'telegram_order_status_bot_token', "VARCHAR(255) NOT NULL DEFAULT ''");

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('admin_send_order_status_telegram')) {
    function admin_send_order_status_telegram(PDO $pdo, array $order, $old_status, $new_status, array $context = [])
    { global $dbRepo;
    global $dbRepo;

        $old_status = trim((string) $old_status);
        $new_status = trim((string) $new_status);
        if ($new_status === '' || strcasecmp($old_status, $new_status) === 0) {
            return ['skipped' => true, 'reason' => 'unchanged'];
        }

        try {
            $stmt = $dbRepo->query("
                SELECT telegram_order_status_enabled, telegram_order_status_bot_token, telegram_order_status_chat_id,
                       telegram_bot_token, telegram_chat_id,
                       telegram_enable_manager_notifications, telegram_enable_employee_notifications
                FROM tbl_settings WHERE id = 1 LIMIT 1
            ");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return ['skipped' => true, 'reason' => 'settings_unavailable'];
        }

        if (!$settings || empty($settings['telegram_order_status_enabled'])) {
            return ['skipped' => true, 'reason' => 'disabled'];
        }

        // Dedicated order-status bot/chat if configured (admin/settings.php), otherwise
        // fall back to the main order-notifications bot - same pattern as the
        // incomplete-order notifications already use.
        $botToken = trim((string) ($settings['telegram_order_status_bot_token'] ?? ''));
        $chatId   = trim((string) ($settings['telegram_order_status_chat_id'] ?? ''));
        if ($botToken === '') {
            $botToken = trim((string) ($settings['telegram_bot_token'] ?? ''));
        }
        if ($chatId === '') {
            $chatId = trim((string) ($settings['telegram_chat_id'] ?? ''));
        }

        require_once dirname(__DIR__, 2) . '/assets/telegram-notification.php';

        $orderData = [
            'order_id' => $order['id'] ?? '',
            'tracking' => $order['ecotrack_tracking'] ?? ($context['tracking'] ?? ''),
            'customer_name' => $order['customer_name'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? '',
            'old_status' => $old_status !== '' ? $old_status : '-',
            'new_status' => $new_status,
            'note' => $context['note'] ?? '',
        ];

        $pushed = false;

        if ($botToken !== '' && $chatId !== '') {
            $telegram = new TelegramNotification($botToken, $chatId);
            if ($telegram->sendOrderStatusNotification($orderData)) {
                $pushed = true;
            }
        }

        // Also notify managers/employees who personally linked their own Telegram.
        // If a genuinely separate "order status" bot is configured, personal chats
        // must come from that bot's own link table (SecondaryBotLinkService) -
        // the main bot's tbl_user/tbl_employee chat_id was never started with that
        // bot and can't be messaged through it. Only when order-status falls back
        // to the main bot do the existing self-linked chat_ids apply.
        require_once __DIR__ . '/../telegram/Services/SecondaryBotLinkService.php';
        $usesDedicatedBot = SecondaryBotLinkService::hasDedicatedBot($pdo, 'order_status');

        if ($usesDedicatedBot) {
            if (!empty($settings['telegram_enable_manager_notifications'])) {
                $stmt = $dbRepo->query("SELECT id FROM tbl_user WHERE status = 1");
                while ($mgr = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $chat = SecondaryBotLinkService::getLinkedChatId($pdo, 'manager', (int) $mgr['id'], 'order_status');
                    if ($chat !== '') {
                        $notifier = new TelegramNotification($botToken, $chat);
                        if ($notifier->sendOrderStatusNotification($orderData)) {
                            $pushed = true;
                        }
                    }
                }
            }
            if (!empty($settings['telegram_enable_employee_notifications']) && !empty($order['employee_id'])) {
                $chat = SecondaryBotLinkService::getLinkedChatId($pdo, 'employee', (int) $order['employee_id'], 'order_status');
                if ($chat !== '') {
                    $notifier = new TelegramNotification($botToken, $chat);
                    if ($notifier->sendOrderStatusNotification($orderData)) {
                        $pushed = true;
                    }
                }
            }
        } elseif ($botToken !== '') {
            if (!empty($settings['telegram_enable_manager_notifications'])) {
                $stmt = $dbRepo->query("SELECT telegram_chat_id FROM tbl_user WHERE telegram_is_linked = 1 AND status = 1");
                while ($mgr = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($mgr['telegram_chat_id'])) {
                        $notifier = new TelegramNotification($botToken, $mgr['telegram_chat_id']);
                        if ($notifier->sendOrderStatusNotification($orderData)) {
                            $pushed = true;
                        }
                    }
                }
            }

            if (!empty($settings['telegram_enable_employee_notifications']) && !empty($order['employee_id'])) {
                $stmt = $dbRepo->prepare("SELECT telegram_chat_id FROM tbl_employee WHERE id = ? AND telegram_is_linked = 1 AND is_active = 1");
                $stmt->execute([$order['employee_id']]);
                $emp = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($emp['telegram_chat_id'])) {
                    $notifier = new TelegramNotification($botToken, $emp['telegram_chat_id']);
                    if ($notifier->sendOrderStatusNotification($orderData)) {
                        $pushed = true;
                    }
                }
            }
        }

        return $pushed ? ['success' => true] : ['skipped' => true, 'reason' => 'no_recipients'];
    }
}

if (!function_exists('ecotrack_filter_query_params')) {
    function ecotrack_filter_query_params(array $params)
    { global $dbRepo;
    global $dbRepo;

        $filtered = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                if (!empty($value)) {
                    $filtered[$key] = $value;
                }
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' && $value !== '0' && $value !== 0) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}

if (!function_exists('ecotrack_build_query_string')) {
    function ecotrack_build_query_string(array $params)
    { global $dbRepo;
    global $dbRepo;

        $pairs = [];
        foreach (ecotrack_filter_query_params($params) as $key => $value) {
            $key = (string) $key;
            if (is_array($value)) {
                $array_key = substr($key, -2) === '[]' ? $key : $key . '[]';
                foreach ($value as $item) {
                    if ($item === null) {
                        continue;
                    }
                    if (is_string($item)) {
                        $item = trim($item);
                    }
                    if ($item === '' && $item !== '0' && $item !== 0) {
                        continue;
                    }
                    $pairs[] = rawurlencode($array_key) . '=' . rawurlencode((string) $item);
                }
                continue;
            }

            $pairs[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $pairs);
    }
}

if (!function_exists('ecotrack_curl_execute')) {
    function ecotrack_curl_execute($ch)
    { global $dbRepo;
    global $dbRepo;

        $response = curl_exec($ch);
        $error = trim((string) curl_error($ch));
        $errno = (int) curl_errno($ch);

        if ((!is_string($response) || $response === '') && (stripos($error, 'SSL certificate') !== false || in_array($errno, [60, 77], true))) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            $error = trim((string) curl_error($ch));
        }

        return [is_string($response) ? $response : '', $error];
    }
}

if (!function_exists('ecotrack_update_base_url')) {
    function ecotrack_update_base_url(PDO $pdo, $base_url)
    { global $dbRepo;
    global $dbRepo;

        $base_url = ecotrack_normalize_base_url_value($base_url);
        if ($base_url === '') {
            return;
        }

        try {
            admin_ensure_ecotrack_setting_columns($pdo);
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET ecotrack_base_url=? WHERE id=1");
            $statement->execute([$base_url]);
        } catch (Exception $exception) {
            error_log('Unable to save ECOTRACK base URL: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('ecotrack_resolve_base_url')) {
    function ecotrack_resolve_base_url(PDO $pdo, array $settings)
    { global $dbRepo;

        $settings = ecotrack_normalize_settings($settings);
        $token = $settings['ecotrack_api_token'];
        if ($token === '') {
            return [false, '', 'ECOTRACK token is empty'];
        }

        if ($settings['ecotrack_base_url'] !== '') {
            return [true, $settings['ecotrack_base_url'], ''];
        }

        foreach (ecotrack_candidate_base_urls() as $candidate) {
            $url = rtrim($candidate, '/') . '/api/v1/validate/token?api_token=' . rawurlencode($token);
            $headers = ['Accept: application/json'];
            $response = '';
            $status_code = 0;

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                if ($ch === false) {
                    continue;
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                list($response) = ecotrack_curl_execute($ch);
                $status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            } else {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 10,
                        'ignore_errors' => true,
                        'header' => implode("\r\n", $headers)
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                $response = @file_get_contents($url, false, $context);
                if (!is_string($response)) {
                    $response = '';
                }
                if (!empty($http_response_header) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $matches)) {
                    $status_code = (int) $matches[1];
                }
            }

            if ($status_code < 200 || $status_code >= 500) {
                continue;
            }

            $payload = ecotrack_json_decode($response);
            if (!$payload) {
                continue;
            }

            $message = strtoupper(trim((string) ($payload['message'] ?? '')));
            if (in_array($message, ['VALID_TOKEN', 'INVALID_TOKEN', 'TOKEN_NOT_ALLOWED'], true)) {
                ecotrack_update_base_url($pdo, $candidate);
                return [true, rtrim($candidate, '/'), ''];
            }
        }

        return [false, '', 'تعذر اكتشاف رابط ECOTRACK تلقائيًا من التوكن الحالي.'];
    }
}

if (!function_exists('ecotrack_api_request')) {
    function ecotrack_api_request(PDO $pdo, array $settings, $method, $path, array $query = [], $body = null, $auth_mode = 'bearer')
    { global $dbRepo;

        $settings = ecotrack_normalize_settings($settings);
        list($base_ok, $base_url, $base_error) = ecotrack_resolve_base_url($pdo, $settings);
        if (!$base_ok || $base_url === '') {
            if (trim((string) ($settings['ecotrack_base_url'] ?? '')) === '') {
                $base_error = 'تعذر اكتشاف رابط ECOTRACK تلقائيًا من التوكن الحالي. أدخل Base URL يدويًا في الإعدادات، وهو نفس العنوان الذي يمثّل {{url}} في توثيق ECOTRACK.';
            }
            return [
                'success' => false,
                'status_code' => 0,
                'response' => '',
                'json' => null,
                'error' => $base_error !== '' ? $base_error : 'ECOTRACK base URL is missing',
                'url' => ''
            ];
        }

        $method = strtoupper(trim((string) $method));
        if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
            $method = 'GET';
        }

        $url = $base_url . '/' . ltrim((string) $path, '/');
        if (!empty($query)) {
            $query_string = ecotrack_build_query_string($query);
            if ($query_string !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $query_string;
            }
        }

        $headers = ['Accept: application/json'];
        if ($auth_mode === 'bearer' && $settings['ecotrack_api_token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $settings['ecotrack_api_token'];
        }

        $payload = '';
        if ($body !== null) {
            $payload = is_string($body)
                ? $body
                : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $response = '';
        $status_code = 0;
        $error = '';
        $elapsed_ms = 0;

        if (function_exists('curl_init')) {
            $attempts = 2;
            $connect_timeout = 15;
            $timeout = 60;

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $ch = curl_init($url);
                if ($ch === false) {
                    return ['success' => false, 'status_code' => 0, 'response' => '', 'json' => null, 'error' => 'Unable to initialize cURL', 'url' => $url, 'elapsed_ms' => 0];
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                if ($method !== 'GET' && $payload !== '') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                }
                $t_start = microtime(true);
                list($response, $error) = ecotrack_curl_execute($ch);
                $elapsed_ms = round((microtime(true) - $t_start) * 1000);
                $status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $timeout_hit = $error !== '' && stripos($error, 'timeout') !== false;
                $no_response = $status_code === 0 && trim($response) === '';
                if (!$timeout_hit && !$no_response) {
                    break;
                }

                if ($attempt < $attempts) {
                    $connect_timeout = 30;
                    $timeout = 90;
                    usleep(250000);
                }
            }
        } else {
            $options = [
                'http' => [
                    'method' => $method,
                    'timeout' => 60,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers)
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            if ($method !== 'GET' && $payload !== '') {
                $options['http']['content'] = $payload;
            }
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            if (!is_string($response)) {
                $response = '';
            }
            if (!empty($http_response_header) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $matches)) {
                $status_code = (int) $matches[1];
            }
        }

        $json = ecotrack_json_decode($response);
        $success = $status_code >= 200 && $status_code < 300;
        if (!$success && $error === '') {
            $error = 'ECOTRACK returned HTTP ' . ($status_code > 0 ? $status_code : 0);
        }

        return [
            'success' => $success,
            'status_code' => $status_code,
            'response' => $response,
            'json' => $json,
            'error' => $error,
            'url' => $url,
            'elapsed_ms' => $elapsed_ms
        ];
    }
}

if (!function_exists('ecotrack_normalize_compare_text')) {
    function ecotrack_normalize_compare_text($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('admin_fix_broken_arabic_text')) {
            $value = admin_fix_broken_arabic_text($value);
        }

        if (preg_match('/\p{Arabic}/u', $value)) {
            $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value);
            $value = strtr($value, [
                'أ' => 'ا',
                'إ' => 'ا',
                'آ' => 'ا',
                'ٱ' => 'ا',
                'ى' => 'ي',
                'ئ' => 'ي',
                'ؤ' => 'و',
                'ة' => 'ه',
                'گ' => 'ك',
                'ڨ' => 'ق',
                'ڤ' => 'ف',
                'پ' => 'ب'
            ]);
            if (function_exists('mb_strtolower')) {
                $value = mb_strtolower($value, 'UTF-8');
            }
            $value = preg_replace('/[^\p{Arabic}\p{N}]+/u', '', $value);
            return trim((string) $value);
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && trim($converted) !== '') {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return (string) $value;
    }
}

if (!function_exists('ecotrack_transliterate_arabic_place_name')) {
    function ecotrack_transliterate_arabic_place_name($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('admin_fix_broken_arabic_text')) {
            $value = admin_fix_broken_arabic_text($value);
        }

        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value);
        $value = strtr($value, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ى' => 'ي',
            'ئ' => 'ي',
            'ؤ' => 'و',
            'ة' => 'ه',
            'گ' => 'ك',
            'ڨ' => 'ق',
            'ڤ' => 'ف',
            'پ' => 'ب'
        ]);

        $word_map = [
            'ام' => 'oum',
            'عين' => 'ain',
            'بير' => 'bir',
            'بئر' => 'bir',
            'وادي' => 'oued',
            'واديي' => 'oued',
            'اولاد' => 'ouled',
            'سيدي' => 'sidi',
            'ابن' => 'ben',
            'بن' => 'ben',
            'صفصاف' => 'safsaf',
            'راس' => 'ras',
            'تل' => 'tel',
            "\u{062C}\u{0632}\u{0627}\u{064A}\u{0631}" => 'alger',
            "\u{0648}\u{0633}\u{0637}\u{064A}" => 'centre',
            "\u{0645}\u{062F}\u{0646}\u{064A}\u{0647}" => 'madania',
            "\u{0645}\u{0631}\u{0627}\u{062F}\u{064A}\u{0647}" => 'mouradia',
            "\u{0642}\u{0635}\u{0628}\u{0647}" => 'casbah',
            "\u{0642}\u{0628}\u{0647}" => 'kouba',
            "\u{062F}\u{0627}\u{064A}" => 'dey',
        ];

        $char_map = [
            'ا' => 'a',
            'ب' => 'b',
            'ت' => 't',
            'ث' => 'th',
            'ج' => 'j',
            'ح' => 'h',
            'خ' => 'kh',
            'د' => 'd',
            'ذ' => 'dh',
            'ر' => 'r',
            'ز' => 'z',
            'س' => 's',
            'ش' => 'ch',
            'ص' => 's',
            'ض' => 'd',
            'ط' => 't',
            'ظ' => 'z',
            'ع' => 'a',
            'غ' => 'gh',
            'ف' => 'f',
            'ق' => 'q',
            'ك' => 'k',
            'ل' => 'l',
            'م' => 'm',
            'ن' => 'n',
            'ه' => 'h',
            'و' => 'ou',
            'ي' => 'i',
            'ء' => ''
        ];

        $parts = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        $latin_parts = [];

        foreach ($parts as $part) {
            $article = false;
            if (function_exists('mb_substr') && function_exists('mb_strlen')) {
                if (mb_substr($part, 0, 2, 'UTF-8') === 'ال' && mb_strlen($part, 'UTF-8') > 2) {
                    $article = true;
                    $part = (string) mb_substr($part, 2, null, 'UTF-8');
                }
            } elseif (strpos($part, 'ال') === 0 && strlen($part) > 4) {
                $article = true;
                $part = substr($part, 4);
            }

            if (isset($word_map[$part])) {
                $latin = $word_map[$part];
            } else {
                $latin = strtr($part, $char_map);
            }

            $latin = strtolower(trim((string) $latin));
            if ($latin === '') {
                continue;
            }

            if ($article) {
                $latin = 'el' . $latin;
            }

            $latin_parts[] = $latin;
        }

        return trim(implode(' ', $latin_parts));
    }
}

if (!function_exists('ecotrack_place_name_match_key')) {
    function ecotrack_place_name_match_key($value)
    { global $dbRepo;
    global $dbRepo;

        $value = ecotrack_normalize_compare_text($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[aeiouy]+/', '', $value);
        $value = preg_replace('/(.)\\1+/', '$1', $value);
        return trim((string) $value);
    }
}

if (!function_exists('ecotrack_place_name_variants')) {
    function ecotrack_place_name_variants($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $variants = [];
        $normalized = ecotrack_normalize_compare_text($value);
        if ($normalized !== '') {
            $variants[] = $normalized;
        }

        if (preg_match('/\p{Arabic}/u', $value)) {
            $latin = ecotrack_normalize_compare_text(ecotrack_transliterate_arabic_place_name($value));
            if ($latin !== '') {
                $variants[] = $latin;
            }
        }

        $expanded = [];
        foreach (array_unique($variants) as $variant) {
            $variant = trim((string) $variant);
            if ($variant === '') {
                continue;
            }

            $expanded[] = $variant;

            if (strpos($variant, 'al') === 0 && strlen($variant) > 2) {
                $expanded[] = 'el' . substr($variant, 2);
                $expanded[] = substr($variant, 2);
            }

            if (strpos($variant, 'el') === 0 && strlen($variant) > 2) {
                $expanded[] = 'al' . substr($variant, 2);
                $expanded[] = substr($variant, 2);
            }

            $expanded[] = str_replace('ou', 'u', $variant);
            $expanded[] = str_replace('q', 'k', $variant);
            $expanded[] = str_replace('ch', 'sh', $variant);
            $expanded[] = str_replace('sh', 'ch', $variant);
        }

        $expanded = array_values(array_filter(array_unique(array_map('trim', $expanded))));
        return $expanded;
    }
}

if (!function_exists('ecotrack_communes_cache_path')) {
    function ecotrack_communes_cache_path($wilaya_code)
    { global $dbRepo;
    global $dbRepo;

        $wilaya_code = max(0, (int) $wilaya_code);
        if ($wilaya_code <= 0) {
            return '';
        }

        $cache_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0775, true);
        }

        return $cache_dir . DIRECTORY_SEPARATOR . 'ecotrack-communes-' . $wilaya_code . '.json';
    }
}

if (!function_exists('ecotrack_read_cached_communes')) {
    function ecotrack_read_cached_communes($wilaya_code)
    { global $dbRepo;
    global $dbRepo;

        $path = ecotrack_communes_cache_path($wilaya_code);
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        $json = json_decode($content, true);
        return is_array($json) ? $json : null;
    }
}

if (!function_exists('ecotrack_write_cached_communes')) {
    function ecotrack_write_cached_communes($wilaya_code, array $rows)
    { global $dbRepo;
    global $dbRepo;

        $path = ecotrack_communes_cache_path($wilaya_code);
        if ($path === '') {
            return;
        }

        $payload = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($payload) || trim($payload) === '') {
            return;
        }

        @file_put_contents($path, $payload);
    }
}

if (!function_exists('admin_ensure_order_ecotrack_columns')) {
    function admin_ensure_order_ecotrack_columns(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/order_ecotrack_columns.lock';
        if (file_exists($lock_file)) {
            return;
        }

        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_status', "VARCHAR(40) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_reference', "VARCHAR(120) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_tracking', "VARCHAR(120) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_remote_status', "VARCHAR(120) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_remote_time', 'DATETIME NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_error', 'TEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_payload', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_response', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_order_info', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_updates', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_tracking_info', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_last_trackings_info', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_sent_at', 'DATETIME NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_previous_order_status', "VARCHAR(40) NOT NULL DEFAULT ''");

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('admin_ecotrack_request_success')) {
    function admin_ecotrack_request_success(array $request)
    { global $dbRepo;
    global $dbRepo;

        $request_ok = !empty($request['success']);
        if ($request_ok && is_array($request['json']) && array_key_exists('success', $request['json'])) {
            $request_ok = !empty($request['json']['success']);
        }

        return $request_ok;
    }
}

if (!function_exists('admin_ecotrack_request_error_text')) {
    function admin_ecotrack_request_error_text(array $request, $fallback = '')
    { global $dbRepo;
    global $dbRepo;

        $error_text = '';

        if (!empty($request['json']['errors'])) {
            $error_text = ecotrack_messages_to_text($request['json']['errors']);
        }
        if ($error_text === '' && !empty($request['json']['message'])) {
            $error_text = trim((string) $request['json']['message']);
        }
        if ($error_text === '' && !empty($request['error'])) {
            $error_text = trim((string) $request['error']);
        }
        if ($error_text === '') {
            $error_text = trim((string) $fallback);
        }

        return $error_text;
    }
}

if (!function_exists('admin_ecotrack_mark_order_sent_locally')) {
    function admin_ecotrack_mark_order_sent_locally(PDO $pdo, array $order, $changed_by = null)
    { global $dbRepo;
    global $dbRepo;

        $order_id = (int) ($order['id'] ?? 0);
        if ($order_id <= 0) {
            return false;
        }

        $current_status = admin_normalize_order_status($order['order_status'] ?? '');
        $target_status = 'Completed';
        if ($current_status === $target_status) {
            $statement = $dbRepo->prepare("UPDATE tbl_order SET ecotrack_previous_order_status = CASE WHEN TRIM(ecotrack_previous_order_status) = '' THEN ? ELSE ecotrack_previous_order_status END WHERE id = ? LIMIT 1");
            $statement->execute([$current_status, $order_id]);
            return false;
        }

        $statement = $dbRepo->prepare('UPDATE tbl_order SET order_status = ?, ecotrack_previous_order_status = ? WHERE id = ? LIMIT 1');
        $statement->execute([$target_status, $current_status, $order_id]);

        admin_log_order_status_change(
            $pdo,
            $order_id,
            $current_status,
            $target_status,
            'تم إرسال الطلب إلى ECOTRACK واعتماده تلقائيًا داخل المتجر.',
            $changed_by
        );

        return true;
    }
}

if (!function_exists('admin_ecotrack_restore_local_order_status')) {
    function admin_ecotrack_restore_local_order_status(PDO $pdo, array $order, $changed_by = null)
    { global $dbRepo;
    global $dbRepo;

        $order_id = (int) ($order['id'] ?? 0);
        if ($order_id <= 0) {
            return '';
        }

        $current_status = admin_normalize_order_status($order['order_status'] ?? '');
        $restore_status = trim((string) ($order['ecotrack_previous_order_status'] ?? ''));
        $restore_status = $restore_status !== '' ? admin_normalize_order_status($restore_status) : 'Pending';

        $statement = $dbRepo->prepare('UPDATE tbl_order SET order_status = ?, ecotrack_previous_order_status = ? WHERE id = ? LIMIT 1');
        $statement->execute([$restore_status, '', $order_id]);

        if ($current_status !== $restore_status) {
            admin_log_order_status_change(
                $pdo,
                $order_id,
                $current_status,
                $restore_status,
                'تم حذف الطلب من ECOTRACK وإرجاعه إلى حالته المحلية السابقة.',
                $changed_by
            );
        }

        return $restore_status;
    }
}

if (!function_exists('admin_ecotrack_save_order_state')) {
    function admin_ecotrack_save_order_state(PDO $pdo, $order_id, array $state, $touch_sent_at = false)
    { global $dbRepo;
    global $dbRepo;

        $sql = "
            UPDATE tbl_order
            SET ecotrack_reference = ?,
                ecotrack_tracking = ?,
                ecotrack_status = ?,
                ecotrack_remote_status = ?,
                ecotrack_last_error = ?,
                ecotrack_last_payload = ?,
                ecotrack_last_response = ?
        ";

        $params = [
            (string) ($state['reference'] ?? ''),
            (string) ($state['tracking'] ?? ''),
            (string) ($state['status'] ?? ''),
            (string) ($state['remote_status'] ?? ''),
            $state['last_error'] ?? '',
            $state['last_payload'] ?? '',
            $state['last_response'] ?? ''
        ];

        if (array_key_exists('last_updates', $state)) {
            $sql .= ", ecotrack_last_updates = ?";
            $params[] = $state['last_updates'];
        }

        if (array_key_exists('last_order_info', $state)) {
            $sql .= ", ecotrack_last_order_info = ?";
            $params[] = $state['last_order_info'];
        }

        if (array_key_exists('last_tracking_info', $state)) {
            $sql .= ", ecotrack_last_tracking_info = ?";
            $params[] = $state['last_tracking_info'];
        }

        if (array_key_exists('last_trackings_info', $state)) {
            $sql .= ", ecotrack_last_trackings_info = ?";
            $params[] = $state['last_trackings_info'];
        }

        if (array_key_exists('sent_at', $state)) {
            $sql .= ", ecotrack_sent_at = ?";
            $params[] = $state['sent_at'];
        } elseif ($touch_sent_at) {
            $sql .= ", ecotrack_sent_at = NOW()";
        }

        $sql .= " WHERE id = ? LIMIT 1";
        $params[] = (int) $order_id;

        $statement = $dbRepo->prepare($sql);
        $statement->execute($params);
    }
}

if (!function_exists('admin_ecotrack_auto_dispatch')) {
    /**
     * Programmatically create the delivery order at ECOTRACK for a given order,
     * reusing the exact same request/response handling as the manual "send to
     * ECOTRACK" button. Called automatically when an order is confirmed.
     *
     * Safe / idempotent:
     *  - skips silently if ECOTRACK isn't configured;
     *  - skips if the order was already sent (has a tracking number);
     *  - never throws - returns a status array so the caller (status change)
     *    is never rolled back by a delivery-side problem.
     *
     * @return array{sent?:bool, skipped?:bool, reason?:string, tracking?:string, error?:string}
     */
    function admin_ecotrack_auto_dispatch(PDO $pdo, int $order_id, $changed_by = null): array
    { global $dbRepo;
        if ($order_id <= 0) {
            return ['skipped' => true, 'reason' => 'invalid_order'];
        }

        try {
            $settings = ecotrack_normalize_settings(front_get_settings($pdo));
            if (!ecotrack_is_configured($settings)) {
                return ['skipped' => true, 'reason' => 'not_configured'];
            }

            $stmt = $dbRepo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return ['skipped' => true, 'reason' => 'order_not_found'];
            }

            // Already dispatched? Don't create a duplicate shipment.
            if (trim((string) ($order['ecotrack_tracking'] ?? '')) !== ''
                || trim((string) ($order['tracking_number'] ?? '')) !== '') {
                return ['skipped' => true, 'reason' => 'already_sent'];
            }

            $reference = ecotrack_build_order_reference($order);
            $request_context = ecotrack_create_order_request_context($pdo, $settings, $order);
            $request_body = $request_context['payload'];
            $prepared_order = $request_context['order'];
            $request_entry = (array) ($request_body['orders']['0'] ?? []);
            $request_wilaya_code = trim((string) ($request_entry['code_wilaya'] ?? ''));
            $request_commune_name = trim((string) ($request_entry['commune'] ?? ''));
            $request_payload_text = ecotrack_json_encode($request_body, true);

            if ($request_wilaya_code === '' || $request_commune_name === '') {
                admin_ecotrack_save_order_state($pdo, $order_id, [
                    'reference' => $reference,
                    'tracking' => '',
                    'status' => 'error',
                    'remote_status' => '',
                    'last_error' => 'الرفع التلقائي: بيانات الولاية/البلدية غير مكتملة.',
                    'last_payload' => $request_payload_text,
                    'last_response' => ''
                ], false);
                return ['sent' => false, 'reason' => 'incomplete_address'];
            }

            $request = ecotrack_api_request($pdo, $settings, 'POST', '/api/v1/create/orders', [], $request_body, 'bearer');
            $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

            $result_entry = [];
            if (!empty($request['json']['results'][$reference]) && is_array($request['json']['results'][$reference])) {
                $result_entry = $request['json']['results'][$reference];
            }

            if (!empty($result_entry['success']) && !empty($result_entry['tracking'])) {
                $tracking = trim((string) $result_entry['tracking']);
                $remote_status = trim((string) ($result_entry['status'] ?? ''));

                if (($prepared_order['delivery_type'] ?? '') !== ($order['delivery_type'] ?? '')) {
                    $dbRepo->prepare("UPDATE tbl_order SET delivery_type = ? WHERE id = ? LIMIT 1")
                        ->execute([(string) $prepared_order['delivery_type'], $order_id]);
                }
                admin_ecotrack_save_order_state($pdo, $order_id, [
                    'reference' => $reference,
                    'tracking' => $tracking,
                    'status' => 'sent',
                    'remote_status' => $remote_status,
                    'last_error' => '',
                    'last_payload' => $request_payload_text,
                    'last_response' => $response_text
                ], true);

                return ['sent' => true, 'tracking' => $tracking];
            }

            $result_errors = $result_entry;
            unset($result_errors['success'], $result_errors['tracking']);
            $error_text = ecotrack_messages_to_text($result_errors);
            if ($error_text === '') {
                $error_text = trim((string) ($request['json']['message'] ?? ($request['error'] ?? 'تعذر إرسال الطلب إلى ECOTRACK.')));
            }

            admin_ecotrack_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => '',
                'status' => 'error',
                'remote_status' => '',
                'last_error' => $error_text,
                'last_payload' => $request_payload_text,
                'last_response' => $response_text
            ], false);

            return ['sent' => false, 'error' => $error_text];
        } catch (\Throwable $e) {
            error_log('admin_ecotrack_auto_dispatch failed for order ' . $order_id . ': ' . $e->getMessage());
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('ecotrack_status_meta')) {
    function ecotrack_status_meta($status)
    { global $dbRepo;
    global $dbRepo;

        $status = strtolower(trim((string) $status));

        switch ($status) {
            case 'synced':
                return ['label' => 'تمت المزامنة', 'class' => 'label label-info'];
            case 'shipped':
                return ['label' => 'تم تأكيد الشحن', 'class' => 'label label-primary'];
            case 'sent':
                return ['label' => 'أرسل إلى ECOTRACK', 'class' => 'label label-success'];
            case 'error':
                return ['label' => 'فشل الإرسال', 'class' => 'label label-danger'];
            case 'pending':
                return ['label' => 'جاهز للإرسال', 'class' => 'label label-warning'];
            default:
                return ['label' => 'غير مربوط بعد', 'class' => 'label label-default'];
        }
    }
}

if (!function_exists('ecotrack_algeria_wilaya_code')) {
    function ecotrack_algeria_wilaya_code($wilaya)
    { global $dbRepo;
    global $dbRepo;

        static $map = null;

        if ($map === null) {
            $wilayas = [
                1 => ['أدرار', 'adrar'],
                2 => ['الشلف', 'chlef'],
                3 => ['الأغواط', 'laghouat'],
                4 => ['أم البواقي', 'oum el bouaghi', 'oum bouaghi'],
                5 => ['باتنة', 'batna'],
                6 => ['بجاية', 'بجايه', 'bejaia', 'béjaïa'],
                7 => ['بسكرة', 'biskra'],
                8 => ['بشار', 'bechar', 'béchar'],
                9 => ['البليدة', 'blida'],
                10 => ['البويرة', 'bouira'],
                11 => ['تمنراست', 'tamanrasset'],
                12 => ['تبسة', 'tebessa', 'tébessa'],
                13 => ['تلمسان', 'tlemcen'],
                14 => ['تيارت', 'tiaret'],
                15 => ['تيزي وزو', 'tizi ouzou'],
                16 => ['الجزائر', 'alger'],
                17 => ['الجلفة', 'djelfa'],
                18 => ['جيجل', 'jijel'],
                19 => ['سطيف', 'setif', 'sétif'],
                20 => ['سعيدة', 'saida', 'saïda'],
                21 => ['سكيكدة', 'skikda'],
                22 => ['سيدي بلعباس', 'sidi bel abbes', 'sidi bel abbès'],
                23 => ['عنابة', 'annaba'],
                24 => ['قالمة', 'guelma'],
                25 => ['قسنطينة', 'constantine'],
                26 => ['المدية', 'medea', 'médéa'],
                27 => ['مستغانم', 'mostaganem'],
                28 => ['المسيلة', 'msila', "m'sila"],
                29 => ['معسكر', 'mascara'],
                30 => ['ورقلة', 'ouargla'],
                31 => ['وهران', 'oran'],
                32 => ['البيض', 'el bayadh'],
                33 => ['إليزي', 'اليزي', 'illizi'],
                34 => ['برج بوعريريج', 'bordj bou arreridj'],
                35 => ['بومرداس', 'boumerdes', 'boumerdès'],
                36 => ['الطارف', 'el tarf'],
                37 => ['تندوف', 'tindouf'],
                38 => ['تيسمسيلت', 'tissemsilt'],
                39 => ['الوادي', 'el oued'],
                40 => ['خنشلة', 'khenchela'],
                41 => ['سوق أهراس', 'souk ahras'],
                42 => ['تيبازة', 'tipaza'],
                43 => ['ميلة', 'mila'],
                44 => ['عين الدفلى', 'ain defla', 'aïn defla'],
                45 => ['النعامة', 'naama', 'naâma'],
                46 => ['عين تموشنت', 'ain temouchent', 'aïn témouchent'],
                47 => ['غرداية', 'ghardaia', 'ghardaïa'],
                48 => ['غليزان', 'relizane'],
                49 => ['تيميمون', 'timimoun'],
                50 => ['برج باجي مختار', 'bordj badji mokhtar'],
                51 => ['أولاد جلال', 'ouled djellal'],
                52 => ['بني عباس', 'beni abbes'],
                53 => ['عين صالح', 'in salah'],
                54 => ['عين قزام', 'in guezzam'],
                55 => ['تقرت', 'touggourt'],
                56 => ['جانت', 'djanet'],
                57 => ['المغير', "el m'ghair"],
                58 => ['المنيعة', 'el meniaa']
            ];

            $map = [];
            foreach ($wilayas as $code => $aliases) {
                foreach ($aliases as $alias) {
                    $map[ecotrack_normalize_compare_text($alias)] = (int) $code;
                }
            }
        }

        $wilaya = ecotrack_normalize_compare_text($wilaya);
        return $map[$wilaya] ?? null;
    }
}

if (!function_exists('ecotrack_normalize_delivery_type')) {
    function ecotrack_normalize_delivery_type($value)
    { global $dbRepo;
    global $dbRepo;

        $value = function_exists('admin_normalize_delivery_type_text')
            ? admin_normalize_delivery_type_text($value)
            : trim((string) $value);
        $normalized = strtolower($value);
        $labels = function_exists('admin_delivery_type_labels')
            ? admin_delivery_type_labels()
            : [
                'home' => "\u{0645}\u{0646}\u{0632}\u{0644}",
                'office' => "\u{0645}\u{0643}\u{062A}\u{0628}",
                'free' => "\u{0645}\u{062C}\u{0627}\u{0646}\u{064A}",
            ];

        if ($value === $labels['home'] || $normalized === 'home') {
            return 'home';
        }
        if ($value === $labels['office'] || $normalized === 'office') {
            return 'office';
        }
        if ($value === $labels['free'] || $normalized === 'free') {
            return 'free';
        }

        return $value;

        $value = trim((string) $value);
        $normalized = strtolower($value);

        if ($value === 'منزل' || $normalized === 'home') {
            return 'home';
        }
        if ($value === 'مكتب' || $normalized === 'office') {
            return 'office';
        }
        if ($value === 'مجاني' || $normalized === 'free') {
            return 'free';
        }

        return $value;
    }
}

if (!function_exists('ecotrack_build_order_reference')) {
    function ecotrack_build_order_reference(array $order)
    { global $dbRepo;
    global $dbRepo;

        $existing_reference = trim((string) ($order['ecotrack_reference'] ?? ''));
        if ($existing_reference !== '') {
            return $existing_reference;
        }

        $order_id = (int) ($order['id'] ?? 0);
        if ($order_id > 0) {
            return 'ORD-' . str_pad((string) $order_id, 6, '0', STR_PAD_LEFT);
        }

        return 'ORD-' . date('YmdHis');
    }
}

if (!function_exists('ecotrack_lookup_order_option_label')) {
    function ecotrack_lookup_order_option_label(PDO $pdo, $table, $id_column, $name_column, $value)
    { global $dbRepo;
    global $dbRepo;

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (!ctype_digit($raw)) {
            return $raw;
        }

        $statement = $dbRepo->prepare("SELECT {$name_column} FROM {$table} WHERE {$id_column} = ? LIMIT 1");
        $statement->execute([(int) $raw]);
        $label = trim((string) $statement->fetchColumn());

        return $label;
    }
}

if (!function_exists('ecotrack_build_order_payload')) {
    function ecotrack_build_order_payload(array $order, array $commune_meta = [])
    { global $dbRepo;
    global $dbRepo;

        $phone_variants = sms_gateway_get_phone_variants((string) ($order['customer_phone'] ?? ''));
        $delivery_type = ecotrack_normalize_delivery_type($order['delivery_type'] ?? '');
        $commune_name = trim((string) ($commune_meta['nom'] ?? ($order['commune'] ?? '')));
        $code_postal = trim((string) ($commune_meta['code_postal'] ?? ''));
        $stop_desk = ($delivery_type === 'office') ? 1 : 0;
        $site_name = trim((string) ($order['site_name'] ?? $order['boutique'] ?? ''));
        if ($site_name === '') {
            $site_name = 'BoomStore';
        }
        $local_phone = trim((string) ($phone_variants['phone_local'] ?? ''));
        if ($local_phone === '') {
            $local_phone = trim((string) ($phone_variants['phone_digits'] ?? ''));
        }
        $local_phone = ltrim($local_phone, '+');
        $weight = trim((string) ($order['weight'] ?? ''));
        if ($weight !== '') {
            $weight_numeric = (float) str_replace(',', '.', $weight);
            if ($weight_numeric <= 0) {
                $weight = '';
            } else {
                $weight = rtrim(rtrim(number_format($weight_numeric, 2, '.', ''), '0'), '.');
            }
        }
        $quantity = max(1, (int) ($order['quantity'] ?? 1));
        $amount = (string) round((float) ($order['total_price'] ?? 0));
        $product_name = trim((string) ($order['product_name'] ?? ''));
        $detailed_address = trim((string) ($order['address'] ?? ''));
        $shipping_address = $detailed_address;
        if ($shipping_address === '') {
            $address_parts = [];
            foreach ([trim((string) ($order['commune'] ?? '')), trim((string) ($order['wilaya'] ?? ''))] as $address_part) {
                if ($address_part !== '') {
                    $address_parts[] = $address_part;
                }
            }
            $shipping_address = implode(' - ', $address_parts);
        }
        $notes = trim((string) ($order['ecotrack_notes'] ?? ''));
        $color_label = trim((string) ($order['ecotrack_color_label'] ?? ''));
        $size_label = trim((string) ($order['ecotrack_size_label'] ?? ''));
        $note_parts = [];
        if ($notes !== '') {
            $note_parts[] = $notes;
        }
        if ($color_label !== '') {
            $note_parts[] = 'اللون: ' . $color_label;
        }
        if ($size_label !== '') {
            $note_parts[] = 'المقاس: ' . $size_label;
        }
        if ($quantity > 1) {
            $note_parts[] = 'الكمية: ' . $quantity;
        }
        $notes = implode(' | ', $note_parts);
        if ($notes === '') {
            $notes = 'لا توجد';
        }

        $payload = [
            'reference' => ecotrack_build_order_reference($order),
            'nom_client' => trim((string) ($order['customer_name'] ?? '')),
            'telephone' => $local_phone,
            'telephone_2' => '',
            'adresse' => $shipping_address,
            'code_postal' => $code_postal,
            'commune' => $commune_name,
            'code_wilaya' => (string) (ecotrack_algeria_wilaya_code($order['wilaya'] ?? '') ?? ''),
            'montant' => $amount,
            'remarque' => $notes,
            'produit' => $product_name,
            'stock' => 0,
            'quantite' => (string) $quantity,
            'produit_a_recuperer' => '',
            'boutique' => $site_name,
            'type' => '1',
            'stop_desk' => $stop_desk,
            'gps_link' => trim((string) ($order['gps_link'] ?? ''))
        ];

        if ($weight !== '') {
            $payload['weight'] = $weight;
        }

        return $payload;
    }
}

if (!function_exists('ecotrack_build_order_payload_json')) {
    function ecotrack_build_order_payload_json(array $order)
    { global $dbRepo;
    global $dbRepo;

        return json_encode(
            ecotrack_build_order_payload($order),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}

if (!function_exists('ecotrack_find_commune_meta')) {
    function ecotrack_find_commune_meta(PDO $pdo, array $settings, $wilaya_code, $commune)
    { global $dbRepo;
    global $dbRepo;

        $wilaya_code = (int) $wilaya_code;
        $commune = trim((string) $commune);
        if ($wilaya_code <= 0 || $commune === '') {
            return null;
        }

        $rows = ecotrack_read_cached_communes($wilaya_code);
        if (!is_array($rows) || $rows === []) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/communes', ['wilaya_id' => $wilaya_code], null, 'bearer');
            if (empty($request['json']) || !is_array($request['json'])) {
                return null;
            }

            $rows = $request['json'];
            ecotrack_write_cached_communes($wilaya_code, $rows);
        }

        $expected = ecotrack_normalize_compare_text($commune);
        $expected_variants = ecotrack_place_name_variants($commune);
        $expected_keys = [];
        foreach ($expected_variants as $expected_variant) {
            $key = ecotrack_place_name_match_key($expected_variant);
            if ($key !== '') {
                $expected_keys[$key] = true;
            }
        }

        $best_row = null;
        $best_raw_distance = PHP_INT_MAX;
        $best_key_distance = PHP_INT_MAX;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidate = trim((string) ($row['nom'] ?? ''));
            if ($candidate === '') {
                continue;
            }
            if (ecotrack_normalize_compare_text($candidate) === $expected) {
                return $row;
            }

            $candidate_variants = ecotrack_place_name_variants($candidate);
            $candidate_best_raw = PHP_INT_MAX;
            $candidate_best_key = PHP_INT_MAX;

            foreach ($candidate_variants as $candidate_variant) {
                foreach ($expected_variants as $expected_variant) {
                    if ($candidate_variant === $expected_variant) {
                        return $row;
                    }

                    $raw_distance = levenshtein($candidate_variant, $expected_variant);
                    if ($raw_distance < $candidate_best_raw) {
                        $candidate_best_raw = $raw_distance;
                    }
                }

                $candidate_key = ecotrack_place_name_match_key($candidate_variant);
                if ($candidate_key !== '' && isset($expected_keys[$candidate_key])) {
                    return $row;
                }

                foreach (array_keys($expected_keys) as $expected_key) {
                    $key_distance = levenshtein($candidate_key, $expected_key);
                    if ($key_distance < $candidate_best_key) {
                        $candidate_best_key = $key_distance;
                    }
                }
            }

            if (
                $candidate_best_key < $best_key_distance ||
                ($candidate_best_key === $best_key_distance && $candidate_best_raw < $best_raw_distance)
            ) {
                $best_row = $row;
                $best_key_distance = $candidate_best_key;
                $best_raw_distance = $candidate_best_raw;
            }
        }

        if ($best_row !== null && ($best_key_distance <= 1 || ($best_key_distance <= 2 && $best_raw_distance <= 4))) {
            return $best_row;
        }

        return null;
    }
}

if (!function_exists('ecotrack_commune_has_stop_desk')) {
    function ecotrack_commune_has_stop_desk($commune_meta)
    { global $dbRepo;
    global $dbRepo;

        if (!is_array($commune_meta) || !array_key_exists('has_stop_desk', $commune_meta)) {
            return null;
        }

        $value = $commune_meta['has_stop_desk'];
        if ($value === null || $value === '') {
            return null;
        }

        return in_array((string) $value, ['1', 'true', 'yes'], true) || $value === 1 || $value === true;
    }
}

if (!function_exists('ecotrack_resolve_order_delivery_context')) {
    function ecotrack_resolve_order_delivery_context(PDO $pdo, array $settings, array $order)
    { global $dbRepo;
    global $dbRepo;

        $labels = function_exists('admin_delivery_type_labels')
            ? admin_delivery_type_labels()
            : ['home' => 'منزل', 'office' => 'مكتب', 'free' => 'مجاني'];

        $requested_type_code = ecotrack_normalize_delivery_type($order['delivery_type'] ?? '');
        $wilaya_code = ecotrack_algeria_wilaya_code($order['wilaya'] ?? '');
        $commune_meta = ecotrack_find_commune_meta($pdo, $settings, $wilaya_code, $order['commune'] ?? '');
        $has_stop_desk = ecotrack_commune_has_stop_desk($commune_meta);
        $fallback_message = '';

        if ($requested_type_code === 'office' && $has_stop_desk !== true) {
            $order['delivery_type'] = $labels['home'];
            if ($has_stop_desk === false) {
                $fallback_message = 'تم تحويل نوع التوصيل إلى المنزل لأن المكتب غير متاح في بلدية ' . trim((string) ($order['commune'] ?? '')) . '.';
            } else {
                $fallback_message = 'تم تحويل نوع التوصيل إلى المنزل لتجنب رفض الطلب، لأن توفر المكتب غير مؤكد في بلدية ' . trim((string) ($order['commune'] ?? '')) . '.';
            }
        } elseif ($requested_type_code === 'office') {
            $order['delivery_type'] = $labels['office'];
        } elseif ($requested_type_code === 'free') {
            $order['delivery_type'] = $labels['free'];
        } else {
            $order['delivery_type'] = $labels['home'];
        }

        return [
            'order' => $order,
            'commune_meta' => is_array($commune_meta) ? $commune_meta : [],
            'has_stop_desk' => $has_stop_desk,
            'fallback' => $fallback_message !== '',
            'fallback_message' => $fallback_message
        ];
    }
}

if (!function_exists('ecotrack_create_order_request_context')) {
    function ecotrack_create_order_request_context(PDO $pdo, array $settings, array $order)
    { global $dbRepo;
    global $dbRepo;

        $context = ecotrack_resolve_order_delivery_context($pdo, $settings, $order);
        $prepared_order = $context['order'];
        $prepared_order['ecotrack_color_label'] = ecotrack_lookup_order_option_label($pdo, 'tbl_color', 'color_id', 'color_name', $prepared_order['order_color'] ?? '');
        $prepared_order['ecotrack_size_label'] = ecotrack_lookup_order_option_label($pdo, 'tbl_size', 'size_id', 'size_name', $prepared_order['order_size'] ?? '');
        $entry = ecotrack_build_order_payload($prepared_order, $context['commune_meta']);

        $context['order'] = $prepared_order;
        $context['payload'] = [
            'orders' => [
                '0' => $entry
            ]
        ];

        return $context;
    }
}

if (!function_exists('ecotrack_create_order_request_body')) {
    function ecotrack_create_order_request_body(PDO $pdo, array $settings, array $order)
    { global $dbRepo;
    global $dbRepo;

        $context = ecotrack_create_order_request_context($pdo, $settings, $order);
        return $context['payload'];
    }
}

if (!function_exists('ecotrack_update_order_query')) {
    function ecotrack_update_order_query(PDO $pdo, array $settings, array $order)
    { global $dbRepo;
    global $dbRepo;

        $payload = ecotrack_create_order_request_body($pdo, $settings, $order);
        $entry = (array) ($payload['orders']['0'] ?? []);

        return [
            'tracking' => trim((string) ($order['ecotrack_tracking'] ?? '')),
            'reference' => trim((string) ($entry['reference'] ?? '')),
            'client' => trim((string) ($entry['nom_client'] ?? '')),
            'tel' => trim((string) ($entry['telephone'] ?? '')),
            'tel2' => trim((string) ($entry['telephone_2'] ?? '')),
            'adresse' => trim((string) ($entry['adresse'] ?? '')),
            'code_postal' => trim((string) ($entry['code_postal'] ?? '')),
            'commune' => trim((string) ($entry['commune'] ?? '')),
            'wilaya' => trim((string) ($entry['code_wilaya'] ?? '')),
            'code_wilaya' => trim((string) ($entry['code_wilaya'] ?? '')),
            'montant' => trim((string) ($entry['montant'] ?? '')),
            'remarque' => trim((string) ($entry['remarque'] ?? '')),
            'product' => trim((string) ($entry['produit'] ?? '')),
            'boutique' => trim((string) ($entry['boutique'] ?? '')),
            'type' => trim((string) ($entry['type'] ?? '1')),
            'stop_desk' => trim((string) ($entry['stop_desk'] ?? '0')),
            'fragile' => '0',
            'gps_link' => trim((string) ($entry['gps_link'] ?? ''))
        ];
    }
}

if (!function_exists('sms_gateway_default_settings')) {
    function sms_gateway_default_settings() {        return [
            'sms_gateway_enabled' => 0,
            'sms_gateway_url' => '',
            'sms_gateway_method' => 'POST',
            'sms_gateway_sender' => '',
            'sms_gateway_token' => '',
            'sms_gateway_headers' => '',
            'sms_gateway_body_template' => '',
            'sms_gateway_success_keyword' => ''
        ];
    }
}

if (!function_exists('sms_gateway_normalize_settings')) {
    function sms_gateway_normalize_settings(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = array_merge(sms_gateway_default_settings(), $settings);
        $settings['sms_gateway_enabled'] = !empty($settings['sms_gateway_enabled']) ? 1 : 0;
        $settings['sms_gateway_url'] = trim((string) ($settings['sms_gateway_url'] ?? ''));
        $settings['sms_gateway_method'] = strtoupper(trim((string) ($settings['sms_gateway_method'] ?? 'POST')));
        if (!in_array($settings['sms_gateway_method'], ['GET', 'POST'], true)) {
            $settings['sms_gateway_method'] = 'POST';
        }
        $settings['sms_gateway_sender'] = trim((string) ($settings['sms_gateway_sender'] ?? ''));
        $settings['sms_gateway_token'] = trim((string) ($settings['sms_gateway_token'] ?? ''));
        $settings['sms_gateway_headers'] = (string) ($settings['sms_gateway_headers'] ?? '');
        $settings['sms_gateway_body_template'] = (string) ($settings['sms_gateway_body_template'] ?? '');
        $settings['sms_gateway_success_keyword'] = trim((string) ($settings['sms_gateway_success_keyword'] ?? ''));
        return $settings;
    }
}

if (!function_exists('sms_gateway_is_configured')) {
    function sms_gateway_is_configured(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = sms_gateway_normalize_settings($settings);
        return $settings['sms_gateway_enabled'] === 1 && $settings['sms_gateway_url'] !== '';
    }
}

if (!function_exists('sms_gateway_detect_provider')) {
    function sms_gateway_detect_provider($url)
    { global $dbRepo;
    global $dbRepo;

        $host = strtolower(trim((string) parse_url((string) $url, PHP_URL_HOST)));
        if ($host === 'api.smstext.app') {
            return 'smstext_app';
        }

        return '';
    }
}

if (!function_exists('sms_gateway_provider_defaults')) {
    function sms_gateway_provider_defaults($provider_key, array $context = [])
    { global $dbRepo;
    global $dbRepo;

        if ($provider_key === 'smstext_app') {
            return [
                'method' => 'POST',
                'headers' => [
                    'Authorization: Basic ' . trim((string) ($context['token_basic'] ?? '')),
                    'Content-Type: application/json'
                ],
                'body_template' => '[{"mobile":"{{phone_e164}}","text":"{{message}}"}]'
            ];
        }

        return [
            'method' => '',
            'headers' => [],
            'body_template' => ''
        ];
    }
}

if (!function_exists('sms_gateway_get_phone_variants')) {
    function sms_gateway_get_phone_variants($phone)
    { global $dbRepo;
    global $dbRepo;

        $original = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $original);
        $local = $digits;
        $international = '';
        $e164 = '';

        if (strpos($digits, '213') === 0 && strlen($digits) === 12) {
            $international = $digits;
            $e164 = '+' . $digits;
            $local = '0' . substr($digits, 3);
        } else {
            if (strlen($local) === 9 && preg_match('/^[5-7]/', $local)) {
                $local = '0' . $local;
            }
            if (strlen($local) === 10 && strpos($local, '0') === 0) {
                $international = '213' . substr($local, 1);
                $e164 = '+' . $international;
            }
        }

        return [
            'phone' => $original,
            'phone_digits' => $digits,
            'phone_local' => $local,
            'phone_international' => $international,
            'phone_e164' => $e164
        ];
    }
}

if (!function_exists('sms_gateway_format_money')) {
    function sms_gateway_format_money($value)
    { global $dbRepo;
    global $dbRepo;

        if ($value === null || $value === '') {
            return '';
        }
        return number_format((float) $value, 2, '.', '');
    }
}

if (!function_exists('sms_gateway_build_context')) {
    function sms_gateway_build_context(array $settings, array $data = [])
    { global $dbRepo;
    global $dbRepo;

        $settings = sms_gateway_normalize_settings($settings);
        $order_id = $data['order_id'] ?? $data['id'] ?? '';
        $status = admin_normalize_order_status($data['status'] ?? $data['order_status'] ?? '');
        $from_status = admin_normalize_order_status($data['from_status'] ?? '');
        $status_meta = admin_get_order_status_meta($status);
        $from_status_meta = admin_get_order_status_meta($from_status);
        $site_name = trim((string) ($settings['meta_title_home'] ?? ''));
        if ($site_name === '') {
            $site_name = 'BoomStore';
        }

        $phone = (string) ($data['customer_phone'] ?? $data['phone'] ?? '');
        $token = trim((string) ($settings['sms_gateway_token'] ?? ''));
        $token_basic = '';
        if ($token !== '') {
            $token_basic = base64_encode('apikey:' . $token);
        }
        $context = [
            'site_name' => $site_name,
            'order_id' => (string) $order_id,
            'customer_name' => trim((string) ($data['customer_name'] ?? '')),
            'customer_phone' => trim($phone),
            'product_name' => trim((string) ($data['product_name'] ?? '')),
            'quantity' => (string) ($data['quantity'] ?? ''),
            'unit_price' => (string) ($data['unit_price'] ?? ''),
            'unit_price_formatted' => sms_gateway_format_money($data['unit_price'] ?? ''),
            'total_price' => (string) ($data['total_price'] ?? ''),
            'total_price_formatted' => sms_gateway_format_money($data['total_price'] ?? ''),
            'wilaya' => trim((string) ($data['wilaya'] ?? '')),
            'commune' => trim((string) ($data['commune'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'delivery_type' => trim((string) ($data['delivery_type'] ?? '')),
            'status' => $status,
            'status_label' => (string) ($status_meta['label'] ?? $status),
            'from_status' => $from_status,
            'from_status_label' => (string) ($from_status_meta['label'] ?? $from_status),
            'sender' => $settings['sms_gateway_sender'],
            'token' => $token,
            'token_basic' => $token_basic,
            'message' => ''
        ];

        return array_merge($context, sms_gateway_get_phone_variants($phone));
    }
}

if (!function_exists('sms_gateway_render_template')) {
    function sms_gateway_render_template($template, array $context)
    { global $dbRepo;
    global $dbRepo;

        $template = (string) $template;
        if ($template === '') {
            return '';
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }
}

if (!function_exists('sms_gateway_append_query_string')) {
    function sms_gateway_append_query_string($url, $query)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string) $url);
        $query = ltrim(trim((string) $query), '?&');
        if ($url === '' || $query === '') {
            return $url;
        }

        if (substr($url, -1) === '?' || substr($url, -1) === '&') {
            return $url . $query;
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }
}

if (!function_exists('sms_gateway_parse_headers')) {
    function sms_gateway_parse_headers($headers_text)
    { global $dbRepo;
    global $dbRepo;

        $headers_text = trim((string) $headers_text);
        if ($headers_text === '') {
            return [];
        }

        $decoded = json_decode($headers_text, true);
        if (is_array($decoded)) {
            $headers = [];
            foreach ($decoded as $name => $value) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $headers[] = $name . ': ' . trim((string) $value);
            }
            return $headers;
        }

        $headers = [];
        $lines = preg_split('/\r\n|\r|\n/', $headers_text);
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $headers[] = $line;
        }

        return $headers;
    }
}

if (!function_exists('sms_gateway_json_encode')) {
    function sms_gateway_json_encode($value)
    { global $dbRepo;
    global $dbRepo;

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : false;
    }
}

if (!function_exists('sms_gateway_resolve_recipient_phone')) {
    function sms_gateway_resolve_recipient_phone(array $context, $prefer_international = false)
    { global $dbRepo;
    global $dbRepo;

        $candidates = [];

        if (!empty($context['phone_e164'])) {
            $candidates[] = trim((string) $context['phone_e164']);
        }

        if (!empty($context['phone_international'])) {
            $international = trim((string) $context['phone_international']);
            $candidates[] = '+' . ltrim($international, '+');
            $candidates[] = $international;
        }

        if (!empty($context['phone_local'])) {
            $candidates[] = trim((string) $context['phone_local']);
        }

        if (!$prefer_international) {
            if (!empty($context['phone'])) {
                $candidates[] = trim((string) $context['phone']);
            }
            if (!empty($context['customer_phone'])) {
                $candidates[] = trim((string) $context['customer_phone']);
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('sms_gateway_build_provider_payload')) {
    function sms_gateway_build_provider_payload($provider_key, array $context)
    { global $dbRepo;
    global $dbRepo;

        if ($provider_key !== 'smstext_app') {
            return ['handled' => false];
        }

        $recipient = sms_gateway_resolve_recipient_phone($context, true);
        if ($recipient === '') {
            return [
                'handled' => true,
                'success' => false,
                'error' => 'Recipient phone is empty'
            ];
        }

        $body = sms_gateway_json_encode([
            [
                'mobile' => $recipient,
                'text' => trim((string) ($context['message'] ?? ''))
            ]
        ]);

        if ($body === false) {
            return [
                'handled' => true,
                'success' => false,
                'error' => 'Unable to encode SMS payload as JSON'
            ];
        }

        return [
            'handled' => true,
            'success' => true,
            'body' => $body,
            'recipient' => $recipient
        ];
    }
}

if (!function_exists('sms_gateway_http_request')) {
    function sms_gateway_http_request($method, $url, array $headers = [], $body = '')
    { global $dbRepo;
    global $dbRepo;

        $method = strtoupper(trim((string) $method));
        if ($method === '') {
            $method = 'POST';
        }

        $response = '';
        $http_code = 0;
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['response' => '', 'status_code' => 0, 'error' => 'Unable to initialize cURL'];
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $response = curl_exec($ch);
            if (!is_string($response)) {
                $response = '';
            }
            $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = trim((string) curl_error($ch));
            curl_close($ch);
        } else {
            $options = [
                'http' => [
                    'method' => $method,
                    'timeout' => 15,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers)
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];

            if ($method === 'POST') {
                $options['http']['content'] = $body;
            }

            $stream_context = stream_context_create($options);
            $response = @file_get_contents($url, false, $stream_context);
            if (!is_string($response)) {
                $response = '';
            }

            if (!empty($http_response_header) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $matches)) {
                $http_code = (int) $matches[1];
            }
        }

        return [
            'response' => $response,
            'status_code' => $http_code,
            'error' => $error
        ];
    }
}

if (!function_exists('sms_gateway_mask_sensitive_value')) {
    function sms_gateway_mask_sensitive_value($value)
    { global $dbRepo;
    global $dbRepo;

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/([?&](?:token|api[_-]?key|apikey|authorization)=)[^&]+/i', '$1***', $value);
        return (string) $value;
    }
}

if (!function_exists('sms_gateway_finalize_result')) {
    function sms_gateway_finalize_result(array $settings, array $http_result, array $debug_context = [])
    { global $dbRepo;
    global $dbRepo;

        $settings = sms_gateway_normalize_settings($settings);
        $response = (string) ($http_result['response'] ?? '');
        $http_code = (int) ($http_result['status_code'] ?? 0);
        $error = trim((string) ($http_result['error'] ?? ''));

        $success = $http_code >= 200 && $http_code < 300;
        if ($success && $settings['sms_gateway_success_keyword'] !== '') {
            $success = stripos($response, $settings['sms_gateway_success_keyword']) !== false;
            if (!$success) {
                $error = 'Success keyword not found in gateway response';
            }
        }

        if (!$success && $error === '') {
            $error = 'Gateway returned HTTP ' . ($http_code > 0 ? $http_code : 0);
        }

        if (!$success) {
            $parts = ['SMS gateway request failed: ' . $error];
            if (!empty($debug_context['provider'])) {
                $parts[] = 'Provider: ' . $debug_context['provider'];
            }
            if (!empty($debug_context['recipient'])) {
                $parts[] = 'Recipient: ' . sms_gateway_mask_sensitive_value($debug_context['recipient']);
            }
            if (!empty($debug_context['url'])) {
                $parts[] = 'URL: ' . sms_gateway_mask_sensitive_value($debug_context['url']);
            }
            if ($response !== '') {
                $parts[] = 'Response: ' . substr($response, 0, 500);
            }
            error_log(implode(' | ', $parts));
        }

        return [
            'success' => $success,
            'status_code' => $http_code,
            'response' => $response,
            'error' => $error
        ];
    }
}

if (!function_exists('sms_gateway_dispatch')) {
    function sms_gateway_dispatch(array $settings, array $context)
    { global $dbRepo;
    global $dbRepo;

        return ['success' => false, 'error' => 'Messaging removed'];

        $settings = sms_gateway_normalize_settings($settings);
        $message = trim((string) ($context['message'] ?? ''));

        if (!$settings['sms_gateway_enabled']) {
            return ['success' => false, 'error' => 'SMS gateway disabled'];
        }

        if ($settings['sms_gateway_url'] === '') {
            return ['success' => false, 'error' => 'SMS gateway URL is empty'];
        }

        if ($message === '') {
            return ['success' => false, 'error' => 'SMS message is empty'];
        }

        $url = sms_gateway_render_template($settings['sms_gateway_url'], $context);
        $method = $settings['sms_gateway_method'];
        $headers = sms_gateway_parse_headers(sms_gateway_render_template($settings['sms_gateway_headers'], $context));
        $body_template = trim((string) $settings['sms_gateway_body_template']);
        $provider_key = sms_gateway_detect_provider($url);
        if ($provider_key !== '') {
            $provider_defaults = sms_gateway_provider_defaults($provider_key, $context);
            if ($provider_defaults['method'] !== '') {
                $method = $provider_defaults['method'];
            }
            if (!empty($provider_defaults['headers'])) {
                $headers = $provider_defaults['headers'];
            }
            if ($provider_defaults['body_template'] !== '') {
                $body_template = $provider_defaults['body_template'];
            }
        }
        $content_type = '';
        $provider_payload = sms_gateway_build_provider_payload($provider_key, $context);

        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $content_type = trim(substr($header, strlen('Content-Type:')));
                break;
            }
        }

        $body = '';
        if ($method === 'GET') {
            if ($body_template === '') {
                $query = [
                    'to' => sms_gateway_resolve_recipient_phone($context),
                    'message' => $message
                ];
                if (!empty($context['sender'])) {
                    $query['sender'] = $context['sender'];
                }
                if (!empty($context['token']) && strpos($url, '{{token}}') === false) {
                    $query['token'] = $context['token'];
                }
                $url = sms_gateway_append_query_string($url, http_build_query($query));
            } else {
                $body = sms_gateway_render_template($body_template, $context);
                $url = sms_gateway_append_query_string($url, $body);
                $body = '';
            }
        } else {
            if (!empty($provider_payload['handled'])) {
                if (empty($provider_payload['success'])) {
                    return ['success' => false, 'error' => trim((string) ($provider_payload['error'] ?? 'Failed to build SMS payload'))];
                }
                $body = (string) ($provider_payload['body'] ?? '');
            } elseif ($body_template === '') {
                if ($content_type === '' || stripos($content_type, 'application/json') !== false) {
                    if ($content_type === '') {
                        $headers[] = 'Content-Type: application/json';
                    }
                    $body = sms_gateway_json_encode([
                        'to' => sms_gateway_resolve_recipient_phone($context),
                        'message' => $message,
                        'sender' => (string) ($context['sender'] ?? '')
                    ]);
                    if ($body === false) {
                        return ['success' => false, 'error' => 'Unable to encode SMS payload as JSON'];
                    }
                } else {
                    $body = http_build_query([
                        'to' => sms_gateway_resolve_recipient_phone($context),
                        'message' => $message,
                        'sender' => (string) ($context['sender'] ?? '')
                    ]);
                }
            } else {
                $body = sms_gateway_render_template($body_template, $context);
            }
        }

        $http_result = sms_gateway_http_request($method, $url, $headers, $body);
        return sms_gateway_finalize_result($settings, $http_result, [
            'provider' => $provider_key,
            'recipient' => $provider_payload['recipient'] ?? sms_gateway_resolve_recipient_phone($context),
            'url' => $url
        ]);
    }
}

if (!function_exists('sms_gateway_send_manual_message')) {
    function sms_gateway_send_manual_message(array $settings, $phone, $message, array $context = [])
    { global $dbRepo;
    global $dbRepo;

        $settings = sms_gateway_normalize_settings($settings);
        $context['customer_phone'] = $phone;
        $render_context = sms_gateway_build_context($settings, $context);
        $render_context['message'] = trim(sms_gateway_render_template((string) $message, $render_context));
        return sms_gateway_dispatch($settings, $render_context);
    }
}

if (!function_exists('sms_gateway_send_bulk_manual_messages')) {
    function sms_gateway_send_bulk_manual_messages(array $settings, array $items)
    { global $dbRepo;
    global $dbRepo;

        $settings = sms_gateway_normalize_settings($settings);
        $provider_key = sms_gateway_detect_provider($settings['sms_gateway_url']);
        $prepared_items = [];
        $failed_messages = [];
        $skipped_count = 0;

        foreach ($items as $item) {
            $context = [];
            if (!empty($item['context']) && is_array($item['context'])) {
                $context = $item['context'];
            }

            $phone = trim((string) ($item['phone'] ?? ($context['customer_phone'] ?? ($context['phone'] ?? ''))));
            $message = trim((string) ($item['message'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                $order_id = (int) ($context['id'] ?? ($context['order_id'] ?? 0));
                if ($order_id > 0) {
                    $label = '#' . $order_id;
                } else {
                    $label = trim((string) ($context['customer_name'] ?? $phone));
                }
            }

            if ($phone === '') {
                $skipped_count++;
                continue;
            }

            if ($message === '') {
                $failed_messages[] = $label . ': SMS message is empty';
                continue;
            }

            $context['customer_phone'] = $phone;
            $render_context = sms_gateway_build_context($settings, $context);
            $render_context['message'] = trim(sms_gateway_render_template($message, $render_context));

            if ($render_context['message'] === '') {
                $failed_messages[] = $label . ': SMS message is empty';
                continue;
            }

            $prepared_items[] = [
                'label' => $label,
                'context' => $render_context
            ];
        }

        if (empty($prepared_items)) {
            return [
                'success' => false,
                'sent_count' => 0,
                'failed_messages' => $failed_messages,
                'skipped_count' => $skipped_count,
                'error' => !empty($failed_messages) ? trim((string) $failed_messages[0]) : 'No SMS recipients available'
            ];
        }

        if ($provider_key === 'smstext_app') {
            $payload = [];
            $bulk_labels = [];

            foreach ($prepared_items as $prepared_item) {
                $recipient = sms_gateway_resolve_recipient_phone($prepared_item['context'], true);
                if ($recipient === '') {
                    $failed_messages[] = $prepared_item['label'] . ': Recipient phone is empty';
                    continue;
                }

                $payload[] = [
                    'mobile' => $recipient,
                    'text' => (string) $prepared_item['context']['message']
                ];
                $bulk_labels[] = $prepared_item['label'];
            }

            if (empty($payload)) {
                return [
                    'success' => false,
                    'sent_count' => 0,
                    'failed_messages' => $failed_messages,
                    'skipped_count' => $skipped_count,
                    'error' => !empty($failed_messages) ? trim((string) $failed_messages[0]) : 'No SMS recipients available'
                ];
            }

            $body = sms_gateway_json_encode($payload);
            if ($body === false) {
                return [
                    'success' => false,
                    'sent_count' => 0,
                    'failed_messages' => array_merge($failed_messages, ['Bulk SMS payload encoding failed']),
                    'skipped_count' => $skipped_count,
                    'error' => 'Unable to encode SMS payload as JSON'
                ];
            }

            $url = sms_gateway_render_template($settings['sms_gateway_url'], $prepared_items[0]['context']);
            $provider_defaults = sms_gateway_provider_defaults($provider_key, $prepared_items[0]['context']);
            $headers = !empty($provider_defaults['headers']) ? $provider_defaults['headers'] : [];
            $http_result = sms_gateway_http_request('POST', $url, $headers, $body);
            $result = sms_gateway_finalize_result($settings, $http_result, [
                'provider' => $provider_key,
                'recipient' => 'bulk(' . count($payload) . ')',
                'url' => $url
            ]);

            if (!empty($result['success'])) {
                return [
                    'success' => true,
                    'sent_count' => count($payload),
                    'failed_messages' => $failed_messages,
                    'skipped_count' => $skipped_count,
                    'status_code' => (int) ($result['status_code'] ?? 0),
                    'response' => (string) ($result['response'] ?? ''),
                    'error' => ''
                ];
            }

            $bulk_error = trim((string) ($result['error'] ?? 'Gateway error'));
            foreach ($bulk_labels as $bulk_label) {
                $failed_messages[] = $bulk_label . ': ' . $bulk_error;
            }

            return [
                'success' => false,
                'sent_count' => 0,
                'failed_messages' => $failed_messages,
                'skipped_count' => $skipped_count,
                'status_code' => (int) ($result['status_code'] ?? 0),
                'response' => (string) ($result['response'] ?? ''),
                'error' => $bulk_error
            ];
        }

        $sent_count = 0;
        foreach ($prepared_items as $prepared_item) {
            $result = sms_gateway_dispatch($settings, $prepared_item['context']);
            if (!empty($result['success'])) {
                $sent_count++;
                continue;
            }

            $failed_messages[] = $prepared_item['label'] . ': ' . trim((string) ($result['error'] ?? 'Gateway error'));
        }

        return [
            'success' => $sent_count > 0 && empty($failed_messages),
            'sent_count' => $sent_count,
            'failed_messages' => $failed_messages,
            'skipped_count' => $skipped_count,
            'error' => !empty($failed_messages) ? trim((string) $failed_messages[0]) : ''
        ];
    }
}

if (!function_exists('admin_ensure_sms_template_table')) {
    function admin_ensure_sms_template_table(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/sms_template_table.lock';
        if (file_exists($lock_file)) {
            return;
        }

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_sms_template (
            id INT(11) NOT NULL AUTO_INCREMENT,
            template_name VARCHAR(120) NOT NULL,
            template_body TEXT NOT NULL,
            sort_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('admin_get_sms_templates')) {
    function admin_get_sms_templates(PDO $pdo, $active_only = true)
    { global $dbRepo;
    global $dbRepo;

        admin_ensure_sms_template_table($pdo);
        $sql = "SELECT id, template_name, template_body, sort_order, is_active FROM tbl_sms_template";
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $statement = $dbRepo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('admin_get_sms_automation_events')) {
    function admin_get_sms_automation_events() {        return [
            'order_created' => [
                'label' => 'عند إنشاء طلب جديد',
                'hint' => 'تُرسل مباشرة بعد تسجيل الطلب في الموقع أو من لوحة الإدارة.'
            ],
            'status_pending' => [
                'label' => 'عند نقل الطلب إلى قيد الانتظار',
                'hint' => 'تُستخدم عندما تعيد الطلب للمراجعة أو المتابعة.'
            ],
            'status_confirmed' => [
                'label' => 'عند نقل الطلب إلى قيد المعالجة',
                'hint' => 'مناسبة لإعلام الزبون بأن الطلب دخل مرحلة المعالجة.'
            ],
            'status_completed' => [
                'label' => 'عند تأكيد الطلب',
                'hint' => 'تُرسل بعد اعتماد الطلب نهائياً من الإدارة.'
            ],
            'status_cancelled' => [
                'label' => 'عند إلغاء الطلب',
                'hint' => 'مناسبة لإبلاغ الزبون بسبب الإلغاء أو الخطوة التالية.'
            ],
            'status_returned' => [
                'label' => 'عند تحويل الطلب إلى مرتجع',
                'hint' => 'تُرسل عندما يتحول الطلب إلى حالة مرتجع.'
            ]
        ];
    }
}

if (!function_exists('admin_ensure_sms_automation_table')) {
    function admin_ensure_sms_automation_table(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/sms_automation_table.lock';
        if (file_exists($lock_file)) {
            return;
        }

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_sms_automation (
            event_key VARCHAR(80) NOT NULL,
            template_body TEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (event_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $events = admin_get_sms_automation_events();
        $statement = $dbRepo->prepare("INSERT IGNORE INTO tbl_sms_automation (event_key, template_body, is_enabled, updated_at) VALUES (?, '', 0, NOW())");
        foreach (array_keys($events) as $event_key) {
            $statement->execute([$event_key]);
        }

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('admin_get_sms_automation_templates')) {
    function admin_get_sms_automation_templates(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        admin_ensure_sms_automation_table($pdo);

        $statement = $dbRepo->prepare("SELECT event_key, template_body, is_enabled, updated_at FROM tbl_sms_automation");
        $statement->execute();
        $stored_rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $stored_map = [];
        foreach ($stored_rows as $stored_row) {
            $stored_map[(string) ($stored_row['event_key'] ?? '')] = $stored_row;
        }

        $result = [];
        foreach (admin_get_sms_automation_events() as $event_key => $event_meta) {
            $stored = $stored_map[$event_key] ?? [];
            $result[$event_key] = [
                'event_key' => $event_key,
                'label' => (string) ($event_meta['label'] ?? $event_key),
                'hint' => (string) ($event_meta['hint'] ?? ''),
                'template_body' => (string) ($stored['template_body'] ?? ''),
                'is_enabled' => !empty($stored['is_enabled']) ? 1 : 0,
                'updated_at' => (string) ($stored['updated_at'] ?? '')
            ];
        }

        return $result;
    }
}

if (!function_exists('admin_save_sms_automation_templates')) {
    function admin_save_sms_automation_templates(PDO $pdo, array $submitted_templates)
    { global $dbRepo;
    global $dbRepo;

        admin_ensure_sms_automation_table($pdo);

        $statement = $dbRepo->prepare("
            INSERT INTO tbl_sms_automation (event_key, template_body, is_enabled, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                template_body = VALUES(template_body),
                is_enabled = VALUES(is_enabled),
                updated_at = NOW()
        ");

        foreach (admin_get_sms_automation_events() as $event_key => $event_meta) {
            $row = isset($submitted_templates[$event_key]) && is_array($submitted_templates[$event_key])
                ? $submitted_templates[$event_key]
                : [];
            $statement->execute([
                $event_key,
                trim((string) ($row['body'] ?? '')),
                !empty($row['enabled']) ? 1 : 0
            ]);
        }
    }
}

if (!function_exists('admin_resolve_sms_status_event_key')) {
    function admin_resolve_sms_status_event_key($status)
    { global $dbRepo;
    global $dbRepo;

        $status = admin_normalize_order_status($status);
        $map = [
            'Pending' => 'status_pending',
            'Confirmed' => 'status_confirmed',
            'Completed' => 'status_completed',
            'Cancelled' => 'status_cancelled',
            'Returned' => 'status_returned'
        ];

        return $map[$status] ?? '';
    }
}

if (!function_exists('admin_send_order_sms_automation')) {
    function admin_send_order_sms_automation(PDO $pdo, $event_key, array $order_context, array $settings = null, array $automation_templates = null)
    { global $dbRepo;
    global $dbRepo;

        return ['success' => false, 'skipped' => true, 'reason' => 'sms_removed'];

        $event_key = trim((string) $event_key);
        if ($event_key === '') {
            return ['success' => false, 'skipped' => true, 'reason' => 'missing_event'];
        }

        $phone = trim((string) ($order_context['customer_phone'] ?? ''));
        if ($phone === '') {
            return ['success' => false, 'skipped' => true, 'reason' => 'missing_phone'];
        }

        if ($settings === null) {
            $settings = front_get_settings($pdo);
        }
        if (!sms_gateway_is_configured($settings)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'gateway_not_configured'];
        }

        if ($automation_templates === null) {
            $automation_templates = admin_get_sms_automation_templates($pdo);
        }

        if (empty($automation_templates[$event_key])) {
            return ['success' => false, 'skipped' => true, 'reason' => 'missing_template'];
        }

        $template = $automation_templates[$event_key];
        $template_body = trim((string) ($template['template_body'] ?? ''));
        if (empty($template['is_enabled']) || $template_body === '') {
            return ['success' => false, 'skipped' => true, 'reason' => 'disabled_or_empty'];
        }

        $send_result = sms_gateway_send_manual_message($settings, $phone, $template_body, $order_context);
        $send_result['event_key'] = $event_key;
        $send_result['skipped'] = false;

        return $send_result;
    }
}

if (!function_exists('front_get_page_content')) {
    function front_get_page_content(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        static $cache = [];

        $cache_key = spl_object_hash($pdo);
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $statement = $dbRepo->prepare("SELECT * FROM tbl_page WHERE id=1 LIMIT 1");
        $statement->execute();
        $page = $statement->fetch(PDO::FETCH_ASSOC);

        $cache[$cache_key] = is_array($page) ? $page : [];
        return $cache[$cache_key];
    }
}

if (!function_exists('front_bootstrap_language')) {
    function front_bootstrap_language(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        static $loaded = [];

        $cache_key = spl_object_hash($pdo);
        if (!empty($loaded[$cache_key])) {
            return;
        }

        $statement = $dbRepo->prepare("SELECT * FROM tbl_language");
        $statement->execute();
        $languages = $statement->fetchAll(PDO::FETCH_ASSOC);

        $i = 1;
        foreach ($languages as $row) {
            $constant_name = 'LANG_VALUE_' . $i;
            if (!defined($constant_name)) {
                define($constant_name, $row['lang_value']);
            }
            $i++;
        }

        $loaded[$cache_key] = true;
    }
}

if (!function_exists('front_get_top_categories')) {
    function front_get_top_categories(PDO $pdo, $limit = 6)
    { global $dbRepo;
    global $dbRepo;

        static $cache = [];

        $limit = (int)$limit;
        if ($limit < 0) {
            $limit = 0;
        }

        $cache_key = spl_object_hash($pdo) . ':' . $limit;
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $sql = "SELECT tcat_id, tcat_name FROM tbl_top_category WHERE show_on_menu=1 ORDER BY tcat_id ASC";
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        $statement = $dbRepo->prepare($sql);
        $statement->execute();
        $cache[$cache_key] = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $cache[$cache_key];
    }
}

if (!function_exists('front_get_product_rating_map')) {
    function front_get_product_rating_map(PDO $pdo, array $product_ids)
    { global $dbRepo;
    global $dbRepo;

        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));
        if (empty($product_ids)) {
            return [];
        }

        $ratings = [];
        foreach ($product_ids as $product_id) {
            $ratings[$product_id] = [
                'avg_rating' => 0.0,
                'total_reviews' => 0
            ];
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $statement = $dbRepo->prepare(
            "SELECT p_id, ROUND(AVG(rating) * 2) / 2 AS avg_rating, COUNT(*) AS total_reviews
             FROM tbl_rating
             WHERE p_id IN ($placeholders)
             GROUP BY p_id"
        );
        $statement->execute($product_ids);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $product_id = (int)($row['p_id'] ?? 0);
            if ($product_id <= 0) {
                continue;
            }

            $ratings[$product_id] = [
                'avg_rating' => (float)($row['avg_rating'] ?? 0),
                'total_reviews' => (int)($row['total_reviews'] ?? 0)
            ];
        }

        return $ratings;
    }
}

if (!function_exists('front_render_rating_stars')) {
    function front_render_rating_stars($avg_rating)
    { global $dbRepo;
    global $dbRepo;

        $avg_rating = round(((float)$avg_rating) * 2) / 2;
        if ($avg_rating <= 0) {
            return '';
        }

        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            if ($avg_rating >= $i) {
                $stars[] = '<i class="fa fa-star"></i>';
            } elseif (($i - $avg_rating) === 0.5) {
                $stars[] = '<i class="fa fa-star-half-o"></i>';
            } else {
                $stars[] = '<i class="fa fa-star-o"></i>';
            }
        }

        return implode("\n", $stars);
    }
}

if (!function_exists('db_table_has_columns')) {
    function db_table_has_columns(PDO $pdo, $table_name, array $required_columns)
    { global $dbRepo;
    global $dbRepo;

        static $cache = [];

        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table_name);
        if ($table_name === '' || empty($required_columns)) {
            return false;
        }

        $cache_key = spl_object_hash($pdo) . ':' . $table_name;
        if (!isset($cache[$cache_key])) {
            try {
                $statement = $dbRepo->query("SHOW COLUMNS FROM `" . $table_name . "`");
                $fields = $statement ? $statement->fetchAll(PDO::FETCH_COLUMN, 0) : [];
                $cache[$cache_key] = array_map('strval', $fields);
            } catch (Exception $e) {
                $cache[$cache_key] = [];
            }
        }

        foreach ($required_columns as $column_name) {
            if (!in_array((string)$column_name, $cache[$cache_key], true)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('build_site_absolute_url')) {
    function build_site_absolute_url($path)
    { global $dbRepo;
    global $dbRepo;

        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }

        if (is_external_image_url($path)) {
            $resolved = resolve_external_image_url($path, false);
            return $resolved !== '' ? $resolved : $path;
        }

        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $base = '';
        if (defined('SITE_URL')) {
            $base = trim((string)SITE_URL);
        }
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return rtrim($base, '/') . $path;
    }
}

if (!function_exists('build_cloudinary_delivery_url')) {
    function build_cloudinary_delivery_url($url, $max_width = 0, $quality = 72)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '' || !is_cloudinary_url($url)) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            return $url;
        }

        $host = strtolower((string)$parts['host']);
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        if ($host !== 'res.cloudinary.com') {
            return $url;
        }

        $config_quality = defined('EXTERNAL_IMAGE_PROXY_QUALITY') ? (int)EXTERNAL_IMAGE_PROXY_QUALITY : 72;
        $config_quality = max(40, min(90, $config_quality));
        $requested_quality = (int)$quality;
        if ($requested_quality <= 0) {
            $requested_quality = $config_quality;
        }
        $quality = max(40, min(90, min($config_quality, $requested_quality)));
        $width = max(0, (int)$max_width);

        $path = (string)$parts['path'];
        if (!preg_match('#^/([^/]+)/image/upload/(.+)$#i', $path, $matches)) {
            return $url;
        }

        $transforms = [
            'f_auto',
            'dpr_auto',
            'q_' . $quality
        ];
        if ($width > 0) {
            array_unshift($transforms, 'c_limit,w_' . $width);
        }

        $new_path = '/' . $matches[1] . '/image/upload/' . implode(',', $transforms) . '/' . ltrim((string)$matches[2], '/');
        $rebuilt = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
        if (!empty($parts['port'])) {
            $rebuilt .= ':' . (int)$parts['port'];
        }
        $rebuilt .= $new_path;
        if (!empty($parts['query'])) {
            $rebuilt .= '?' . (string)$parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . (string)$parts['fragment'];
        }

        return $rebuilt;
    }
}

if (!function_exists('build_external_image_proxy_url')) {
    function build_external_image_proxy_url($url, $max_width = 0, $quality = 72)
    { global $dbRepo;
    global $dbRepo;

        $url = trim((string)$url);
        if ($url === '' || !is_valid_image_url($url)) {
            return $url;
        }

        if (defined('EXTERNAL_IMAGE_PROXY_ENABLED') && EXTERNAL_IMAGE_PROXY_ENABLED === false) {
            return $url;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        if ($host === 'res.cloudinary.com') {
            return build_cloudinary_delivery_url($url, $max_width, $quality);
        }
        if ($host === 'wsrv.nl') {
            return $url;
        }

        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        $ext = (string)pathinfo($path, PATHINFO_EXTENSION);
        $config_quality = defined('EXTERNAL_IMAGE_PROXY_QUALITY') ? (int)EXTERNAL_IMAGE_PROXY_QUALITY : 72;
        $config_quality = max(40, min(90, $config_quality));
        $requested_quality = (int)$quality;
        if ($requested_quality <= 0) {
            $requested_quality = $config_quality;
        }
        $quality = max(40, min(90, min($config_quality, $requested_quality)));
        $width = max(0, (int)$max_width);

        $params = [
            'url' => $url,
            'q' => $quality
        ];
        if ($width > 0) {
            $params['w'] = $width;
        }
        if (!in_array($ext, ['gif', 'svg'], true)) {
            $params['output'] = 'webp';
        }

        $proxy_base = defined('EXTERNAL_IMAGE_PROXY_BASE') ? trim((string)EXTERNAL_IMAGE_PROXY_BASE) : 'https://wsrv.nl/';
        if ($proxy_base === '') {
            $proxy_base = 'https://wsrv.nl/';
        }
        $proxy_base = rtrim($proxy_base, '/');

        return $proxy_base . '/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('get_front_optimized_image_url')) {
    function get_front_optimized_image_url($value, $max_width = 1200, $quality = 72)
    { global $dbRepo;
    global $dbRepo;

        $value = normalize_image_value($value);
        if ($value === '') {
            return '';
        }

        if (is_external_image_url($value)) {
            $resolved = resolve_external_image_url($value, false);
            if ($resolved === '') {
                return '';
            }
            return build_external_image_proxy_url($resolved, $max_width, $quality);
        }

        $local_rel = 'assets/uploads/' . ltrim($value, '/\\');
        $local_rel = str_replace('\\', '/', $local_rel);

        // Use pregenerated responsive WebP if available.
        $width = max(0, (int)$max_width);
        if ($width > 0) {
            $base_name = pathinfo($local_rel, PATHINFO_FILENAME);
            if ($base_name !== '') {
                $candidate_rel = 'assets/uploads/' . $base_name . '-w' . $width . '.webp';
                $candidate_abs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate_rel);
                if (is_file($candidate_abs)) {
                    return $candidate_rel;
                }
            }
        }

        // If no local resized derivative exists, proxy local files through wsrv to
        // get width-constrained optimized delivery without requiring GD on server.
        $ext = strtolower((string)pathinfo($local_rel, PATHINFO_EXTENSION));
        if (!in_array($ext, ['gif', 'svg'], true)) {
            $absolute_url = build_site_absolute_url($local_rel);
            if ($absolute_url !== '') {
                return build_external_image_proxy_url($absolute_url, $max_width, $quality);
            }
        }

        return $local_rel;
    }
}

if (!function_exists('get_admin_image_url')) {
    function get_admin_image_url($value)
    { global $dbRepo;
    global $dbRepo;

        $value = normalize_image_value($value);
        if ($value === '') {
            return '';
        }

        if (is_external_image_url($value)) {
            return resolve_external_image_url($value, false);
        }

        return '../assets/uploads/' . ltrim($value, '/\\');
    }
}

if (!function_exists('get_local_upload_path')) {
    function get_local_upload_path($value, $base_dir)
    { global $dbRepo;
    global $dbRepo;

        $value = normalize_image_value($value);
        if ($value === '' || is_external_image_url($value)) {
            return '';
        }

        $value = str_replace(['..\\', '../'], '', $value);
        $value = ltrim($value, '/\\');
        if ($value === '') {
            return '';
        }

        return rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR . $value;
    }
}

if (!function_exists('delete_local_image_file')) {
    function delete_local_image_file($value, $base_dir)
    { global $dbRepo;
    global $dbRepo;

        if (defined('PRESERVE_LOCAL_UPLOAD_FILES') && PRESERVE_LOCAL_UPLOAD_FILES === true) {
            return;
        }
        $full_path = get_local_upload_path($value, $base_dir);
        if ($full_path !== '' && is_file($full_path)) {
            @unlink($full_path);
        }
    }
}

if (!function_exists('store_image_input')) {
    function store_image_input($file_field, $url_field, $target_basename, $upload_dir, &$error_message, $required = false, $allowed_ext = null)
    { global $dbRepo;
    global $dbRepo;

        if ($allowed_ext === null) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        }

        $url_value = trim((string)($_POST[$url_field] ?? ''));
        $file_name = (string)($_FILES[$file_field]['name'] ?? '');
        $file_tmp = (string)($_FILES[$file_field]['tmp_name'] ?? '');
        $file_error = (int)($_FILES[$file_field]['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($url_value !== '') {
            $url_value = store_external_image_url($url_value, $error_message);
            if ($url_value === '' || (!is_valid_image_url($url_value) && !is_external_image_url($url_value))) {
                $error_message .= 'Image URL is not valid.<br>';
                return [false, ''];
            }

            return [true, $url_value];
        }

        if ($file_name !== '') {
            $ext = strtolower((string)pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) {
                $error_message .= 'You must upload: ' . implode(', ', $allowed_ext) . '.<br>';
                return [false, ''];
            }

            if ($file_error !== UPLOAD_ERR_OK || !is_uploaded_file($file_tmp)) {
                $error_message .= 'Upload failed. Please try again.<br>';
                return [false, ''];
            }

            return store_uploaded_image_file($file_tmp, $file_name, $target_basename, $upload_dir, $error_message, $allowed_ext);
        }

        if ($required) {
            $error_message .= 'You must select a photo or provide an image URL.<br>';
            return [false, ''];
        }

        return [true, ''];
    }
}

if (!function_exists('admin_set_flash_message')) {
    function admin_set_flash_message($key, $type, $message)
    { global $dbRepo;
    global $dbRepo;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['_admin_flash']) || !is_array($_SESSION['_admin_flash'])) {
            $_SESSION['_admin_flash'] = [];
        }

        $_SESSION['_admin_flash'][(string) $key] = [
            'type' => (string) $type,
            'message' => (string) $message
        ];
    }
}

if (!function_exists('admin_pull_flash_message')) {
    function admin_pull_flash_message($key)
    { global $dbRepo;
    global $dbRepo;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $key = (string) $key;
        if (!isset($_SESSION['_admin_flash'][$key])) {
            return null;
        }

        $message = $_SESSION['_admin_flash'][$key];
        unset($_SESSION['_admin_flash'][$key]);

        return is_array($message) ? $message : null;
    }
}

if (!function_exists('admin_order_status_definitions')) {
    function admin_order_status_definitions() {        return [
            'Pending' => [
                'label' => 'قيد التأكيد',
                'short_label' => 'قيد الانتظار',
                'badge_class' => 'label label-warning',
                'accent_class' => 'is-pending',
                'description' => 'طلب جديد يحتاج اتصال أو مراجعة قبل اعتماده.'
            ],
            'Completed' => [
                'label' => 'مؤكد',
                'short_label' => 'مؤكد',
                'badge_class' => 'label label-success',
                'accent_class' => 'is-completed',
                'description' => 'تم تأكيد الطلب وهو جاهز للمتابعة والتنفيذ.'
            ],
            'Returned' => [
                'label' => 'مرتجع',
                'short_label' => 'مرتجع',
                'badge_class' => 'label label-info',
                'accent_class' => 'is-returned',
                'description' => 'الطلب أُعيد أو تعذر تسليمه بعد التأكيد.'
            ],
            'Cancelled' => [
                'label' => 'ملغي',
                'short_label' => 'ملغي',
                'badge_class' => 'label label-danger',
                'accent_class' => 'is-cancelled',
                'description' => 'تم إلغاء الطلب من الإدارة أو من العميل.'
            ],
            'Confirmed' => [
                'label' => 'قيد المعالجة',
                'short_label' => 'معالجة',
                'badge_class' => 'label label-primary',
                'accent_class' => 'is-confirmed',
                'description' => 'حالة قديمة موجودة في بعض الطلبات قيد التنفيذ.'
            ]
        ];
    }
}

if (!function_exists('admin_normalize_order_status')) {
    function admin_normalize_order_status($status)
    { global $dbRepo;
    global $dbRepo;

        $status = trim((string) $status);
        if ($status === '') {
            return 'Pending';
        }

        $map = [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
            'confirmed' => 'Confirmed'
        ];

        $normalized_key = strtolower($status);
        return $map[$normalized_key] ?? $status;
    }
}

if (!function_exists('admin_get_order_status_meta')) {
    function admin_get_order_status_meta($status)
    { global $dbRepo;
    global $dbRepo;

        $status = admin_normalize_order_status($status);
        $definitions = admin_order_status_definitions();

        return $definitions[$status] ?? [
            'label' => $status !== '' ? $status : 'غير محدد',
            'short_label' => $status !== '' ? $status : 'غير محدد',
            'badge_class' => 'label label-default',
            'accent_class' => 'is-neutral',
            'description' => 'حالة غير معرفة داخل النظام.'
        ];
    }
}

if (!function_exists('admin_get_order_status_sequence')) {
    function admin_get_order_status_sequence() {        return ['Pending', 'Completed', 'Returned', 'Cancelled', 'Confirmed'];
    }
}

if (!function_exists('admin_get_order_allowed_transitions')) {
    function admin_get_order_allowed_transitions($status)
    { global $dbRepo;
    global $dbRepo;

        $status = admin_normalize_order_status($status);

        $map = [
            'Pending' => ['Completed', 'Cancelled'],
            'Completed' => ['Returned', 'Cancelled', 'Pending'],
            'Returned' => ['Completed', 'Cancelled', 'Pending'],
            'Cancelled' => ['Pending', 'Completed'],
            'Confirmed' => ['Completed', 'Returned', 'Cancelled', 'Pending']
        ];

        return $map[$status] ?? ['Pending', 'Completed', 'Returned', 'Cancelled'];
    }
}

if (!function_exists('admin_can_transition_order_status')) {
    function admin_can_transition_order_status($from_status, $to_status)
    { global $dbRepo;
    global $dbRepo;

        $from_status = admin_normalize_order_status($from_status);
        $to_status = admin_normalize_order_status($to_status);

        if ($to_status === '' || $from_status === $to_status) {
            return false;
        }

        return in_array($to_status, admin_get_order_allowed_transitions($from_status), true);
    }
}

if (!function_exists('admin_format_order_amount')) {
    function admin_format_order_amount($amount)
    { global $dbRepo;
    global $dbRepo;

        return number_format((float) $amount, 0, '.', ' ') . ' دج';
    }
}

if (!function_exists('admin_get_order_call_statuses')) {
    function admin_get_order_call_statuses() {        return [
            'no_answer' => ['label' => 'لم يرد', 'badge_class' => 'label label-warning'],
            'answered' => ['label' => 'تم الرد', 'badge_class' => 'label label-success'],
            'busy' => ['label' => 'مشغول', 'badge_class' => 'label label-info'],
            'phone_off' => ['label' => 'هاتف مغلق', 'badge_class' => 'label label-default'],
            'wrong_number' => ['label' => 'رقم خاطئ', 'badge_class' => 'label label-danger']
        ];
    }
}

if (!function_exists('admin_get_order_call_status_meta')) {
    function admin_get_order_call_status_meta($status)
    { global $dbRepo;
    global $dbRepo;

        $statuses = admin_get_order_call_statuses();
        return $statuses[(string) $status] ?? ['label' => 'غير محدد', 'badge_class' => 'label label-default'];
    }
}

if (!function_exists('admin_ensure_order_call_log_table')) {
    function admin_ensure_order_call_log_table(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        static $ensured = false;

        if ($ensured) {
            return;
        }

        $lock_file = __DIR__ . '/../cache/order_call_log_table.lock';
        if (file_exists($lock_file)) {
            $ensured = true;
            return;
        }

        if (function_exists('admin_db_table_exists') && admin_db_table_exists($pdo, 'tbl_order_call_log')) {
            @file_put_contents($lock_file, '1');
            $ensured = true;
            return;
        }

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_order_call_log (
            id INT(11) NOT NULL AUTO_INCREMENT,
            order_id INT(11) NOT NULL,
            call_status VARCHAR(50) NOT NULL,
            call_note TEXT,
            called_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by VARCHAR(100) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_order_id (order_id),
            INDEX idx_called_at (called_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @file_put_contents($lock_file, '1');
        $ensured = true;
    }
}

if (!function_exists('admin_ensure_order_status_log_table')) {
    function admin_ensure_order_status_log_table(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        static $ensured = false;

        if ($ensured) {
            return;
        }

        $lock_file = __DIR__ . '/../cache/order_status_log_table.lock';
        if (file_exists($lock_file)) {
            $ensured = true;
            return;
        }

        if (function_exists('admin_db_table_exists') && admin_db_table_exists($pdo, 'tbl_order_status_log')) {
            @file_put_contents($lock_file, '1');
            $ensured = true;
            return;
        }

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_order_status_log (
            id INT(11) NOT NULL AUTO_INCREMENT,
            order_id INT(11) NOT NULL,
            from_status VARCHAR(20) DEFAULT NULL,
            to_status VARCHAR(20) NOT NULL,
            status_note TEXT,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            changed_by VARCHAR(100) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_order_id (order_id),
            INDEX idx_changed_at (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @file_put_contents($lock_file, '1');
        $ensured = true;
    }
}

if (!function_exists('admin_log_order_status_change')) {
    function admin_log_order_status_change(PDO $pdo, $order_id, $from_status, $to_status, $status_note = '', $changed_by = null)
    { global $dbRepo;
    global $dbRepo;

        $from_status = admin_normalize_order_status($from_status);
        $to_status = admin_normalize_order_status($to_status);

        if ((int) $order_id <= 0 || $to_status === '' || $from_status === $to_status) {
            return false;
        }

        if (!$pdo->inTransaction()) {
            admin_ensure_order_status_log_table($pdo);
        } elseif (function_exists('admin_db_table_exists') && !admin_db_table_exists($pdo, 'tbl_order_status_log')) {
            throw new RuntimeException('Order status log table is missing before status change transaction.');
        }

        $statement = $dbRepo->prepare("
            INSERT INTO tbl_order_status_log (order_id, from_status, to_status, status_note, changed_at, changed_by)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");

        $res = $statement->execute([
            (int) $order_id,
            $from_status,
            $to_status,
            trim((string) $status_note) !== '' ? trim((string) $status_note) : null,
            $changed_by !== null ? trim((string) $changed_by) : null
        ]);

        if ($res && class_exists('EventManager')) {
            EventManager::dispatch('OrderUpdated', $pdo, (int)$order_id, $from_status, $to_status, $status_note, $changed_by);
        }

        return $res;
    }
}

if (!function_exists('admin_db_table_exists')) {
    function admin_db_table_exists(PDO $pdo, $table_name)
    { global $dbRepo;
    global $dbRepo;

        static $cache = [];

        $table_name = trim((string) $table_name);
        if ($table_name === '') {
            return false;
        }

        $cache_key = strtolower($table_name);
        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        try {
            $quoted_table = $pdo->quote($table_name);
            $statement = $dbRepo->query("SHOW TABLES LIKE {$quoted_table}");
            $cache[$cache_key] = (bool) $statement->fetchColumn();
        } catch (Exception $e) {
            $cache[$cache_key] = false;
            error_log('admin_db_table_exists failed for ' . $table_name . ': ' . $e->getMessage());
        }

        return $cache[$cache_key];
    }
}

if (!function_exists('admin_live_fetch_signature_row')) {
    function admin_live_fetch_signature_row(PDO $pdo, $sql, array $params = [])
    { global $dbRepo;
    global $dbRepo;

        try {
            $statement = $dbRepo->prepare($sql);
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : [];
        } catch (Exception $e) {
            error_log('admin_live_fetch_signature_row failed: ' . $e->getMessage());
            return ['error' => '1'];
        }
    }
}

if (!function_exists('admin_live_signature_orders')) {
    function admin_live_signature_orders(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        return admin_live_fetch_signature_row($pdo, "
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(CRC32(CONCAT_WS('|',
                    id,
                    COALESCE(customer_name, ''),
                    COALESCE(customer_phone, ''),
                    COALESCE(product_name, ''),
                    COALESCE(quantity, ''),
                    COALESCE(total_price, ''),
                    COALESCE(order_status, ''),
                    COALESCE(customer_type, ''),
                    COALESCE(delivery_type, ''),
                    COALESCE(wilaya, ''),
                    COALESCE(commune, '')
                ))), 0) AS checksum
            FROM tbl_order
        ");
    }
}

if (!function_exists('admin_live_signature_products')) {
    function admin_live_signature_products(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        return admin_live_fetch_signature_row($pdo, "
            SELECT
                COUNT(*) AS total_products,
                COALESCE(SUM(CRC32(CONCAT_WS('|',
                    p_id,
                    COALESCE(p_name, ''),
                    COALESCE(p_qty, ''),
                    COALESCE(p_current_price, ''),
                    COALESCE(p_is_active, ''),
                    COALESCE(p_is_featured, ''),
                    COALESCE(p_total_view, '')
                ))), 0) AS checksum
            FROM tbl_product
        ");
    }
}

if (!function_exists('admin_live_signature_customers')) {
    function admin_live_signature_customers(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        if (!admin_db_table_exists($pdo, 'tbl_customer')) {
            return ['total_customers' => 0, 'active_customers' => 0];
        }

        return admin_live_fetch_signature_row($pdo, "
            SELECT
                COUNT(*) AS total_customers,
                COALESCE(SUM(CASE WHEN cust_status = 1 THEN 1 ELSE 0 END), 0) AS active_customers
            FROM tbl_customer
        ");
    }
}

if (!function_exists('admin_live_signature_incomplete_orders')) {
    function admin_live_signature_incomplete_orders(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        if (!admin_db_table_exists($pdo, 'incomplete_orders')) {
            return ['total_incomplete_orders' => 0, 'checksum' => 0];
        }

        return admin_live_fetch_signature_row($pdo, "
            SELECT
                COUNT(*) AS total_incomplete_orders,
                COALESCE(SUM(CRC32(CONCAT_WS('|',
                    id,
                    COALESCE(customer_name, ''),
                    COALESCE(customer_phone, ''),
                    COALESCE(product_name, ''),
                    COALESCE(quantity, ''),
                    COALESCE(total_price, ''),
                    COALESCE(created_at, '')
                ))), 0) AS checksum
            FROM incomplete_orders
        ");
    }
}

if (!function_exists('admin_live_signature_delivery')) {
    function admin_live_signature_delivery(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $payload = [
            'companies' => ['total_companies' => 0, 'checksum' => 0],
            'prices' => ['total_prices' => 0, 'checksum' => 0]
        ];

        if (admin_db_table_exists($pdo, 'tbl_delivery_company')) {
            $payload['companies'] = admin_live_fetch_signature_row($pdo, "
                SELECT
                    COUNT(*) AS total_companies,
                    COALESCE(SUM(CRC32(CONCAT_WS('|',
                        id,
                        COALESCE(name, ''),
                        COALESCE(active, '')
                    ))), 0) AS checksum
                FROM tbl_delivery_company
            ");
        }

        if (admin_db_table_exists($pdo, 'tbl_delivery_price')) {
            $payload['prices'] = admin_live_fetch_signature_row($pdo, "
                SELECT
                    COUNT(*) AS total_prices,
                    COALESCE(SUM(CRC32(CONCAT_WS('|',
                        id,
                        COALESCE(company_id, ''),
                        COALESCE(wilaya, ''),
                        COALESCE(delivery_type, ''),
                        COALESCE(price, '')
                    ))), 0) AS checksum
                FROM tbl_delivery_price
            ");
        }

        return $payload;
    }
}

if (!function_exists('admin_live_signature_sms_templates')) {
    function admin_live_signature_sms_templates(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        if (!admin_db_table_exists($pdo, 'tbl_sms_template')) {
            return ['total_templates' => 0, 'checksum' => 0];
        }

        return admin_live_fetch_signature_row($pdo, "
            SELECT
                COUNT(*) AS total_templates,
                COALESCE(SUM(CRC32(CONCAT_WS('|',
                    id,
                    COALESCE(template_name, ''),
                    COALESCE(template_body, ''),
                    COALESCE(sort_order, ''),
                    COALESCE(is_active, '')
                ))), 0) AS checksum
            FROM tbl_sms_template
        ");
    }
}

if (!function_exists('admin_live_signature_settings_subset')) {
    function admin_live_signature_settings_subset(PDO $pdo, array $keys)
    { global $dbRepo;
    global $dbRepo;

        $settings = front_get_settings($pdo);
        $payload = [];

        foreach ($keys as $key) {
            $payload[(string) $key] = (string) ($settings[$key] ?? '');
        }

        return $payload;
    }
}

if (!function_exists('admin_live_signature_order_details')) {
    function admin_live_signature_order_details(PDO $pdo, $order_id)
    { global $dbRepo;
    global $dbRepo;

        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return ['order_exists' => 0];
        }

        $payload = [];
        $payload['order'] = admin_live_fetch_signature_row($pdo, "
            SELECT
                COUNT(*) AS order_exists,
                COALESCE(SUM(CRC32(CONCAT_WS('|',
                    id,
                    COALESCE(customer_name, ''),
                    COALESCE(customer_phone, ''),
                    COALESCE(product_name, ''),
                    COALESCE(quantity, ''),
                    COALESCE(unit_price, ''),
                    COALESCE(total_price, ''),
                    COALESCE(order_status, ''),
                    COALESCE(wilaya, ''),
                    COALESCE(commune, ''),
                    COALESCE(address, ''),
                    COALESCE(delivery_type, '')
                ))), 0) AS checksum
            FROM tbl_order
            WHERE id = ?
        ", [$order_id]);

        if (admin_db_table_exists($pdo, 'tbl_order_call_log')) {
            $payload['call_logs'] = admin_live_fetch_signature_row($pdo, "
                SELECT
                    COUNT(*) AS total_calls,
                    COALESCE(SUM(CRC32(CONCAT_WS('|',
                        id,
                        order_id,
                        COALESCE(call_status, ''),
                        COALESCE(call_note, ''),
                        COALESCE(called_at, ''),
                        COALESCE(created_by, '')
                    ))), 0) AS checksum
                FROM tbl_order_call_log
                WHERE order_id = ?
            ", [$order_id]);
        } else {
            $payload['call_logs'] = ['total_calls' => 0, 'checksum' => 0];
        }

        if (admin_db_table_exists($pdo, 'tbl_order_status_log')) {
            $payload['status_logs'] = admin_live_fetch_signature_row($pdo, "
                SELECT
                    COUNT(*) AS total_status_logs,
                    COALESCE(SUM(CRC32(CONCAT_WS('|',
                        id,
                        order_id,
                        COALESCE(from_status, ''),
                        COALESCE(to_status, ''),
                        COALESCE(status_note, ''),
                        COALESCE(changed_at, ''),
                        COALESCE(changed_by, '')
                    ))), 0) AS checksum
                FROM tbl_order_status_log
                WHERE order_id = ?
            ", [$order_id]);
        } else {
            $payload['status_logs'] = ['total_status_logs' => 0, 'checksum' => 0];
        }

        return $payload;
    }
}

if (!function_exists('admin_live_refresh_payload')) {
    function admin_live_refresh_payload(PDO $pdo, $scope, array $options = [])
    { global $dbRepo;
    global $dbRepo;

        $scope = trim((string) $scope);

        switch ($scope) {
            case 'dashboard':
                return [
                    'orders' => admin_live_signature_orders($pdo),
                    'products' => admin_live_signature_products($pdo),
                    'customers' => admin_live_signature_customers($pdo),
                    'incomplete_orders' => admin_live_signature_incomplete_orders($pdo),
                    'delivery' => admin_live_signature_delivery($pdo)
                ];

            case 'orders':
                return [
                    'orders' => admin_live_signature_orders($pdo),
                    'incomplete_orders' => admin_live_signature_incomplete_orders($pdo),
                    'sms_settings' => admin_live_signature_settings_subset($pdo, [
                        'sms_gateway_enabled',
                        'sms_gateway_url',
                        'sms_gateway_method',
                        'sms_gateway_sender',
                        'sms_gateway_token',
                        'sms_gateway_success_keyword'
                    ]),
                    'sms_templates' => admin_live_signature_sms_templates($pdo),
                    'call_logs' => admin_db_table_exists($pdo, 'tbl_order_call_log')
                        ? admin_live_fetch_signature_row($pdo, "SELECT COUNT(*) AS total_logs, COALESCE(SUM(CRC32(CONCAT_WS('|', id, order_id, COALESCE(call_status, ''), COALESCE(call_note, ''), COALESCE(called_at, '')))), 0) AS checksum FROM tbl_order_call_log")
                        : ['total_logs' => 0, 'checksum' => 0],
                    'status_logs' => admin_db_table_exists($pdo, 'tbl_order_status_log')
                        ? admin_live_fetch_signature_row($pdo, "SELECT COUNT(*) AS total_logs, COALESCE(SUM(CRC32(CONCAT_WS('|', id, order_id, COALESCE(from_status, ''), COALESCE(to_status, ''), COALESCE(status_note, ''), COALESCE(changed_at, '')))), 0) AS checksum FROM tbl_order_status_log")
                        : ['total_logs' => 0, 'checksum' => 0]
                ];

            case 'order_details':
                return [
                    'order_details' => admin_live_signature_order_details($pdo, (int) ($options['order_id'] ?? 0)),
                    'sms_settings' => admin_live_signature_settings_subset($pdo, [
                        'sms_gateway_enabled',
                        'sms_gateway_url',
                        'sms_gateway_method',
                        'sms_gateway_sender',
                        'sms_gateway_token',
                        'sms_gateway_success_keyword'
                    ]),
                    'sms_templates' => admin_live_signature_sms_templates($pdo),
                    'ecotrack_settings' => admin_live_signature_settings_subset($pdo, [
                        'ecotrack_enabled',
                        'ecotrack_api_token',
                        'ecotrack_base_url'
                    ])
                ];

            case 'store':
                return [
                    'products' => admin_live_signature_products($pdo),
                    'orders' => admin_live_fetch_signature_row($pdo, "
                        SELECT
                            COUNT(*) AS total_orders,
                            COALESCE(SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
                            COALESCE(SUM(CASE WHEN order_status = 'Confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_orders,
                            COALESCE(SUM(CASE WHEN order_status = 'Completed' AND DATE(order_date) = CURDATE() THEN total_price ELSE 0 END), 0) AS completed_today_total
                        FROM tbl_order
                    "),
                    'incomplete_orders' => admin_live_signature_incomplete_orders($pdo),
                    'delivery' => admin_live_signature_delivery($pdo)
                ];

            case 'delivery':
                return [
                    'delivery' => admin_live_signature_delivery($pdo)
                ];

            case 'incomplete_orders':
                return [
                    'incomplete_orders' => admin_live_signature_incomplete_orders($pdo)
                ];
        }

        return ['scope' => $scope, 'fallback' => '1'];
    }
}

if (!function_exists('admin_live_refresh_version')) {
    function admin_live_refresh_version(PDO $pdo, $scope, array $options = [])
    { global $dbRepo;
    global $dbRepo;

        $payload = admin_live_refresh_payload($pdo, $scope, $options);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return sha1($encoded !== false ? $encoded : serialize($payload));
    }
}

if (!function_exists('admin_build_live_refresh_config')) {
    function admin_build_live_refresh_config(PDO $pdo, $scope, array $options = [])
    { global $dbRepo;
    global $dbRepo;

        $scope = trim((string) $scope);
        if ($scope === '') {
            return ['enabled' => false];
        }

        $interval_ms = isset($options['interval_ms']) ? (int) $options['interval_ms'] : 20000;
        if ($interval_ms < 10000) {
            $interval_ms = 10000;
        }

        $params = ['scope' => $scope];
        if (!empty($options['order_id'])) {
            $params['order_id'] = (int) $options['order_id'];
        }

        return [
            'enabled' => true,
            'scope' => $scope,
            'endpoint' => 'live-update-status.php',
            'interval_ms' => $interval_ms,
            'params' => $params,
            'version' => admin_live_refresh_version($pdo, $scope, $options),
            'active_text' => 'التحديث التلقائي مفعل',
            'pending_text' => 'يوجد تحديث جديد وسيتم تطبيقه تلقائياً'
        ];
    }
}

if (!function_exists('admin_ensure_zrexpress_setting_columns')) {
    function admin_ensure_zrexpress_setting_columns(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/zrexpress_setting_columns.lock';
        if (file_exists($lock_file)) {
            return;
        }

        admin_add_column_if_missing($pdo, 'tbl_settings', 'zrexpress_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        admin_add_column_if_missing($pdo, 'tbl_settings', 'zrexpress_token', 'TEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_settings', 'zrexpress_key', 'TEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_settings', 'zrexpress_base_url', "VARCHAR(255) NOT NULL DEFAULT ''");

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('admin_ensure_order_zrexpress_columns')) {
    function admin_ensure_order_zrexpress_columns(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $lock_file = __DIR__ . '/../cache/order_zrexpress_columns.lock';
        if (file_exists($lock_file)) {
            return;
        }

        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_status', "VARCHAR(40) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_reference', "VARCHAR(120) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_tracking', "VARCHAR(120) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_remote_status', "VARCHAR(120) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_remote_time', 'DATETIME NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_last_error', 'TEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_last_payload', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_last_response', 'LONGTEXT NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'zrexpress_sent_at', 'DATETIME NULL');

        @file_put_contents($lock_file, '1');
    }
}

if (!function_exists('zrexpress_default_settings')) {
    function zrexpress_default_settings() {        return [
            'zrexpress_enabled' => 0,
            'zrexpress_token' => '',
            'zrexpress_key' => '',
            'zrexpress_base_url' => 'https://procolis.com/api_v1'
        ];
    }
}

if (!function_exists('zrexpress_normalize_settings')) {
    function zrexpress_normalize_settings(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = array_merge(zrexpress_default_settings(), $settings);
        $settings['zrexpress_enabled'] = !empty($settings['zrexpress_enabled']) ? 1 : 0;
        $settings['zrexpress_token'] = trim((string) ($settings['zrexpress_token'] ?? ''));
        $settings['zrexpress_key'] = trim((string) ($settings['zrexpress_key'] ?? ''));
        
        $base_url = trim((string) ($settings['zrexpress_base_url'] ?? ''));
        if ($base_url === '') {
            $base_url = 'https://procolis.com/api_v1';
        }
        $settings['zrexpress_base_url'] = rtrim($base_url, '/');
        
        return $settings;
    }
}

if (!function_exists('zrexpress_is_configured')) {
    function zrexpress_is_configured(array $settings)
    { global $dbRepo;
    global $dbRepo;

        $settings = zrexpress_normalize_settings($settings);
        return $settings['zrexpress_enabled'] === 1 && $settings['zrexpress_token'] !== '' && $settings['zrexpress_key'] !== '';
    }
}

if (!function_exists('zrexpress_status_meta')) {
    function zrexpress_status_meta($status)
    { global $dbRepo;
    global $dbRepo;

        $status = strtolower(trim((string) $status));

        switch ($status) {
            case 'synced':
                return ['label' => 'تمت المزامنة', 'class' => 'label label-info'];
            case 'shipped':
                return ['label' => 'تم تأكيد الشحن', 'class' => 'label label-primary'];
            case 'sent':
                return ['label' => 'أرسل إلى ZRexpress', 'class' => 'label label-success'];
            case 'error':
                return ['label' => 'فشل الإرسال', 'class' => 'label label-danger'];
            case 'pending':
                return ['label' => 'جاهز للإرسال', 'class' => 'label label-warning'];
            default:
                return ['label' => 'غير مربوط بعد', 'class' => 'label label-default'];
        }
    }
}

if (!function_exists('zrexpress_api_request')) {
    function zrexpress_api_request(PDO $pdo, array $settings, $method, $path, array $query = [], $body = null)
    { global $dbRepo;
    global $dbRepo;

        $settings = zrexpress_normalize_settings($settings);
        $base_url = $settings['zrexpress_base_url'];

        $method = strtoupper(trim((string) $method));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'GET';
        }

        $url = $base_url . '/' . ltrim((string) $path, '/');
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'token: ' . $settings['zrexpress_token'],
            'key: ' . $settings['zrexpress_key']
        ];

        $payload = '';
        if ($body !== null) {
            $payload = is_string($body)
                ? $body
                : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $response = '';
        $status_code = 0;
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return [
                    'success' => false,
                    'status_code' => 0,
                    'response' => '',
                    'json' => null,
                    'error' => 'Unable to initialize cURL',
                    'url' => $url
                ];
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($method !== 'GET' && $payload !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
            }
            curl_close($ch);
        } else {
            $error = 'cURL extension is not enabled';
        }

        $json = null;
        if ($error === '') {
            $json = json_decode((string) $response, true);
        }

        return [
            'success' => ($error === '' && $status_code >= 200 && $status_code < 300),
            'status_code' => $status_code,
            'response' => $response,
            'json' => $json,
            'error' => $error,
            'url' => $url
        ];
    }
}



// Ensure essential directories exist
if (!function_exists('ensure_system_directories')) {
    function ensure_system_directories() {        $dirs = [
            __DIR__ . '/../../assets/uploads',
            __DIR__ . '/../invoices',
            __DIR__ . '/../logs',
            __DIR__ . '/../cron'
        ];
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        
        $uploads_dir = __DIR__ . '/../../assets/uploads';
        $htaccess_path = $uploads_dir . '/.htaccess';
        if (file_exists($uploads_dir) && !file_exists($htaccess_path)) {
            $htaccess_content = "<FilesMatch \"\\.(php|php4|php5|php7|php8|phtml|pl|py|jsp|asp|htm|html|shtml|sh|cgi)$\">\n    Require all denied\n</FilesMatch>\n<IfModule mod_php5.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n";
            @file_put_contents($htaccess_path, $htaccess_content);
        }
    }
    ensure_system_directories();
}


if (!function_exists('add_admin_notification')) {
    function add_admin_notification(PDO $pdo, string $title, string $message, string $type = 'info') { global $dbRepo;
    global $dbRepo;

        $stmt = $dbRepo->query("SELECT id FROM tbl_user");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $insert = $dbRepo->prepare("INSERT INTO tbl_notification (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        foreach ($admins as $admin) {
            $insert->execute([$admin['id'], $title, $message, $type]);
        }
    }
}
