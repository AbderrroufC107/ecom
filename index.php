<?php
require_once __DIR__ . '/inc/next-customer-bridge.php';
next_customer_redirect('/');

$hide_auth_links = true;
$body_class = 'index-react';
$index_css = 'assets/css/index-react.css';
$page_stylesheet = $index_css;
$css_version = @filemtime(__DIR__ . '/' . $index_css);
if ($css_version) {
    $page_stylesheet .= '?v=' . $css_version;
}

require_once('header.php');
require_once('inc/encryption.php');

if (!function_exists('home_image_url')) {
    function home_image_url($image)
    {
        $image = trim((string)$image);
        if ($image === '') {
            return '';
        }
        return trim((string)get_front_image_url($image));
    }
}

if (!function_exists('home_pick_product_photo')) {
    function home_pick_product_photo($row)
    {
        foreach (['landing_photo_1', 'landing_photo_2', 'landing_photo_3', 'p_featured_photo'] as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}

if (!function_exists('home_product_badge')) {
    function home_product_badge($row, $fallback)
    {
        $price = (float)($row['p_current_price'] ?? 0);
        $old = (float)($row['p_old_price'] ?? 0);
        if ($old > $price && $price > 0) {
            $percent = (int)round((($old - $price) / $old) * 100);
            if ($percent > 0) {
                return 'خصم ' . $percent . '%';
            }
        }
        return $fallback;
    }
}

if (!function_exists('home_product_payload')) {
    function home_product_payload($row, $badge)
    {
        $photo = home_pick_product_photo($row);
        $price = (float)($row['p_current_price'] ?? 0);
        $old = (float)($row['p_old_price'] ?? 0);
        return [
            'id' => (int)($row['p_id'] ?? 0),
            'name' => (string)($row['p_name'] ?? ''),
            'price' => $price,
            'oldPrice' => $old,
            'image' => home_image_url($photo),
            'url' => create_secure_product_link((int)($row['p_id'] ?? 0), $row['product_template'] ?? ''),
            'views' => (int)($row['p_total_view'] ?? 0),
            'badge' => home_product_badge($row, $badge),
            'saving' => ($old > $price && $price > 0) ? ($old - $price) : 0
        ];
    }
}

if (!function_exists('home_clean_home_label')) {
    function home_clean_home_label($value, $fallback)
    {
        $value = trim((string)$value);
        $normalized = strtolower($value);
        $map = [
            'read more' => 'تعرف أكثر',
            'latest products' => 'أحدث المنتجات',
            'featured products' => 'منتجات مختارة',
            'popular products' => 'الأكثر طلبا',
            'our list of recently added products' => 'أحدث المنتجات التي تمت إضافتها للمتجر',
            'popular products based on customer interest' => 'منتجات عليها اهتمام أكبر من العملاء'
        ];
        if ($value === '') {
            return $fallback;
        }
        return $map[$normalized] ?? $value;
    }
}

$settings_stmt = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$settings_stmt->execute();
$home_settings = $settings_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$total_featured = min((int)($home_settings['total_featured_product_home'] ?? 6), 8);
$total_latest = min((int)($home_settings['total_latest_product_home'] ?? 6), 8);
$total_popular = min((int)($home_settings['total_popular_product_home'] ?? 6), 8);

$slider_items = [];
try {
    $stmt = $pdo->prepare("SELECT photo, heading, content, button_url, button_text FROM tbl_slider ORDER BY id ASC");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $slide) {
        $photo = trim((string)($slide['photo'] ?? ''));
        $image_url = home_image_url($photo);
        if ($image_url === '') {
            continue;
        }
        $slider_items[] = [
            'image' => $image_url,
            'heading' => trim((string)($slide['heading'] ?? '')),
            'content' => trim((string)($slide['content'] ?? '')),
            'buttonText' => trim((string)($slide['button_text'] ?? '')),
            'buttonUrl' => trim((string)($slide['button_url'] ?? ''))
        ];
    }
} catch (Exception $e) {
    $slider_items = [];
}

$services = [];
if ((int)($home_settings['home_service_on_off'] ?? 0) === 1) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_service ORDER BY id ASC LIMIT 4");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $service) {
            $services[] = [
                'title' => trim((string)($service['title'] ?? '')),
                'content' => trim((string)($service['content'] ?? '')),
                'image' => home_image_url($service['photo'] ?? '')
            ];
        }
    } catch (Exception $e) {
        $services = [];
    }
}

$featured_products = [];
if ((int)($home_settings['home_featured_product_on_off'] ?? 1) === 1 && $total_featured > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_product WHERE p_is_featured=? AND p_is_active=? LIMIT " . $total_featured);
    $stmt->execute([1, 1]);
    $featured_products = array_map(fn($row) => home_product_payload($row, 'مختار'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

$latest_products = [];
if ((int)($home_settings['home_latest_product_on_off'] ?? 1) === 1 && $total_latest > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_product WHERE p_is_active=? ORDER BY p_id DESC LIMIT " . $total_latest);
    $stmt->execute([1]);
    $latest_products = array_map(fn($row) => home_product_payload($row, 'جديد'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

$popular_products = [];
if ((int)($home_settings['home_popular_product_on_off'] ?? 1) === 1 && $total_popular > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_product WHERE p_is_active=? ORDER BY p_total_view DESC LIMIT " . $total_popular);
    $stmt->execute([1]);
    $popular_products = array_map(fn($row) => home_product_payload($row, 'رائج'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

$top_categories = [];
try {
    $stmt_tcats = $pdo->query("SELECT tcat_id, tcat_name FROM tbl_top_category ORDER BY tcat_id ASC");
    foreach ($stmt_tcats->fetchAll(PDO::FETCH_ASSOC) as $tcRow) {
        $top_categories[] = [
            'id' => (int)($tcRow['tcat_id'] ?? 0),
            'name' => trim((string)($tcRow['tcat_name'] ?? '')),
            'url' => 'product-category.php?id=' . (int)($tcRow['tcat_id'] ?? 0) . '&type=top-category'
        ];
    }
} catch (Exception $e) {
    $top_categories = [];
}

$active_products_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_product WHERE p_is_active=?");
    $stmt->execute([1]);
    $active_products_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $active_products_count = count($featured_products) + count($latest_products) + count($popular_products);
}

$logo_url = home_image_url($logo ?? '');
$hero_product = $featured_products[0] ?? ($latest_products[0] ?? null);
$home_payload = [
    'store' => [
        'name' => trim((string)($meta_title_home ?: 'متجر الثقة')),
        'logo' => $logo_url,
        'fallbackLogo' => trim((string)($meta_title_home ?: 'MT')),
        'searchAction' => 'search-result.php'
    ],
    'hero' => [
        'title' => trim((string)(($home_settings['cta_title'] ?? '') ?: 'متجر الثقة')),
        'subtitle' => trim((string)(($home_settings['cta_content'] ?? '') ?: 'واجهة تسوق سريعة وواضحة، منتجات مختارة، وطلب مباشر بدون تعقيد.')),
        'ctaText' => home_clean_home_label($home_settings['cta_read_more_text'] ?? '', ''),
        'ctaUrl' => trim((string)($home_settings['cta_read_more_url'] ?? '')),
        'ctaImage' => home_image_url($home_settings['cta_photo'] ?? ''),
        'slides' => $slider_items,
        'product' => $hero_product
    ],
    'stats' => [
        ['label' => 'منتج متاح', 'value' => $active_products_count],
        ['label' => 'خدمة متجر', 'value' => count($services)]
    ],
    'categories' => $top_categories,
    'services' => $services,
    'sections' => [
        [
            'key' => 'featured',
            'eyebrow' => 'مختارات المتجر',
            'title' => home_clean_home_label($home_settings['featured_product_title'] ?? '', 'منتجات مميزة'),
            'subtitle' => home_clean_home_label($home_settings['featured_product_subtitle'] ?? '', 'أفضل العناصر المعروضة حاليا.'),
            'products' => $featured_products
        ],
        [
            'key' => 'latest',
            'eyebrow' => 'وصل حديثا',
            'title' => home_clean_home_label($home_settings['latest_product_title'] ?? '', 'أحدث المنتجات'),
            'subtitle' => home_clean_home_label($home_settings['latest_product_subtitle'] ?? '', 'كل جديد في المتجر في مكان واحد.'),
            'products' => $latest_products
        ],
        [
            'key' => 'popular',
            'eyebrow' => 'رائج بين العملاء',
            'title' => home_clean_home_label($home_settings['popular_product_title'] ?? '', 'الأكثر طلبا'),
            'subtitle' => home_clean_home_label($home_settings['popular_product_subtitle'] ?? '', 'منتجات عليها اهتمام ومشاهدات أكثر.'),
            'products' => $popular_products
        ]
    ]
];
?>

<div id="react-home-root" dir="rtl"></div>
<script id="react-home-data" type="application/json"><?php echo json_encode($home_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<script>document.getElementById('react-home-root')&&(document.getElementById('react-home-root').innerHTML='<div class="react-boot">جاري تحميل واجهة المتجر...</div>');</script>
<script src="assets/js/react.production.min.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/react.production.min.js'); ?>"></script>
<script src="assets/js/react-dom.production.min.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/react-dom.production.min.js'); ?>"></script>
<script src="assets/js/index-react-home.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/index-react-home.js'); ?>"></script>

<?php require_once('footer.php'); ?>
