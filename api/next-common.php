<?php
declare(strict_types=1);

if (!defined('NEXT_CUSTOMER_API')) {
    define('NEXT_CUSTOMER_API', true);
}

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/functions.php';
require_once __DIR__ . '/../inc/encryption.php';
require_once __DIR__ . '/../inc/site-security.php';

if (!function_exists('next_api_headers')) {
    function next_api_headers(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

if (!function_exists('next_json')) {
    function next_json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('next_text')) {
    function next_text($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('admin_fix_broken_arabic_text')) {
            $fixed = admin_fix_broken_arabic_text($value);
            if (is_string($fixed) && trim($fixed) !== '') {
                return trim($fixed);
            }
        }

        return $value;
    }
}

if (!function_exists('next_base_url')) {
    function next_base_url(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/ecom/api'));
        $root = preg_replace('#/api$#', '', $script);
        $root = rtrim($root ?: '', '/');

        return $scheme . '://' . $host . $root;
    }
}

if (!function_exists('next_asset_url')) {
    function next_asset_url($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $url = function_exists('get_front_image_url') ? get_front_image_url($value) : ('assets/uploads/' . ltrim($value, '/\\'));
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) || strpos($url, '//') === 0) {
            return $url;
        }

        return rtrim(next_base_url(), '/') . '/' . ltrim($url, '/');
    }
}

if (!function_exists('next_read_payload')) {
    function next_read_payload(): array
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

if (!function_exists('next_resolve_product_id')) {
    function next_resolve_product_id($raw): int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 0;
        }

        $decoded = function_exists('decrypt_product_id') ? decrypt_product_id($raw) : false;
        if ($decoded !== false && (int) $decoded > 0) {
            return (int) $decoded;
        }

        return ctype_digit($raw) ? (int) $raw : 0;
    }
}

if (!function_exists('next_product_link')) {
    function next_product_link(array $product, string $fallback = 'buy-now'): string
    {
        $id = (int) ($product['p_id'] ?? $product['id'] ?? 0);
        $template = trim((string) ($product['product_template'] ?? $fallback));
        $route = '/buy-now';

        if ($template === 'landing_page.php' || $template === 'landing_page' || $template === 'landing') {
            $route = '/landing_page';
        } elseif ($template === 'landing_page_2.php' || $template === 'landing_page_2' || $template === 'landing-2') {
            $route = '/landing_page_2';
        }

        $secureId = function_exists('encrypt_product_id') ? encrypt_product_id($id) : (string) $id;
        return $route . '?id=' . rawurlencode($secureId);
    }
}

if (!function_exists('next_delivery_labels')) {
    function next_delivery_labels(): array
    {
        if (function_exists('admin_delivery_type_labels')) {
            return admin_delivery_type_labels();
        }

        return [
            'home' => 'منزل',
            'office' => 'مكتب',
            'free' => 'مجاني',
        ];
    }
}

if (!function_exists('next_load_shipping_data')) {
    function next_load_shipping_data(PDO $pdo, int $productId, string $deliveryMode, int $preferredCompanyId = 0): array
    {
        $deliveryMode = function_exists('normalize_product_delivery_mode')
            ? normalize_product_delivery_mode($deliveryMode)
            : 'home_office';

        $companyIds = [];
        if (function_exists('resolve_product_delivery_company_id')) {
            $resolved = resolve_product_delivery_company_id($pdo, $preferredCompanyId);
            if ($resolved > 0) {
                $companyIds[] = (int) $resolved;
            }
        } elseif ($preferredCompanyId > 0) {
            $companyIds[] = $preferredCompanyId;
        }

        try {
            $stmt = $pdo->query("SELECT id FROM tbl_delivery_company ORDER BY active DESC, id ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0 && !in_array($id, $companyIds, true)) {
                    $companyIds[] = $id;
                }
            }
        } catch (Throwable $e) {
            // Some legacy installs do not have delivery companies yet.
        }

        $labels = next_delivery_labels();
        foreach ($companyIds as $companyId) {
            $shipping = [];
            try {
                $stmt = $pdo->prepare("SELECT wilaya, price, delivery_type FROM tbl_delivery_price WHERE company_id = ?");
                $stmt->execute([$companyId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $wilaya = next_text($row['wilaya'] ?? '');
                    if ($wilaya === '') {
                        continue;
                    }
                    $type = function_exists('resolve_delivery_type_by_mode')
                        ? resolve_delivery_type_by_mode($row['delivery_type'] ?? '', $deliveryMode)
                        : ($row['delivery_type'] ?? $labels['home']);
                    $shipping[$wilaya][$type] = (float) ($row['price'] ?? 0);
                }
            } catch (Throwable $e) {
                $shipping = [];
            }

            if (!empty($shipping)) {
                ksort($shipping);
                return [
                    'companyId' => $companyId,
                    'mode' => $deliveryMode,
                    'labels' => $labels,
                    'prices' => $shipping,
                ];
            }
        }

        return [
            'companyId' => 0,
            'mode' => $deliveryMode,
            'labels' => $labels,
            'prices' => [],
        ];
    }
}

if (!function_exists('next_load_product_payload')) {
    function next_load_product_payload(PDO $pdo, int $productId): array
    {
        if (function_exists('ensure_product_delivery_company_column')) {
            ensure_product_delivery_company_column($pdo);
        }
        if (function_exists('ensure_product_offer_table')) {
            ensure_product_offer_table($pdo);
        }

        $stmt = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id = ? AND p_is_active = 1 LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return [];
        }

        $deliveryMode = function_exists('normalize_product_delivery_mode')
            ? normalize_product_delivery_mode($product['p_delivery_mode'] ?? 'home_office')
            : 'home_office';
        $preferredCompanyId = (int) ($product['p_delivery_company_id'] ?? 0);

        $offers = [];
        try {
            $stmt = $pdo->prepare("SELECT offer_id, offer_qty, offer_unit_price, offer_type, offer_description, offer_photo, is_most_popular, sort_order FROM tbl_product_offer WHERE p_id = ? AND is_active = 1 ORDER BY sort_order ASC, offer_qty ASC");
            $stmt->execute([$productId]);
            $base = (float) ($product['p_current_price'] ?? 0);
            $specialIndex = 1;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $qty = max(1, (int) ($row['offer_qty'] ?? 1));
                $unit = (float) ($row['offer_unit_price'] ?? 0);
                if ($unit <= 0) {
                    continue;
                }
                $type = (string) ($row['offer_type'] ?? 'quantity');
                $discount = ($base > 0) ? max(0, (int) round((1 - ($unit / $base)) * 100)) : 0;
                $offers[] = [
                    'id' => (int) ($row['offer_id'] ?? 0),
                    'type' => $type === 'special' ? 'special' : 'quantity',
                    'label' => $type === 'special' ? ('العرض ' . $specialIndex++) : ($qty . ' قطع'),
                    'qty' => $qty,
                    'unitPrice' => $unit,
                    'total' => $qty * $unit,
                    'baseTotal' => $qty * $base,
                    'discount' => $discount,
                    'description' => next_text(strip_tags((string) ($row['offer_description'] ?? ''))),
                    'photo' => next_asset_url($row['offer_photo'] ?? ''),
                    'popular' => (int) ($row['is_most_popular'] ?? 0) === 1,
                ];
            }
        } catch (Throwable $e) {
            $offers = [];
        }

        $sizes = [];
        try {
            $stmt = $pdo->prepare("SELECT ps.size_id, s.size_name FROM tbl_product_size ps LEFT JOIN tbl_size s ON s.size_id = ps.size_id WHERE ps.p_id = ? AND ps.size_id != '0' ORDER BY s.size_name ASC");
            $stmt->execute([$productId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = next_text($row['size_name'] ?? $row['size_id'] ?? '');
                if ($name !== '' && strtolower($name) !== 'no size') {
                    $sizes[] = ['id' => (string) $row['size_id'], 'name' => $name];
                }
            }
        } catch (Throwable $e) {
            $sizes = [];
        }

        $colors = [];
        try {
            $stmt = $pdo->prepare("SELECT pc.color_id, c.color_name, pp.photo FROM tbl_product_color pc LEFT JOIN tbl_color c ON c.color_id = pc.color_id LEFT JOIN tbl_product_photo pp ON pp.p_id = pc.p_id AND pp.color_id = pc.color_id WHERE pc.p_id = ? ORDER BY c.color_name ASC");
            $stmt->execute([$productId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $colors[] = [
                    'id' => (string) ($row['color_id'] ?? ''),
                    'name' => next_text($row['color_name'] ?? $row['color_id'] ?? ''),
                    'photo' => next_asset_url($row['photo'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            $colors = [];
        }

        $photos = [];
        foreach (['p_featured_photo', 'landing_photo_1', 'landing_photo_2', 'landing_photo_3'] as $field) {
            $url = next_asset_url($product[$field] ?? '');
            if ($url !== '' && !in_array($url, $photos, true)) {
                $photos[] = $url;
            }
        }
        try {
            $stmt = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? AND photo IS NOT NULL AND photo != '' ORDER BY pp_id ASC");
            $stmt->execute([$productId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $url = next_asset_url($row['photo'] ?? '');
                if ($url !== '' && !in_array($url, $photos, true)) {
                    $photos[] = $url;
                }
            }
        } catch (Throwable $e) {
            // Optional photos are not required for ordering.
        }

        $settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];

        return [
            'baseUrl' => next_base_url(),
            'store' => [
                'name' => next_text($settings['meta_title_home'] ?? 'متجر الثقة'),
                'logo' => next_asset_url($settings['logo'] ?? ''),
                'phone' => next_text($settings['contact_phone'] ?? ''),
                'email' => next_text($settings['contact_email'] ?? ''),
            ],
            'product' => [
                'id' => (int) $product['p_id'],
                'secureId' => function_exists('encrypt_product_id') ? encrypt_product_id((int) $product['p_id']) : (string) $product['p_id'],
                'name' => next_text($product['p_name'] ?? ''),
                'price' => (float) ($product['p_current_price'] ?? 0),
                'oldPrice' => (float) ($product['p_old_price'] ?? 0),
                'stock' => (int) ($product['p_qty'] ?? 0),
                'description' => next_text(strip_tags((string) ($product['p_description'] ?? ''))),
                'shortDescription' => next_text(strip_tags((string) ($product['p_short_description'] ?? ''))),
                'moreDescription' => next_text(strip_tags((string) ($product['more_description'] ?? ''))),
                'announcement' => next_text($product['p_announcement'] ?? ''),
                'template' => next_text($product['product_template'] ?? ''),
            ],
            'photos' => $photos,
            'offers' => $offers,
            'sizes' => $sizes,
            'colors' => $colors,
            'delivery' => next_load_shipping_data($pdo, $productId, $deliveryMode, $preferredCompanyId),
            'pixels' => [
                'facebook' => next_text($settings['facebook_pixel_id'] ?? ''),
                'tiktok' => next_text($settings['tiktok_pixel_id'] ?? ''),
            ],
        ];
    }
}

if (!function_exists('next_product_card')) {
    function next_product_card(array $row): array
    {
        $price = (float) ($row['p_current_price'] ?? 0);
        $old = (float) ($row['p_old_price'] ?? 0);
        $badge = 'متاح';
        if ($old > $price && $price > 0) {
            $badge = 'خصم ' . max(1, (int) round((($old - $price) / $old) * 100)) . '%';
        } elseif ((int) ($row['p_qty'] ?? 0) <= 0) {
            $badge = 'نفد المخزون';
        }

        $photo = '';
        foreach (['landing_photo_1', 'landing_photo_2', 'landing_photo_3', 'p_featured_photo'] as $field) {
            $photo = next_asset_url($row[$field] ?? '');
            if ($photo !== '') {
                break;
            }
        }

        return [
            'id' => (int) ($row['p_id'] ?? 0),
            'name' => next_text($row['p_name'] ?? ''),
            'price' => $price,
            'oldPrice' => $old,
            'image' => $photo,
            'url' => next_product_link($row),
            'badge' => $badge,
            'views' => (int) ($row['p_total_view'] ?? 0),
            'soldOut' => (int) ($row['p_qty'] ?? 0) <= 0,
        ];
    }
}
