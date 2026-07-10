<?php
require_once __DIR__ . '/inc/next-customer-bridge.php';
next_customer_redirect('/landing_page', ['id' => $_GET['id'] ?? $_REQUEST['id'] ?? '']);

$is_landing_page = true;
$page_meta_title = '';
$page_meta_description = '';
$page_meta_keywords = '';
$page_preload_image = '';
$page_preload_srcset = '';
$page_preload_sizes = '(max-width: 768px) 92vw, 920px';
$page_google_fonts = '';
$landing_default_title = 'BoomStore - متجر إلكتروني احترافي';
$landing_default_description = 'BoomStore متجر إلكتروني يوفر أفضل المنتجات مع تجربة تسوق سريعة وآمنة.';

if (!empty($_REQUEST['id'])) {
    require_once('admin/inc/config.php');
    require_once('admin/inc/functions.php');
    require_once('inc/delivery_cache_functions.php');
    require_once('inc/encryption.php');
    require_once('inc/site-security.php');
    require_once('inc/checkout_async_tasks.php');

    $landing_product_id = decrypt_product_id($_REQUEST['id']);
    if ($landing_product_id === false) {
        $landing_product_id = intval($_REQUEST['id']);
    }

    if ($landing_product_id > 0) {
        $stmt_meta = $pdo->prepare("SELECT p_name, p_description, p_featured_photo, landing_photo_1, landing_photo_2, landing_photo_3 FROM tbl_product WHERE p_id=?");
        $stmt_meta->execute([$landing_product_id]);
        $meta_row = $stmt_meta->fetch(PDO::FETCH_ASSOC);
        if ($meta_row) {
            $meta_name = trim($meta_row['p_name'] ?? '');
            if ($meta_name !== '') {
                $page_meta_title = $meta_name . ' - BoomStore';
            } else {
                $page_meta_title = $landing_default_title;
            }

            $meta_desc = trim(strip_tags($meta_row['p_description'] ?? ''));
            if ($meta_desc === '') {
                $meta_desc = $landing_default_description;
            } elseif (mb_strlen($meta_desc) > 160) {
                $meta_desc = mb_substr($meta_desc, 0, 160) . '...';
            }
            $page_meta_description = $meta_desc;

            $hero_photo = '';
            try {
                $stmt_hero = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? AND is_additional = 1 AND photo != '' ORDER BY id ASC LIMIT 1");
                $stmt_hero->execute([$landing_product_id]);
                $hero_photo = $stmt_hero->fetchColumn() ?: '';
            } catch (PDOException $e) {
                $hero_photo = '';
            }

            if ($hero_photo === '') {
                $hero_photo = $meta_row['landing_photo_1'] ?? '';
            }
            if ($hero_photo === '') {
                $hero_photo = $meta_row['landing_photo_2'] ?? '';
            }
            if ($hero_photo === '') {
                $hero_photo = $meta_row['landing_photo_3'] ?? '';
            }
            if ($hero_photo === '') {
                $hero_photo = $meta_row['p_featured_photo'] ?? '';
            }
            if ($hero_photo !== '') {
                if (is_external_image_url($hero_photo)) {
                    $external_srcset = [];
                    foreach ([360, 480, 640, 768, 960] as $w) {
                        $candidate = get_front_optimized_image_url($hero_photo, $w, 58);
                        if ($candidate !== '') {
                            $external_srcset[] = $candidate . ' ' . $w . 'w';
                        }
                    }
                    if (!empty($external_srcset)) {
                        $page_preload_srcset = implode(', ', array_unique($external_srcset));
                    } else {
                        $page_preload_srcset = '';
                    }
                    $page_preload_image = get_front_optimized_image_url($hero_photo, 640, 58);
                } else {
                    $hero_base = preg_replace('/\\.(jpe?g|png)$/i', '', $hero_photo);
                    if ($hero_base === $hero_photo) {
                        $hero_base = pathinfo($hero_photo, PATHINFO_FILENAME);
                    }
                    $srcset_parts = [];
                    foreach ([480, 768, 1024, 1280] as $w) {
                        $candidate = __DIR__ . '/assets/uploads/' . $hero_base . '-w' . $w . '.webp';
                        if (is_file($candidate)) {
                            $srcset_parts[] = 'assets/uploads/' . $hero_base . '-w' . $w . '.webp ' . $w . 'w';
                        }
                    }
                    if (!empty($srcset_parts)) {
                        $page_preload_srcset = implode(', ', $srcset_parts);
                        $preferred = __DIR__ . '/assets/uploads/' . $hero_base . '-w768.webp';
                        if (is_file($preferred)) {
                            $page_preload_image = 'assets/uploads/' . $hero_base . '-w768.webp';
                        } else {
                            $page_preload_image = 'assets/uploads/' . $hero_base . '-w1024.webp';
                        }
                    } else {
                        $hero_webp = preg_replace('/\\.(jpe?g|png)$/i', '.webp', $hero_photo);
                        if ($hero_webp && is_file(__DIR__ . '/assets/uploads/' . $hero_webp)) {
                            $page_preload_image = 'assets/uploads/' . $hero_webp;
                        } else {
                            $page_preload_image = get_front_optimized_image_url($hero_photo, 960, 58);
                        }
                    }
                }
            }
        }
    }
}

if ($page_meta_title === '') {
    $page_meta_title = $landing_default_title;
}
if ($page_meta_description === '') {
    $page_meta_description = $landing_default_description;
}

require_once('header.php');
?>
<link rel="stylesheet" href="assets/css/checkout.css">
<style>
/* Reset the browser's default 8px body margin so full-bleed bars
   (announcement / trust) reach the screen edges on this page. */
body { margin: 0; }

/* Never let an image overflow its column — the source stylesheet that
   constrained the landing-photo images (1200px naturals) isn't loaded,
   which caused horizontal scrolling on mobile. */
.landing-page img { max-width: 100%; height: auto; }
.landing-page { overflow-x: hidden; }

/* Center & constrain the landing content column on desktop.
   Without this, .container has no max-width and stretches to the full
   viewport (looked unprofessional / edge-to-edge on wide screens).
   Restores the intended centered sales-page column. */
.landing-page .container {
    max-width: 760px;
    margin-left: auto;
    margin-right: auto;
    padding-left: 16px;
    padding-right: 16px;
    box-sizing: border-box;
}
/* Full-bleed background bars keep their edge-to-edge background,
   only their inner .container is centered (handled by the rule above). */
.landing-page .trust-strip {
    width: 100%;
}

/* ===== Top announcement bar: professional black scrolling marquee =====
   These rules live here because the source stylesheet that defined them
   (landing_css_extracted.css) is not loaded on this page. */
.landing-page .announcement-bar {
    width: 100%;
    background: #000000;
    color: #ffffff;
    padding: 14px 0;
    margin-top: 0 !important;
    font-weight: 800;
    letter-spacing: 0.3px;
    overflow: hidden;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    min-height: 52px;
    display: flex;
    align-items: center;
}
/* Bar marquee must span the full width, not the centered 760px column. */
.landing-page .announcement-bar .container {
    max-width: 100%;
    padding-left: 0;
    padding-right: 0;
}
.landing-page .announcement-track {
    display: inline-flex;
    align-items: center;
    width: max-content;
    white-space: nowrap;
    animation: landing-announcement-scroll 20s linear infinite;
    will-change: transform;
}
/* Each identical copy carries its own leading gap, so the two copies are
   pixel-identical units and translateX(-50%) loops with no seam. */
.landing-page .announcement-item {
    font-size: 1.25rem;
    line-height: 1.4;
    padding-inline-start: 64px;
}
/* Two identical copies in the markup + moving by exactly -50% = a
   seamless infinite marquee that is never empty. */
@keyframes landing-announcement-scroll {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}

@media (max-width: 768px) {
    .landing-page .container {
        max-width: 100%;
        padding-left: 14px;
        padding-right: 14px;
    }
    .landing-page .announcement-bar {
        padding: 11px 0;
        min-height: 44px;
    }
    .landing-page .announcement-item {
        font-size: 1.02rem;
    }
    .landing-page .announcement-track {
        gap: 48px;
    }
}
</style>
<?php
// Include encryption helpers
require_once('inc/encryption.php');
require_once __DIR__ . '/inc/checkout-functions.php';
require_once __DIR__ . '/inc/incomplete-orders.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
$success_message = '';
$order_details = null;
$purchase_pixel_data = null;
$product_id = $product_name = $unit_price = null;
$announcement_text = '';
$product_delivery_mode = 'home_office';
$product_delivery_company_id = 0;

ensure_product_offer_table($pdo);

if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
    header('location: index.php');
    exit;
}

$encrypted_id = $_REQUEST['id'];
$product_id = decrypt_product_id($encrypted_id);

if ($product_id === false) {
    $product_id = intval($_REQUEST['id']);
    if ($product_id <= 0) {
        header('location: index.php');
        exit;
    }
}

try {
    $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
    $statement->execute([$product_id]);
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (empty($result)) {
        header('location: index.php');
        exit;
    }
    foreach ($result as $row) {
        $product_id = $row['p_id'];
        $product_name = $row['p_name'];
        $unit_price = floatval($row['p_current_price']);
        $p_qty = $row['p_qty'];
        $p_featured_photo = $row['p_featured_photo'];
        $old_price = $row['p_old_price'];
        $description = $row['p_description'];
        $more_description = $row['more_description'] ?? '';
        $product_template = $row['product_template'] ?? 'buy-now.php';
        $product_delivery_mode = normalize_product_delivery_mode($row['p_delivery_mode'] ?? 'home_office');
        $product_delivery_company_id = (int)($row['p_delivery_company_id'] ?? 0);
        $landing_photo_1 = $row['landing_photo_1'] ?? '';
        $landing_photo_2 = $row['landing_photo_2'] ?? '';
        $landing_photo_3 = $row['landing_photo_3'] ?? '';
        $announcement_text = $row['p_announcement'] ?? '';
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$offers = [];
$offers_js = [];
$default_offer_id = '';
try {
    $stmt_offers = $pdo->prepare("SELECT offer_id, offer_qty, offer_unit_price, offer_type, offer_description, offer_photo, is_active, is_most_popular FROM tbl_product_offer WHERE p_id = ? AND is_active = 1 ORDER BY sort_order ASC, offer_qty ASC");
    $stmt_offers->execute([$product_id]);
    $base_unit_price = floatval($unit_price);
    $special_offer_label_index = 1;
    foreach ($stmt_offers->fetchAll(PDO::FETCH_ASSOC) as $offer_row) {
        $offer_type = (string)($offer_row['offer_type'] ?? 'quantity');
        $qty = (int)$offer_row['offer_qty'];
        $unit = floatval($offer_row['offer_unit_price']);
        $is_popular = ((int)($offer_row['is_most_popular'] ?? 0) === 1);
        if ($default_offer_id === '' && $is_popular) {
            $default_offer_id = (string)(int)$offer_row['offer_id'];
        }
        if ($offer_type === 'special') {
            $qty = max(1, $qty);
            if ($unit <= 0) {
                continue;
            }

            $offer_total = $qty * $unit;
            $base_total = $qty * $base_unit_price;
            $discount = 0;
            if ($base_unit_price > 0) {
                $discount = (int)round((1 - ($unit / $base_unit_price)) * 100);
                if ($discount < 0) {
                    $discount = 0;
                }
            }

            $description_text = trim(strip_tags((string)($offer_row['offer_description'] ?? '')));
            $offers[] = [
                'id' => (int)$offer_row['offer_id'],
                'type' => 'special',
                'label' => 'عرض ' . $special_offer_label_index,
                'qty' => $qty,
                'unit' => $unit,
                'offer_total' => $offer_total,
                'base_total' => $base_total,
                'discount' => $discount,
                'description' => $description_text,
                'photo' => (string)($offer_row['offer_photo'] ?? ''),
                'is_most_popular' => $is_popular ? 1 : 0
            ];
            $offers_js[] = [
                'id' => (int)$offer_row['offer_id'],
                'type' => 'special',
                'qty' => $qty,
                'unit' => $unit,
                'photo' => (string)($offer_row['offer_photo'] ?? ''),
                'is_most_popular' => $is_popular ? 1 : 0
            ];
            $special_offer_label_index++;
            continue;
        }

        if ($qty > 0 && $unit > 0) {
            $offer_total = $qty * $unit;
            $base_total = $qty * $base_unit_price;
            $discount = 0;
            if ($base_unit_price > 0) {
                $discount = (int)round((1 - ($unit / $base_unit_price)) * 100);
                if ($discount < 0) {
                    $discount = 0;
                }
            }
            $offers[] = [
                'id' => (int)$offer_row['offer_id'],
                'type' => 'quantity',
                'label' => '',
                'qty' => $qty,
                'unit' => $unit,
                'offer_total' => $offer_total,
                'base_total' => $base_total,
                'discount' => $discount,
                'description' => '',
                'photo' => '',
                'is_most_popular' => $is_popular ? 1 : 0
            ];
            $offers_js[] = [
                'id' => (int)$offer_row['offer_id'],
                'type' => 'quantity',
                'qty' => $qty,
                'unit' => $unit,
                'is_most_popular' => $is_popular ? 1 : 0,
                'photo' => ''
            ];
        }
    }
} catch (PDOException $e) {
    error_log('Failed to load offers: ' . $e->getMessage());
}

if ($default_offer_id === '' && !empty($offers)) {
    $default_offer_id = (string)$offers[0]['id'];
}

$discount_percent = null;
if (!empty($old_price) && $old_price > 0 && $unit_price < $old_price) {
    $discount_percent = round((($old_price - $unit_price) / $old_price) * 100);
}

$short_description = '';
if (!empty($description)) {
    $short_description = trim(strip_tags($description));
    if (mb_strlen($short_description) > 180) {
        $short_description = mb_substr($short_description, 0, 180) . '...';
    }
}

// Sizes
$sizes = [];
$stmt_sizes = $pdo->prepare("SELECT size_id FROM tbl_product_size WHERE p_id = ? AND size_id != '0'");
$stmt_sizes->execute([$product_id]);
foreach ($stmt_sizes->fetchAll(PDO::FETCH_ASSOC) as $row_size) {
    $sizes[] = $row_size['size_id'];
}
$size_names = [];
if (!empty($sizes)) {
    $placeholders = implode(',', array_fill(0, count($sizes), '?'));
    $stmt_size_names = $pdo->prepare("SELECT size_id, size_name FROM tbl_size WHERE size_id IN ($placeholders) AND size_name != 'no size'");
    $stmt_size_names->execute($sizes);
    foreach ($stmt_size_names->fetchAll(PDO::FETCH_ASSOC) as $row_size) {
        $size_names[$row_size['size_id']] = $row_size['size_name'];
    }
}

// Colors
$colors = [];
$stmt_colors = $pdo->prepare("SELECT color_id FROM tbl_product_color WHERE p_id = ? AND color_id IS NOT NULL AND color_id != 0");
$stmt_colors->execute([$product_id]);
foreach ($stmt_colors->fetchAll(PDO::FETCH_ASSOC) as $row_color) {
    $colors[] = $row_color['color_id'];
}
$color_names = [];
if (!empty($colors)) {
    $placeholders = implode(',', array_fill(0, count($colors), '?'));
    $stmt_color_names = $pdo->prepare("SELECT color_id, color_name FROM tbl_color WHERE color_id IN ($placeholders)");
    $stmt_color_names->execute($colors);
    foreach ($stmt_color_names->fetchAll(PDO::FETCH_ASSOC) as $row_color) {
        $color_names[$row_color['color_id']] = $row_color['color_name'];
    }
}

$color_name_ar = [
    'red' => '????',
    'blue' => '????',
    'green' => '????',
    'black' => '????',
    'white' => '????',
    'yellow' => '????',
    'orange' => '???????',
    'purple' => '??????',
    'pink' => '????',
    'gray' => '?????',
    'grey' => '?????',
    'brown' => '???',
    'beige' => '???',
    'gold' => '????',
    'silver' => '???',
    'lightblue' => '???? ????',
    'light blue' => '???? ????',
    'violet' => '??????',
    'light purple' => '?????? ????',
    'lightpurple' => '?????? ????',
    'salmon' => '??????',
    'mixed' => '????? ??????',
    'ash' => '????? ????',
    'dark clay' => '???? ????',
    'cognac' => '??? ?????',
    'coffee' => '??? ????',
    'charcoal' => '????',
    'fuchsia' => '?????',
    'burgundy' => '?????',
    'midnight blue' => '???? ????',
    'no color' => '???? ???'
];

$color_name_css = [
    '????? ??????' => '#bdbdbd',
    '???? ????' => 'lightblue',
    '?????? ????' => 'plum',
    '??????' => 'salmon',
    '????? ????' => '#b2beb5',
    '???? ????' => '#8b5a2b',
    '??? ?????' => '#9b4f41',
    '??? ????' => '#6f4e37',
    '????' => '#36454f',
    '?????' => 'fuchsia',
    '?????' => '#800020',
    '???? ????' => 'midnightblue',
    '???? ???' => '#f2f2f2'
];

// Color photos for carousel
$color_photo_map = [];
$stmt_photos = $pdo->prepare("SELECT photo, color_id FROM tbl_product_photo WHERE p_id = ? AND color_id IS NOT NULL AND color_id != 0 AND (is_additional IS NULL OR is_additional = 0)");
$stmt_photos->execute([$product_id]);
foreach ($stmt_photos->fetchAll(PDO::FETCH_ASSOC) as $row_photo) {
    if (!empty($row_photo['photo'])) {
        $color_photo_map[$row_photo['color_id']] = $row_photo['photo'];
    }
}

// All photos (non-additional)
$all_photos = [];
if (!empty($p_featured_photo)) {
    $all_photos[] = $p_featured_photo;
}
$stmt_all_photos = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? AND (photo IS NOT NULL AND photo != '') AND (is_additional IS NULL OR is_additional = 0)");
$stmt_all_photos->execute([$product_id]);
foreach ($stmt_all_photos->fetchAll(PDO::FETCH_ASSOC) as $row_photo) {
    if (!in_array($row_photo['photo'], $all_photos, true)) {
        $all_photos[] = $row_photo['photo'];
    }
}

$other_photos = [];
foreach ($all_photos as $photo) {
    if ($photo !== $p_featured_photo) {
        $other_photos[] = $photo;
    }
}

// Additional photos (is_additional=1)
$stmt_additional = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? AND is_additional = 1");
$stmt_additional->execute([$product_id]);
$additional_photos = $stmt_additional->fetchAll(PDO::FETCH_ASSOC);

$landing_carousel_photos = [];
foreach ($additional_photos as $row) {
    $photo = $row['photo'] ?? '';
    if (!empty($photo)) {
        $landing_carousel_photos[] = $photo;
    }
}
if (empty($landing_carousel_photos)) {
    foreach ([$landing_photo_1, $landing_photo_2, $landing_photo_3] as $photo) {
        if (!empty($photo)) {
            $landing_carousel_photos[] = $photo;
        }
    }
}
$first_uploaded_photo = $landing_carousel_photos[0] ?? '';
$display_carousel_photos = [];
$seen_photos = [];
foreach ($landing_carousel_photos as $photo) {
    if ($photo !== '' && !isset($seen_photos[$photo])) {
        $display_carousel_photos[] = $photo;
        $seen_photos[$photo] = true;
    }
}
foreach ($color_photo_map as $photo) {
    if ($photo !== '' && !isset($seen_photos[$photo])) {
        $display_carousel_photos[] = $photo;
        $seen_photos[$photo] = true;
    }
}
if (empty($display_carousel_photos) && !empty($p_featured_photo)) {
    $display_carousel_photos[] = $p_featured_photo;
}
$carousel_index_map = [];
foreach ($display_carousel_photos as $idx => $photo) {
    if (!isset($carousel_index_map[$photo])) {
        $carousel_index_map[$photo] = $idx;
    }
}
$default_carousel_photo = !empty($display_carousel_photos) ? $display_carousel_photos[0] : ($p_featured_photo ?? '');

if (!function_exists('get_image_dimensions')) {
    function get_image_dimensions($relative_path, $fallback_width = 1200, $fallback_height = 1200) {
        static $cache = [];
        $key = $relative_path . '|' . $fallback_width . 'x' . $fallback_height;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $full_path = __DIR__ . '/' . ltrim($relative_path, '/');
        if (is_file($full_path)) {
            $size = @getimagesize($full_path);
            if ($size && isset($size[0], $size[1])) {
                $cache[$key] = ['width' => (int)$size[0], 'height' => (int)$size[1]];
                return $cache[$key];
            }
        }
        $cache[$key] = ['width' => (int)$fallback_width, 'height' => (int)$fallback_height];
        return $cache[$key];
    }
}

if (!function_exists('resolve_webp_src')) {
    function resolve_webp_src($photo) {
        if ($photo === '') {
            return '';
        }
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $photo);
        if ($webp && is_file(__DIR__ . '/assets/uploads/' . $webp)) {
            return 'assets/uploads/' . $webp;
        }
        return '';
    }
}

if (!function_exists('build_webp_srcset')) {
    function build_webp_srcset($photo, $widths = [480, 768, 1024, 1280]) {
        if ($photo === '') {
            return '';
        }
        $base = preg_replace('/\.(jpe?g|png)$/i', '', $photo);
        if ($base === $photo) {
            $base = pathinfo($photo, PATHINFO_FILENAME);
        }
        $srcset = [];
        foreach ($widths as $w) {
            $candidate = __DIR__ . '/assets/uploads/' . $base . '-w' . $w . '.webp';
            if (is_file($candidate)) {
                $srcset[] = 'assets/uploads/' . $base . '-w' . $w . '.webp ' . $w . 'w';
            }
        }
        if (empty($srcset)) {
            $webp = resolve_webp_src($photo);
            if ($webp !== '') {
                $dims = get_image_dimensions($webp, 1200, 1200);
                $srcset[] = $webp . ' ' . $dims['width'] . 'w';
            }
        }
        return implode(', ', $srcset);
    }
}

if (!function_exists('build_external_image_srcset')) {
    function build_external_image_srcset($photo, $widths = [360, 480, 640, 768, 960], $quality = 58) {
        $photo = trim((string)$photo);
        if ($photo === '') {
            return '';
        }

        $srcset = [];
        foreach ($widths as $w) {
            $candidate = get_front_optimized_image_url($photo, (int)$w, (int)$quality);
            if ($candidate !== '') {
                $srcset[] = $candidate . ' ' . (int)$w . 'w';
            }
        }

        if (empty($srcset)) {
            return '';
        }
        return implode(', ', array_unique($srcset));
    }
}

$carousel_photo_sizes = [];
$carousel_photo_srcsets = [];
$carousel_photo_img_srcsets = [];
$carousel_photo_optimized_urls = [];
$carousel_thumb_optimized_urls = [];
$photo_candidates = [];
foreach ($display_carousel_photos as $photo) {
    if ($photo !== '') {
        $photo_candidates[$photo] = true;
    }
}
foreach ($color_photo_map as $photo) {
    if ($photo !== '') {
        $photo_candidates[$photo] = true;
    }
}
foreach ([$landing_photo_1, $landing_photo_2, $landing_photo_3, $p_featured_photo] as $photo) {
    if (!empty($photo)) {
        $photo_candidates[$photo] = true;
    }
}
foreach (array_keys($photo_candidates) as $photo) {
    $dims = get_image_dimensions($photo, 1200, 1200);
    $carousel_photo_sizes[$photo] = ['width' => (int)$dims['width'], 'height' => (int)$dims['height']];
    $carousel_photo_srcsets[$photo] = build_webp_srcset($photo);
    $carousel_photo_img_srcsets[$photo] = build_external_image_srcset($photo, [360, 480, 640, 768, 960], 58);
    $carousel_photo_optimized_urls[$photo] = get_front_optimized_image_url($photo, 980, 58);
    $carousel_thumb_optimized_urls[$photo] = get_front_optimized_image_url($photo, 320, 60);
}
$default_carousel_dims = $carousel_photo_sizes[$default_carousel_photo] ?? ['width' => 1200, 'height' => 1200];
$landing_carousel_ratio = max(1, (int)($default_carousel_dims['width'] ?? 1200)) . ' / ' . max(1, (int)($default_carousel_dims['height'] ?? 1200));

// Shipping data: الشركة المختارة أولاً، ثم fallback إلى أي شركة بها تسعير.
$resolved_company_ids = [];
$preferred_company_id = resolve_product_delivery_company_id($pdo, $product_delivery_company_id);
if ($preferred_company_id > 0) {
    $resolved_company_ids[] = $preferred_company_id;
}
try {
    $company_stmt = $pdo->query("SELECT id FROM tbl_delivery_company ORDER BY active DESC, id ASC");
    foreach ($company_stmt->fetchAll(PDO::FETCH_ASSOC) as $company_row) {
        $candidate_id = (int)($company_row['id'] ?? 0);
        if ($candidate_id > 0 && !in_array($candidate_id, $resolved_company_ids, true)) {
            $resolved_company_ids[] = $candidate_id;
        }
    }
} catch (PDOException $e) {
    error_log('Failed to build delivery company fallback list: ' . $e->getMessage());
}

$company_id = 0;
$shipping_data = []; // Keep this for backward compatibility in backend PHP checks if any
$delivery_cache_data = ['wilayas' => [], 'communes' => [], 'desks' => []];

foreach ($resolved_company_ids as $candidate_company_id) {
    $cache_data = delivery_cache_get_frontend_data($pdo, $candidate_company_id);
    if (!empty($cache_data['wilayas'])) {
        $delivery_cache_data = $cache_data;
        $company_id = $candidate_company_id;
        
        // Build the legacy shipping_data format for backend checks
        foreach ($cache_data['wilayas'] as $w) {
            $w_name = delivery_cache_wilaya_name($w);
            if ($w_name === '') continue;
            $shipping_data[$w_name] = [];
            // We just need a sample commune to know if home/desk is supported for the whole wilaya
            if (!empty($cache_data['communes'][$w_name][0])) {
                $c = $cache_data['communes'][$w_name][0];
                if ($c['home']) $shipping_data[$w_name]['منزل'] = $c['home_price'];
                if ($c['desk']) $shipping_data[$w_name]['مكتب'] = $c['desk_price'];
            }
        }
        break;
    }
}

require_once('assets/telegram-notification.php');

$telegram_bot_token = $telegram_bot_token ?? '';
$telegram_chat_id = $telegram_chat_id ?? '';
$telegram_orders_enabled = isset($telegram_orders_enabled) ? (int)$telegram_orders_enabled : 0;
$telegram_incomplete_enabled = isset($telegram_incomplete_enabled) ? (int)$telegram_incomplete_enabled : 0;
$telegram_incomplete_bot_token = $telegram_incomplete_bot_token ?? '';
if ($telegram_incomplete_bot_token === '') {
    $telegram_incomplete_bot_token = $telegram_bot_token;
}
$telegram_incomplete_chat_id = $telegram_incomplete_chat_id ?? $telegram_chat_id;
if ($telegram_incomplete_chat_id === '') {
    $telegram_incomplete_chat_id = $telegram_chat_id;
}

$selected_color_post = $_POST['selected_color'] ?? '';

// إضافة دالة للتحقق من البيانات المرسلة
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['form1'])) {
    // التحقق من token النموذج
    if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        error_log('Order rejected - invalid form token');
        $redirect_id = '';
        if (isset($_REQUEST['id'])) {
            $redirect_id = urlencode((string)$_REQUEST['id']);
        } elseif (isset($product_id) && $product_id !== null && $product_id !== '') {
            $redirect_id = urlencode((string)$product_id);
        }
        $redirect_url = 'landing_page.php';
        if ($redirect_id !== '') {
            $redirect_url .= '?id=' . $redirect_id;
        }
        header('Location: ' . $redirect_url);
        exit;
    } else {
        // التحقق من منع الإرسال المتكرر باستخدام الجلسة
        if (!isset($_SESSION['last_order_time'])) {
            $_SESSION['last_order_time'] = 0;
        }
        
        $current_time = time();
        $last_order_time = $_SESSION['last_order_time'];
        
        // منع الإرسال المتكرر خلال 30 ثانية
        if (($current_time - $last_order_time) < 30) {
            $error_message = "يرجى الانتظار " . (30 - ($current_time - $last_order_time)) . " ثانية قبل إرسال طلب جديد";
            error_log('تم منع إرسال متكرر - الوقت المتبقي: ' . (30 - ($current_time - $last_order_time)) . ' ثانية');
        } else {
            // تحديث وقت آخر طلب وإنشاء token جديد
            $_SESSION['last_order_time'] = $current_time;
            $_SESSION['form_token'] = bin2hex(random_bytes(32)); // إنشاء token جديد
        
            error_log('=== بداية معالجة النموذج ===');
            error_log('بيانات POST: ' . print_r($_POST, true));
        
            // التحقق من الاتصال بقاعدة البيانات
            if (!checkDatabaseConnection($pdo)) {
                $error_message = "حدث خطأ في الاتصال بقاعدة البيانات";
                error_log('فشل الاتصال بقاعدة البيانات في معالجة النموذج');
            } else {
                $customer_name = $_POST['customer_name'] ?? '';
                $customer_phone = $_POST['customer_phone'] ?? '';
                $wilaya = $_POST['wilaya'] ?? '';
                $commune = $_POST['commune'] ?? '';
                
                error_log('البيانات المستخرجة: ' . json_encode([
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'wilaya' => $wilaya,
                    'commune' => $commune,
                    'product_id' => $product_id,
                    'product_name' => $product_name
                ]));

                $delivery_type = resolve_delivery_type_by_mode($_POST['delivery_type'] ?? '', $product_delivery_mode);
                $available_delivery_prices = get_available_delivery_prices_for_wilaya($shipping_data, $wilaya, $product_delivery_mode);
                if ($product_delivery_mode !== 'free' && !empty($wilaya)) {
                    $resolved = resolve_available_delivery_type_for_wilaya($shipping_data, $wilaya, $product_delivery_mode, $delivery_type);
                    if ($resolved !== '') {
                        $delivery_type = $resolved;
                    }
                }
                if ($product_delivery_mode !== 'free' && !empty($wilaya) && !empty($commune)) {
                    $delivery_context = ecotrack_resolve_order_delivery_context($pdo, ecotrack_normalize_settings(front_get_settings($pdo)), [
                        'wilaya' => $wilaya,
                        'commune' => $commune,
                        'delivery_type' => $delivery_type
                    ]);
                    $delivery_type = (string) ($delivery_context['order']['delivery_type'] ?? $delivery_type);
                }
                $_POST['delivery_type'] = $delivery_type;
                $security_check = site_security_evaluate_order($pdo, [
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'wilaya' => $wilaya,
                    'commune' => $commune,
                    'address' => $_POST['address'] ?? '',
                    'device_id' => $_POST['device_id'] ?? null
                ]);
                $customer_phone_for_security = $security_check['context']['phone'] ?? site_security_normalize_phone($customer_phone);
                if ($security_check['action'] !== 'allow') {
                    site_security_record_rejected_attempt($pdo, $security_check);
                    $error_message = $security_check['message'];
                    $_POST['customer_phone'] = $customer_phone_for_security;
                    error_log('Blocked landing order attempt before validation: ' . json_encode([
                        'action' => $security_check['action'],
                        'status' => $security_check['status'],
                        'phone' => $customer_phone_for_security,
                        'ip' => $security_check['context']['ip_address'] ?? '',
                        'device_id' => $security_check['context']['device_id'] ?? ''
                    ], JSON_UNESCAPED_UNICODE));
                }
                $selected_offer_id = $_POST['selected_offer_id'] ?? '';
                $effective_qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
                $effective_unit_price = $unit_price;
                if ($selected_offer_id !== '' && !empty($offers)) {
                    foreach ($offers as $offer) {
                        if ((string)$offer['id'] === (string)$selected_offer_id) {
                            $effective_qty = (int)$offer['qty'];
                            $effective_unit_price = (float)$offer['unit'];
                            break;
                        }
                    }
                }
                if ($effective_qty < 1) {
                    $effective_qty = 1;
                }
                $shipping_fee = 0;
                if ($product_delivery_mode !== 'free' && $delivery_type !== '' && !empty($available_delivery_prices)) {
                    $shipping_fee = (float) ($available_delivery_prices[$delivery_type] ?? 0);
                }
                $computed_total = ($effective_qty * $effective_unit_price) + $shipping_fee;


                // التحقق من البيانات المطلوبة
                if(empty($customer_name) || empty($customer_phone)) {
                    $error_message = "يرجى إدخال الاسم ورقم الهاتف";
                    error_log('خطأ: الاسم أو الهاتف فارغ');
                } else {
                    // التحقق من وجود طلبات سابقة برقم الهاتف
                    $existing_order = checkExistingOrder($pdo, $customer_phone);
                    if ($existing_order['exists']) {
                        $error_message = $existing_order['message'];
                        error_log('تم منع طلب متكرر - رقم الهاتف: ' . $customer_phone . ' - حالة الطلب: ' . $existing_order['status']);
                    } else {
                        // التحقق من صحة اسم العميل
                        $name_validation = validateCustomerName($customer_name);
                        if (!$name_validation['valid']) {
                            $error_message = $name_validation['message'];
                            error_log('خطأ في اسم العميل: ' . $name_validation['message'] . ' - الاسم: ' . $customer_name);
                        } elseif ($product_delivery_mode !== 'free' && !empty($wilaya) && !empty($commune) && empty($available_delivery_prices)) {
                            $error_message = "لا يوجد توصيل متاح لهذه الولاية عبر شركة التوصيل الحالية";
                            error_log('خطأ: لا توجد طريقة توصيل متاحة - الولاية: ' . $wilaya);
                        } elseif ($product_delivery_mode !== 'free' && !empty($wilaya) && !empty($commune) && $delivery_type === '') {
                            $error_message = "يرجى اختيار طريقة توصيل متاحة لهذه الولاية";
                            error_log('خطأ: لم يتم تحديد طريقة توصيل متاحة - الولاية: ' . $wilaya);
                        } elseif ($product_delivery_mode !== 'free' && !empty($wilaya) && !empty($commune) && $shipping_fee <= 0) {
                            $error_message = "نوع التوصيل غير متوفر لهذه الولاية (السعر غير مُدخل).";
                            error_log('خطأ: سعر التوصيل غير متوفر/0 - الولاية: ' . $wilaya . ' - النوع: ' . $delivery_type);
                        } elseif ($product_delivery_mode !== 'free' && !empty($wilaya) && !empty($commune) && !delivery_cache_validate_checkout_data($pdo, $company_id ?: $product_delivery_company_id, $wilaya, $commune, $delivery_type, $_POST['desk'] ?? ($_POST['desk_id'] ?? null))) {
                            $error_message = "بيانات التوصيل غير صحيحة، يرجى تحديث الصفحة والمحاولة مجدداً";
                            error_log("خطأ: بيانات توصيل غير صالحة من الكاش - ولاية: $wilaya, بلدية: $commune, نوع: $delivery_type");
                        } elseif (!validateAlgerianPhoneNumber($customer_phone)) {
                            $error_message = "رقم الهاتف غير صحيح. يرجى إدخال رقم هاتف جزائري صحيح (مثال: 0555123456 أو 555123456)";
                            error_log('خطأ: رقم الهاتف غير صحيح - ' . $customer_phone);
                        } else {
                            // تنسيق رقم الهاتف قبل الحفظ
                            $customer_phone = formatPhoneNumber($customer_phone);
                            // إذا تم إدخال الاسم ورقم الهاتف فقط
                            if(empty($wilaya) || empty($commune)) {
                                error_log('سيتم حفظ كطلب غير مكتمل - wilaya أو commune فارغ');
                                
                                // التحقق من وجود product_id و product_name
                                if(empty($product_id) || empty($product_name)) {
                                    error_log('خطأ: product_id أو product_name فارغ');
                                    
                                    // إظهار رسالة خطأ مع إعادة التوجيه
                                    echo '<script>
                                        document.body.innerHTML = `
                                            <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
                                                <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px;">
                                                    <h2 style="color: #721c24; margin-bottom: 15px;">❌ خطأ في بيانات المنتج</h2>
                                                    <p style="margin-bottom: 10px;">حدث خطأ في بيانات المنتج</p>
                                                    <p style="margin-bottom: 10px;">سيتم توجيهك إلى الصفحة الرئيسية خلال لحظات...</p>
                                                    <div style="margin-top: 20px;">
                                                        <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #721c24; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <style>
                                                @keyframes spin {
                                                    to { transform: rotate(360deg); }
                                                }
                                            </style>
                                        `;
                                        
                                        setTimeout(function() {
                                            window.location.href = "index.php";
                                        }, 3000);
                                </script>';
                                exit;
                            } else {
                                // حفظ كطلب غير مكتمل
                                $selected_color_id = $_POST['selected_color'] ?? '';
                                $selected_color_name = $selected_color_id;
                                if ($selected_color_id !== '' && isset($color_names[$selected_color_id])) {
                                    $selected_color_name = $color_names[$selected_color_id];
                                } elseif ($selected_color_id !== '' && ctype_digit($selected_color_id)) {
                                    $selected_color_name = '';
                                }

                                if(saveIncompleteOrder($pdo, $product_id, $product_name, $customer_name, $customer_phone, [
                                    'quantity' => $effective_qty ?? null,
                                    'unit_price' => $effective_unit_price,
                                    'total_price' => $computed_total ?? null,
                                    'selected_size' => $_POST['selected_size'] ?? '',
                                    'selected_color' => $selected_color_id,
                                    'delivery_type' => $delivery_type ?? '',
                                    'wilaya' => $wilaya ?? '',
                                    'commune' => $commune ?? ''
                                ])) {
                                    // Telegram notification for incomplete orders (when enabled)
                                    if (!empty($telegram_bot_token) && !empty($telegram_incomplete_chat_id) && !empty($telegram_incomplete_enabled)) {
                                        $telegram = new TelegramNotification($telegram_incomplete_bot_token, $telegram_incomplete_chat_id);
                                        $incompleteData = [
                                            'customer_name' => $customer_name,
                                            'customer_phone' => $customer_phone,
                                            'product_id' => $product_id,
                                            'product_name' => $product_name,
                                            'quantity' => $effective_qty ?? 1,
                                            'unit_price' => $effective_unit_price,
                                            'total_price' => $computed_total ?? '',
                                            'selected_size' => $_POST['selected_size'] ?? '',
                                            'selected_color' => $selected_color_name,
                                            'delivery_type' => $delivery_type ?? '',
                                            'wilaya' => $wilaya ?? '',
                                            'commune' => $commune ?? '',
                                            'source' => 'buy-now'
                                        ];
                                        $telegram->sendIncompleteOrderNotification($incompleteData);

                                        // Send to personally linked users via incomplete secondary bot
                                        try {
                                            require_once __DIR__ . '/admin/telegram/Services/SecondaryBotLinkService.php';
                                            if (SecondaryBotLinkService::hasDedicatedBot($pdo, 'incomplete')) {
                                                $linkedUsers = SecondaryBotLinkService::getLinkedChatIds($pdo, 'incomplete');
                                                foreach ($linkedUsers as $linked) {
                                                    $personalTelegram = new TelegramNotification($telegram_incomplete_bot_token, $linked['chat_id']);
                                                    $personalTelegram->sendIncompleteOrderNotification($incompleteData);
                                                }
                                            }
                                        } catch (Throwable $e) {}
                                    }
                                    // إرسال Pixels للطلبات غير المكتملة وإظهار رسالة نجاح
                                    echo '<script>
                                        // إرسال TikTok Pixel Event للطلبات غير المكتملة
                                        if (typeof ttq !== "undefined") {
                                            ttq.track("AddToCart", {
                                    value: ' . floatval($unit_price) . ',
                                    currency: "DZD",
                                    content_type: "product",
                                    content_name: "' . addslashes($product_name) . '",
                                    quantity: 1
                                });
                            }
                            
                            // إرسال Facebook Pixel Event للطلبات غير المكتملة
                            if (typeof fbq !== "undefined") {
                                fbq("track", "AddToCart", {
                                    value: ' . floatval($unit_price) . ',
                                    currency: "DZD",
                                    content_type: "product",
                                    content_name: "' . addslashes($product_name) . '",
                                    content_ids: ["' . $product_id . '"],
                                    num_items: 1
                                });
                            }
                            
                            // إرسال Snapchat Pixel Event
                            if (typeof trk !== "undefined") {
                                trk("track", "ADD_CART", {
                                    price: ' . floatval($unit_price) . ',
                                    currency: "DZD",
                                    content_type: "product",
                                    content_name: "' . addslashes($product_name) . '",
                                    number_items: 1
                                });
                            }
                            
                            // إرسال Google Analytics Event
                            if (typeof gtag !== "undefined") {
                                gtag("event", "add_to_cart", {
                                    value: ' . floatval($unit_price) . ',
                                    currency: "DZD",
                                    items: [{ item_id: "' . $product_id . '", item_name: "' . addslashes($product_name) . '", quantity: 1 }]
                                });
                            }
                            
                            document.body.innerHTML = `
                                <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
                                    <div style="background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px;">
                                        <h2 style="color: #155724; margin-bottom: 15px;">✅ تم حفظ معلوماتك بنجاح!</h2>
                                        <p style="margin-bottom: 10px;">يمكنك إكمال الطلب لاحقاً من خلال إدخال باقي المعلومات.</p>
                                        <p style="margin-bottom: 10px;">سيتم توجيهك إلى الصفحة الرئيسية خلال لحظات...</p>
                                        <div style="margin-top: 20px;">
                                            <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #155724; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite;"></div>
                                        </div>
                                    </div>
                                </div>
                                <style>
                                    @keyframes spin {
                                        to { transform: rotate(360deg); }
                                    }
                                </style>
                            `;
                            
                            setTimeout(function() {
                                window.location.href = "index.php";
                            }, 3000);
                        </script>';
                        exit;
                    } else {
                        error_log('فشل حفظ الطلب غير المكتمل');
                        
                        // إظهار رسالة خطأ مع إعادة التوجيه
                        echo '<script>
                            document.body.innerHTML = `
                                <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
                                    <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px;">
                                        <h2 style="color: #721c24; margin-bottom: 15px;">❌ خطأ في حفظ المعلومات</h2>
                                        <p style="margin-bottom: 10px;">حدث خطأ أثناء حفظ المعلومات</p>
                                        <p style="margin-bottom: 10px;">سيتم توجيهك إلى الصفحة الرئيسية خلال لحظات...</p>
                                        <div style="margin-top: 20px;">
                                            <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #721c24; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite;"></div>
                                        </div>
                                    </div>
                                </div>
                                <style>
                                    @keyframes spin {
                                        to { transform: rotate(360deg); }
                                    }
                                </style>
                            `;
                            
                            setTimeout(function() {
                                window.location.href = "index.php";
                            }, 3000);
                        </script>';
                        exit;
                    }
                }
            } else {
                // إكمال الطلب كالمعتاد
                site_security_ensure_order_columns($pdo);
                try {
                    $pdo->beginTransaction();
                    $security_check = site_security_reject_if_needed($pdo, [
                        'customer_name' => $_POST['customer_name'] ?? '',
                        'customer_phone' => $_POST['customer_phone'] ?? '',
                        'wilaya' => $_POST['wilaya'] ?? '',
                        'commune' => $_POST['commune'] ?? '',
                        'address' => $_POST['address'] ?? '',
                        'device_id' => $_POST['device_id'] ?? null
                    ]);
                    $_POST['customer_phone'] = $security_check['context']['phone'] ?? site_security_normalize_phone($_POST['customer_phone'] ?? '');
                    $statement = $pdo->prepare("INSERT INTO tbl_order (
                        product_id, product_name, order_size, order_color, quantity, unit_price, total_price,
                        customer_name, customer_phone, wilaya, commune, delivery_type, address,
                        customer_ip, device_id, user_agent, order_status,
                        order_date
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    
                    $statement->execute([
                        $product_id,
                        $product_name,
                        $_POST['selected_size'] ?? null,
                        $_POST['selected_color'] ?? null,
                        $effective_qty,
                        $effective_unit_price,
                        $computed_total,
                        $_POST['customer_name'],
                        $_POST['customer_phone'],
                        $_POST['wilaya'],
                        $_POST['commune'],
                        $delivery_type, // حفظ نوع التوصيل
                        $_POST['address'] ?? '',
                        $security_check['context']['ip_address'] ?? site_security_client_ip(),
                        $security_check['context']['device_id'] ?? site_security_device_id(),
                        $security_check['context']['user_agent'] ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                        'pending',
                        date('Y-m-d H:i:s')
                    ]);
                    
                    // إرسال إشعار إلى تيليجرام
                    
                    $created_order_id = (int) $pdo->lastInsertId();
                    $created_order_context = [
                        'id' => $created_order_id,
                        'customer_name' => (string) ($_POST['customer_name'] ?? ''),
                        'customer_phone' => (string) ($_POST['customer_phone'] ?? ''),
                        'product_name' => (string) $product_name,
                        'quantity' => (int) $effective_qty,
                        'total_price' => (float) $computed_total,
                        'order_status' => 'Pending',
                        'status' => 'Pending',
                        'wilaya' => (string) ($_POST['wilaya'] ?? ''),
                        'commune' => (string) ($_POST['commune'] ?? ''),
                        'address' => (string) ($_POST['address'] ?? ''),
                        'delivery_type' => (string) $delivery_type,
                        'customer_ip' => (string) ($security_check['context']['ip_address'] ?? ''),
                        'device_id' => (string) ($security_check['context']['device_id'] ?? '')
                    ];

                    $selected_color_id = $_POST['selected_color'] ?? '';
                    $selected_color_name = $selected_color_id;
                    if ($selected_color_id !== '' && isset($color_names[$selected_color_id])) {
                        $selected_color_name = $color_names[$selected_color_id];
                    } elseif ($selected_color_id !== '' && ctype_digit($selected_color_id)) {
                        $selected_color_name = '';
                    }
                    $orderData = [
                        'order_id' => $created_order_id,
                        'customer_name' => $_POST['customer_name'],
                        'customer_phone' => $_POST['customer_phone'],
                        'wilaya' => $_POST['wilaya'],
                        'commune' => $_POST['commune'],
                        'delivery_type' => $delivery_type ?? '',
                        'product_name' => $product_name,
                        'quantity' => $effective_qty,
                        'unit_price' => $effective_unit_price,
                        'total_price' => $computed_total,
                        'selected_size' => $_POST['selected_size'] ?? '',
                        'selected_color' => $selected_color_name
                    ];


                    $pdo->commit();

                    // Central Order Assignment
                    if (file_exists(__DIR__ . '/admin/inc/employee_functions.php')) {
                        require_once __DIR__ . '/admin/inc/employee_functions.php';
                        if (function_exists('assign_order_by_strategy')) {
                            assign_order_by_strategy($pdo, $created_order_id, 'landing_page');
                        }
                    }

                    checkout_dispatch_order_post_tasks([
                        'order_id' => $created_order_id,
                        'source' => 'landing_page',
                        'telegram_order_data' => $orderData,
                        'context' => $created_order_context
                    ]);
                    
                    // تخزين معلومات الطلب في الجلسة
                    $_SESSION['order_details'] = [
                        'product_name' => $product_name,
                        'quantity' => $effective_qty,
                        'total_price' => $computed_total,
                        'customer_name' => $_POST['customer_name'],
                        'order_date' => date('Y-m-d H:i:s')
                    ];
                    
                    
                    $order_details = $_SESSION['order_details'];
                    $success_message = 'Order received successfully. We will contact you shortly to confirm.';
                    $_SESSION['success_message'] = $success_message;
                    $purchase_pixel_data = [
                        'value' => $computed_total,
                        'currency' => 'DZD',
                        'content_type' => 'product',
                        'content_name' => $product_name,
                        'content_ids' => [(string)$product_id],
                        'quantity' => $effective_qty
                    ];
                    $_SESSION['purchase_pixel_data'] = $purchase_pixel_data;
                    $redirect_id = isset($_REQUEST['id']) ? urlencode((string)$_REQUEST['id']) : urlencode((string)$product_id);
                    $redirect_url = 'landing_page.php?id=' . $redirect_id . '&success=1';
                    header('Location: ' . $redirect_url);
                    exit;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("خطأ في قاعدة البيانات أثناء إكمال الطلب: " . $e->getMessage());
                    
                    // إظهار رسالة خطأ مع إعادة التوجيه
                    echo '<script>
                        document.body.innerHTML = `
                            <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
                                <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px;">
                                    <h2 style="color: #721c24; margin-bottom: 15px;">❌ حدث خطأ أثناء معالجة الطلب</h2>
                                    <p style="margin-bottom: 10px;">يرجى المحاولة مرة أخرى أو التواصل معنا.</p>
                                    <p style="margin-bottom: 10px;">سيتم توجيهك إلى الصفحة الرئيسية خلال لحظات...</p>
                                    <div style="margin-top: 20px;">
                                        <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #721c24; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite;"></div>
                                    </div>
                                </div>
                            </div>
                            <style>
                                @keyframes spin {
                                    to { transform: rotate(360deg); }
                                }
                            </style>
                        `;
                        
                        setTimeout(function() {
                            window.location.href = "index.php";
                        }, 3000);
                                    </script>';
                                    exit;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    error_log('=== نهاية معالجة النموذج ===');

}


// Show session success message (if any)
if (isset($_SESSION['success_message'])) {
    if ($success_message === '') {
        $success_message = $_SESSION['success_message'];
    }
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['order_details']) && empty($order_details)) {
    $order_details = $_SESSION['order_details'];
    unset($_SESSION['order_details']);
}
?>
<div class="page landing-page" id="top">
    <main id="main-content">
    <?php if (!empty($announcement_text)): ?>
        <div class="announcement-bar">
            <div class="container">
                <div class="announcement-track">
                    <span class="announcement-item"><?= htmlspecialchars($announcement_text) ?></span>
                    <span class="announcement-item"><?= htmlspecialchars($announcement_text) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
                    
                    

    <?php if (!empty($display_carousel_photos)): ?>
    <?php 
    $photo = $display_carousel_photos[0]; 
    $photo_url = $carousel_photo_optimized_urls[$photo] ?? get_front_optimized_image_url($photo, 980, 58);
    $photo_dims = $carousel_photo_sizes[$photo] ?? get_image_dimensions($photo, 1200, 1200);
    $photo_webp = resolve_webp_src($photo);
    $photo_webp_srcset = $carousel_photo_srcsets[$photo] ?? build_webp_srcset($photo);
    $photo_fallback_srcset = $carousel_photo_img_srcsets[$photo] ?? '';
    ?>
    <section class="landing-single-image" style="margin-top: 20px; margin-bottom: 20px;">
        <div class="container">
            <div class="landing-carousel-frame" style="background: transparent; box-shadow: none;">
                <?php if (!empty($product_name)): ?>
                    <h1 class="landing-carousel-title" style="text-align: center; font-weight: 900; margin-bottom: 15px; font-size: 1.5rem; color: #111827;"><?= htmlspecialchars($product_name) ?></h1>
                <?php endif; ?>
                <div style="width: 100%; display: flex; justify-content: center;">
                    <?php if ($photo_webp !== ''): ?>
                        <picture style="width: 100%; max-width: 800px;">
                            <source type="image/webp"
                                    srcset="<?= htmlspecialchars($photo_webp_srcset !== '' ? $photo_webp_srcset : $photo_webp) ?>"
                                    sizes="(max-width: 768px) 92vw, 800px">
                            <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($product_name) ?>"
                                 width="<?= (int)($photo_dims['width'] ?? 1200) ?>"
                                 height="<?= (int)($photo_dims['height'] ?? 1200) ?>"
                                 loading="eager"
                                 decoding="sync"
                                 style="width: 100%; height: auto; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);"
                                 <?= $photo_fallback_srcset !== '' ? 'srcset="' . htmlspecialchars($photo_fallback_srcset, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                 sizes="(max-width: 768px) 92vw, 800px">
                        </picture>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($product_name) ?>"
                             width="<?= (int)($photo_dims['width'] ?? 1200) ?>"
                             height="<?= (int)($photo_dims['height'] ?? 1200) ?>"
                             loading="eager"
                             decoding="sync"
                             style="width: 100%; height: auto; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);"
                             <?= $photo_fallback_srcset !== '' ? 'srcset="' . htmlspecialchars($photo_fallback_srcset, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                             sizes="(max-width: 768px) 92vw, 800px">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
<style>
/* Custom Layout overrides */
.order-offers-top {
    margin-bottom: 20px;
    direction: rtl;
}
.order-offers-top .offer-title {
    font-size: 1.8rem;
    font-weight: 900;
    text-align: center;
    color: #1f2937;
    margin-bottom: 15px;
}
.offer-card {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    margin-bottom: 12px;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
    direction: rtl;
}
.offer-card.selected {
    border: 1px solid #ef4444;
    background: #fffafa;
}
.offer-card .offer-select-dot {
    position: static !important;
    transform: none !important;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid #9ca3af;
    margin-left: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #fff;
}
.offer-card.selected .offer-select-dot {
    border-color: #3b82f6;
}
.offer-card.selected .offer-select-dot::after {
    content: '';
    width: 10px;
    height: 10px;
    background: #3b82f6;
    border-radius: 50%;
}
.offer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-grow: 1;
}
.offer-details {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}
.offer-qty-price, .offer-special-label {
    font-weight: 900;
    font-size: 1.7rem;
    color: #1e293b;
}
.offer-badge {
    background: #dcfce7;
    color: #166534;
    font-size: 0.95rem;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 800;
    display: inline-block;
}
.offer-price-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: left;
}
.offer-new {
    color: #ef4444;
    font-weight: 900;
    font-size: 2.2rem;
}
.offer-old {
    text-decoration: line-through;
    color: #9ca3af;
    font-size: 1.3rem;
}
.offer-popular-badge {
    position: absolute;
    top: -14px;
    left: 20px;
    background: #dc2626;
    color: white;
    font-size: 1.5rem;
    padding: 8px 24px;
    border-radius: 14px;
    font-weight: 900;
    z-index: 2;
    letter-spacing: 0.3px;
}
.offer-thumb {
    display: none !important;
}

/* Fixes for is-popular styling and price wrapping */
.offer-card.is-popular:not(.selected) {
    border-color: #e5e7eb !important;
    background: #fff !important;
    box-shadow: none !important;
}
.offer-card.is-popular:not(.selected) .offer-select-dot {
    background: #fff !important;
    box-shadow: none !important;
    border-color: #9ca3af !important;
}
.offer-card.selected, .offer-card.selected.is-popular {
    border-color: #ef4444 !important;
    background: #fffafa !important;
    box-shadow: none !important;
}
.offer-card.selected .offer-select-dot, .offer-card.selected.is-popular .offer-select-dot {
    background: #fff !important;
    box-shadow: none !important;
    border-color: #3b82f6 !important;
}
.offer-new, .offer-old {
    white-space: nowrap !important;
    display: inline-block;
}
.offer-qty-price {
    text-align: right;
    line-height: 1.4;
}
.offer-details {
    width: 100%;
}
.offer-price-stack {
    flex-shrink: 0;
    margin-right: 15px; /* Add some space between text and price */
}

/* Force wilaya + commune side by side */
.form-row {
    display: flex !important;
    flex-wrap: nowrap !important;
    gap: 10px;
    margin-bottom: 15px;
}
.form-row > .form-group {
    flex: 1 1 0 !important;
    min-width: 0 !important;
    padding: 0 !important;
    margin-bottom: 0 !important;
}
@media (max-width: 480px) {
    .form-row {
        flex-wrap: wrap !important;
    }
    .form-row > .form-group {
        flex: 1 1 100% !important;
    }
}

/* Custom Delivery Cards CSS */
.custom-delivery-grid {
    display: flex;
    gap: 12px;
    flex-direction: row-reverse; /* Since direction is RTL, row-reverse will put Home on the left and Office on the right if needed, wait. RTL puts first item on right. Image has Home on left and Office on right. */
}
.custom-del-card {
    flex: 1;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    min-height: 110px;
}
.custom-del-card.selected {
    border-color: #3b82f6;
    background: #fff; /* Keep white background as per image */
}
.del-radio-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid #9ca3af;
    margin-bottom: 10px;
    position: relative;
    background: #fff;
}
.custom-del-card.selected .del-radio-circle {
    border-color: #3b82f6;
}
.custom-del-card.selected .del-radio-circle::after {
    content: '';
    width: 10px;
    height: 10px;
    background: #3b82f6;
    border-radius: 50%;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.del-title {
    font-weight: 800;
    color: #1e3a8a; /* Dark blue as in image */
    font-size: 1.05rem;
    margin-bottom: 5px;
}
.del-subtitle {
    font-size: 0.9rem;
    font-weight: 700;
}
.del-subtitle.gray {
    color: #9ca3af;
}
.del-subtitle.green {
    color: #10b981;
}

/* Delivery Banner */
.custom-delivery-banner {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    direction: rtl;
}
.custom-delivery-banner .text {
    font-size: 1.2rem;
    color: #1e3a8a;
    line-height: 1.6;
}
.custom-delivery-banner .text strong {
    font-weight: 800;
    font-size: 1.35rem;
}
.custom-delivery-banner .icon {
    font-size: 2rem;
    margin-right: 15px;
    opacity: 0.9;
}

/* Form Styles */
.order-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    background: #fff;
}
.order-form-title {
    text-align: center;
    font-weight: 800;
    font-size: 2.2rem;
    color: #111827;
    margin-bottom: 20px;
}
.order-card-head .order-tag,
.order-card-head .product-title,
.order-card-head .rating-row,
.order-card-head .hero-price,
.order-card-head .order-now-text {
    display: none;
}
.form-group label {
    font-weight: 700;
    font-size: 0.85rem;
    color: #374151;
    margin-bottom: 8px;
}
.form-control {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 10px 12px;
    box-shadow: none;
}
.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.phone-wrapper {
    display: flex;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    overflow: hidden;
    direction: rtl;
}
.phone-wrapper .country-code {
    background: #f3f4f6;
    padding: 10px 15px;
    border-left: 1px solid #d1d5db;
    display: flex;
    align-items: center;
    font-weight: 700;
    color: #374151;
    direction: ltr;
}
.phone-wrapper .country-code img {
    width: 20px;
    margin-right: 8px;
}
.phone-wrapper input {
    border: none;
    border-radius: 0;
    width: 100%;
    padding: 10px 15px;
    direction: ltr;
    text-align: left;
}
.phone-wrapper input:focus {
    outline: none;
}

/* Delivery Options */
.delivery-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    align-items: stretch;
    margin-bottom: 20px;
}
.delivery-btn {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 10px;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
    width: 180px;
    max-width: 100%;
}
.delivery-btn.selected {
    border-color: #3b82f6;
    background: #ffffff;
}
.delivery-btn .radio-icon {
    width: 18px;
    height: 18px;
    aspect-ratio: 1 / 1;
    border-radius: 9999px;
    box-sizing: border-box;
    border: 1px solid #d1d5db;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.delivery-btn.selected .radio-icon {
    border-color: #3b82f6;
}
.delivery-btn.selected .radio-icon::after {
    content: '';
    width: 10px;
    height: 10px;
    aspect-ratio: 1 / 1;
    background: #3b82f6;
    border-radius: 9999px;
}
.delivery-btn .delivery-label {
    font-weight: 700;
    font-size: 0.9rem;
    color: #111827;
    margin-bottom: 4px;
}
.delivery-btn .delivery-desc {
    font-size: 0.8rem;
    color: #6b7280;
}

@media (max-width: 420px) {
    .delivery-options {
        justify-content: stretch;
    }
    .delivery-btn {
        width: 100%;
    }
}

/* Submit Button */
.btn-buy-now {
    background: #22c55e !important;
    color: white !important;
    font-weight: 800 !important;
    font-size: 1.2rem !important;
    padding: 16px !important;
    border-radius: 8px !important;
    width: 100% !important;
    border: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
}
.btn-buy-now:hover {
    background: #16a34a !important;
}

.privacy-note, .form-features {
    display: none !important;
}

/* Custom grid for wilaya and commune */
.form-row {
    display: flex;
    margin-left: -5px;
    margin-right: -5px;
}
.form-row > .form-group {
    padding-left: 5px;
    padding-right: 5px;
    flex: 1;
}

/* Delivery total summary under delivery options */
.delivery-info {
    display: block !important;
    background: #f8f8f8 !important;
    border: 2px solid #1f2937 !important;
    border-radius: 14px !important;
    padding: 10px 14px !important;
    margin-top: 10px !important;
}
.delivery-info .summary-row {
    padding: 8px 0 !important;
}
.delivery-info .summary-row.total {
    border-top: 1px solid rgba(0, 0, 0, 0.2) !important;
    margin-top: 6px !important;
    padding-top: 10px !important;
}
.delivery-info .summary-label {
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    color: #111827 !important;
}
.delivery-info .summary-value {
    font-size: 1.15rem !important;
    font-weight: 800 !important;
}
.delivery-info .summary-value.total-value {
    color: #10b981 !important;
}
.delivery-options-note {
    display: none !important;
}

.sticky-cta {
    position: fixed;
    bottom: calc(18px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    display: none;
}

.sticky-cta.is-visible {
    display: block;
}

.sticky-cta button {
    background: #c1121f;
    color: #ffffff;
    border: none;
    padding: 12px 26px;
    border-radius: 999px;
    font-weight: 800;
    box-shadow: 0 12px 28px rgba(193, 18, 31, 0.35);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    font-family: inherit;
}

.sticky-cta button:hover {
    background: #8f1318;
}

/* ═══ Professional order-card polish (appearance only) ═══════════════
   Fixes: card-in-card double framing, duplicate title, cramped quantity. */

/* Single clean card: flatten the inner form so it doesn't draw a 2nd box. */
.landing-page .order-card {
    border: 1px solid #eef0f3;
    border-radius: 16px;
    padding: 26px 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 12px 34px rgba(0, 0, 0, 0.05);
}
.landing-page .order-card .modern-checkout-form {
    background: transparent;
    box-shadow: none;
    border: none;
    border-radius: 0;
    padding: 0;
    margin-bottom: 0;
    overflow: visible;
}
.landing-page .order-card:hover {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 16px 40px rgba(0, 0, 0, 0.07);
}

/* One title only: keep the outer heading, drop the duplicate inside the form. */
.landing-page .modern-checkout-form .checkout-title { display: none; }
.landing-page .order-form-title {
    font-size: 1.6rem;
    margin-bottom: 4px;
}
/* Turn the hidden tagline into a subtle subtitle under the title. */
.landing-page .order-card-head .order-tag {
    display: block;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: #6b7280;
    margin: 0 0 20px;
}

/* Uniform field sizing: inputs default to content-box (→70px tall) while
   selects render border-box (→48px). Force border-box so EVERY control is
   exactly 48px, with one consistent border/radius and red focus. Padding is
   deliberately NOT set here, so select.form-control keeps its 36px chevron gap. */
.landing-page .modern-checkout-form .form-control {
    box-sizing: border-box;
    height: 48px;
    border: 1.5px solid #dde1e6;
    border-radius: 10px;
}
.landing-page .modern-checkout-form .form-control:focus {
    border-color: #c1121f;
    box-shadow: 0 0 0 3px rgba(193, 18, 31, 0.12);
}
.landing-page .modern-checkout-form .grid-2-cols { gap: 12px 12px; }
.landing-page .modern-checkout-form > .form-group,
.landing-page .modern-checkout-form > .grid-2-cols,
.landing-page .modern-checkout-form > .row {
    margin-bottom: 16px;
}

/* When the product has offers, the chosen offer defines the quantity
   (server enforces $effective_qty = offer qty), so hide the manual stepper
   — same behaviour as landing_page_2. */
.landing-page .order-card.has-offers .quantity-group { display: none; }

/* Quantity: a clean, balanced stepper instead of a cramped inline box. */
.landing-page .quantity-group {
    display: block;
    border: none;
    height: auto;
    background: transparent;
    overflow: visible;
}
.landing-page .quantity-group .qty-control {
    display: inline-flex;
    align-items: center;
    border: 1.5px solid #dde1e6;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    height: 48px;
    box-sizing: border-box;
}
.landing-page .quantity-group .qty-btn {
    width: 50px;
    height: 100%;
    border: none;
    background: #f8f9fb;
    font-size: 1.35rem;
    font-weight: 700;
    color: #374151;
    cursor: pointer;
    line-height: 1;
}
.landing-page .quantity-group .qty-btn:hover {
    background: #eef1f5;
    color: #111827;
}
.landing-page .quantity-group #quantity {
    width: 66px;
    height: 100%;
    border: none;
    border-left: 1.5px solid #dde1e6;
    border-right: 1.5px solid #dde1e6;
    border-radius: 0;
    text-align: center;
    font-size: 1.05rem;
    font-weight: 700;
    box-shadow: none;
    padding: 0;
}

/* ── Color selector: give the (otherwise unstyled) container a clean card,
   and turn the cramped circles into readable pills. ─────────────────── */
.landing-page .order-color-select {
    background: #f9fafb;
    border: 1px solid #eef0f3;
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 16px;
}
.landing-page .order-color-title {
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
    margin: 0 0 4px;
}
.landing-page .order-color-title strong { color: #c1121f; }
.landing-page .order-color-note {
    font-size: 0.85rem;
    color: #6b7280;
    line-height: 1.5;
    margin: 0 0 12px;
}
.landing-page .color-selector { gap: 10px; margin: 0; }
.landing-page .color-option {
    width: auto;
    min-width: 66px;
    height: 44px;
    border-radius: 10px;
    padding: 0 16px;
    font-size: 0.9rem;
    font-weight: 800;
    color: #111827;
    /* white halo keeps the name readable on any swatch colour (light or dark) */
    text-shadow: 0 0 3px #fff, 0 0 3px #fff, 0 0 5px #fff;
    border: 1.5px solid rgba(0, 0, 0, 0.16);
    transform: none;
}
.landing-page .color-option:hover {
    transform: none;
    border-color: #c1121f;
}
.landing-page .color-option.selected {
    transform: none;
    border-color: #c1121f;
    box-shadow: 0 0 0 3px rgba(193, 18, 31, 0.2);
}
.landing-page .next-step-hint {
    font-size: 0.82rem;
    color: #6b7280;
    text-align: center;
    margin-top: 10px;
}
.landing-page .order-color-error {
    color: #c1121f;
    font-size: 0.85rem;
    font-weight: 700;
    margin-top: 8px;
}

/* ── Delivery options: full-width, edge-aligned grid (not centred 180px
   chips), and a single consistent (red) selected state. ─────────────── */
.landing-page .delivery-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    justify-content: stretch;
    margin: 0 0 4px;
}
.landing-page .delivery-btn {
    width: auto;
    max-width: none;
    padding: 9px 10px;
    gap: 3px;
}
.landing-page .delivery-btn.selected {
    border-color: #c1121f;
    background: #fff5f5;
    box-shadow: 0 0 0 3px rgba(193, 18, 31, 0.12);
}
.landing-page .delivery-btn.selected .radio-icon { border-color: #c1121f; }
.landing-page .delivery-btn.selected .radio-icon::after { background: #c1121f; }

/* ── Make the delivery-type boxes more compact (they were too big) ── */
.landing-page .delivery-btn .radio-icon {
    width: 15px;
    height: 15px;
    margin-bottom: 4px;
}
.landing-page .delivery-btn.selected .radio-icon::after {
    width: 8px;
    height: 8px;
}
.landing-page .delivery-btn .delivery-label {
    font-size: 0.82rem;
    margin-bottom: 1px;
}
.landing-page .delivery-btn .delivery-price-tag {
    font-size: 0.85rem;
}

/* ── Slightly smaller offer boxes ── */
.landing-page .offer-card {
    padding: 11px 16px;
    margin-bottom: 10px;
}
.landing-page .offer-qty-price,
.landing-page .offer-special-label {
    font-size: 1.35rem;
}
.landing-page .offer-new { font-size: 1.7rem; }
.landing-page .offer-old { font-size: 1.05rem; }
.landing-page .offer-select-dot {
    width: 18px;
    height: 18px;
    margin-left: 12px;
}
.landing-page .offer-popular-badge {
    font-size: 1.05rem;
    padding: 5px 16px;
    top: -12px;
}

/* ── Price summary: lighter, cleaner card instead of a heavy black border. ── */
.landing-page .delivery-info {
    background: #f9fafb !important;
    border: 1px solid #eef0f3 !important;
    border-radius: 12px !important;
    padding: 14px 16px !important;
}

/* ── CTA: keep the existing JS loading/disabled behaviour, just polish states. ── */
.landing-page .btn-buy-now:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
<section class="landing-order">
        <div class="container">
            <?php if(!empty($colors)): ?>
                <div class="order-color-select" id="colorSelection">
                    <div class="order-color-title">اختر <strong>اللون</strong> لإتمام الطلب</div>
                    <div class="order-color-note">اختيار اللون يساعدنا على تجهيز طلبك بسرعة وبدقة.</div>
                    <div id="colorError" class="order-color-error" data-message="&#1575;&#1582;&#1578;&#1585; &#1575;&#1604;&#1604;&#1608;&#1606; &#1604;&#1573;&#1578;&#1605;&#1575;&#1605; &#1575;&#1604;&#1591;&#1604;&#1576;.">&#1575;&#1582;&#1578;&#1585; &#1575;&#1604;&#1604;&#1608;&#1606; &#1604;&#1573;&#1578;&#1605;&#1575;&#1605; &#1575;&#1604;&#1591;&#1604;&#1576;.</div>
                    <div class="color-selector">
                        <?php foreach($colors as $color_id): ?>
                            <?php
                                $raw_color_name = $color_names[$color_id] ?? '';
                                $display_color_name = $raw_color_name;
                                $css_color_value = $raw_color_name;
                                if ($raw_color_name !== '' && preg_match('/\p{Arabic}/u', $raw_color_name)) {
                                    if (isset($color_name_css[$raw_color_name])) {
                                        $css_color_value = $color_name_css[$raw_color_name];
                                    }
                                } else {
                                    $color_key = strtolower(trim($raw_color_name));
                                    if ($color_key !== '' && isset($color_name_ar[$color_key])) {
                                        $display_color_name = $color_name_ar[$color_key];
                                    }
                                }
                                $color_photo = $color_photo_map[$color_id] ?? $default_carousel_photo;
                                $slide_index = ($color_photo !== '' && isset($carousel_index_map[$color_photo])) ? $carousel_index_map[$color_photo] : '';
                                $is_default_color = ($selected_color_post !== '' && (int)$color_id === (int)$selected_color_post);
                            ?>
                            <button type="button" class="color-option<?= $is_default_color ? ' selected' : '' ?>" data-value="<?= $color_id ?>" data-color="<?= htmlspecialchars($css_color_value) ?>" data-photo="<?= htmlspecialchars($color_photo) ?>" data-slide-index="<?= $slide_index ?>" data-default="<?= $is_default_color ? '1' : '0' ?>" aria-pressed="<?= $is_default_color ? 'true' : 'false' ?>" style="background-color: <?= htmlspecialchars($css_color_value) ?>;">
                                <?= htmlspecialchars($display_color_name) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div id="nextStepHint" class="next-step-hint">&#1575;&#1576;&#1583;&#1571; &#1576;&#1575;&#1582;&#1578;&#1610;&#1575;&#1585; &#1575;&#1604;&#1604;&#1608;&#1606; &#1575;&#1604;&#1605;&#1591;&#1604;&#1608;&#1576;.</div>
                </div>
                <?php endif; ?>
                        <?php if (!empty($offers)): ?>
                
<div class="order-offers order-offers-top">
                    <div class="offer-title">عرض خاص لفترة محدودة 🔥</div>
                    <div class="offer-grid">
                        <?php $offer_thumb = !empty($landing_carousel_photos) ? $landing_carousel_photos[0] : ($first_uploaded_photo ?: $default_carousel_photo); ?>
                        <?php foreach ($offers as $index => $offer): ?>
                            <?php
                            $offer_card_photo = $offer['type'] === 'special' ? (!empty($offer['photo']) ? $offer['photo'] : $offer_thumb) : $offer_thumb;
                            $offer_thumb_url = '';
                            $offer_dims = ['width' => 160, 'height' => 160];
                            if (!empty($offer_card_photo)) {
                                if (is_valid_image_url($offer_card_photo)) {
                                    $offer_thumb_url = $offer_card_photo;
                                } else {
                                    $offer_thumb_url = $carousel_thumb_optimized_urls[$offer_card_photo] ?? get_front_optimized_image_url($offer_card_photo, 320, 60);
                                }
                                $offer_dims = $carousel_photo_sizes[$offer_card_photo] ?? get_image_dimensions($offer_card_photo, 160, 160);
                            }
                            ?>
                            <?php $is_default_offer = ($default_offer_id !== '' && (string)$offer['id'] === (string)$default_offer_id); ?>
                            <div class="offer-card<?= $is_default_offer ? ' selected' : '' ?><?= $offer['type'] === 'special' ? ' offer-card--special' : '' ?><?= !empty($offer['is_most_popular']) ? ' is-popular' : '' ?>" data-offer-id="<?= $offer['id'] ?>" data-offer-qty="<?= $offer['qty'] ?>" data-offer-unit="<?= $offer['unit'] ?>" data-offer-type="<?= htmlspecialchars($offer['type'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($offer['is_most_popular'])): ?>
                                    <span class="offer-popular-badge">الأكثر طلباً</span>
                                <?php endif; ?>
                                <span class="offer-select-dot" aria-hidden="true"></span>
                                <div class="offer-content">
                                    <div class="offer-details">
                                        <?php if ($offer['type'] === 'special'): ?>
                                            <div class="offer-special-label"><?= htmlspecialchars($offer['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if (!empty($offer['description'])): ?>
                                                <div class="offer-special-desc"><?= htmlspecialchars($offer['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <span class="offer-badge"><?= $offer['discount'] > 0 ? ('تخفيض ' . $offer['discount'] . '%') : 'سعر خاص' ?></span>
                                        <?php else: ?>
                                            <div class="offer-qty-price"><?= htmlspecialchars($product_name) ?> <?= $offer['qty'] ?>X</div>
                                            <span class="offer-badge">تخفيض <?= $offer['discount'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="offer-price-stack">
                                        <?php if (!empty($offer['base_total']) && $offer['base_total'] > $offer['offer_total']): ?>
                                            <span class="offer-old"><?= number_format($offer['base_total'], 0, '.', ',') ?> &#1583;&#1580;</span>
                                        <?php endif; ?>
                                        <span class="offer-new"><?= number_format($offer['offer_total'], 0, '.', ',') ?> &#1583;&#1580;</span>
                                    </div>
                                </div>
                                <div class="offer-thumb">
                                    <?php if (!empty($offer_card_photo)): ?>
                                        <img src="<?= htmlspecialchars($offer_thumb_url, ENT_QUOTES, 'UTF-8') ?>"
                                             alt="<?= htmlspecialchars($product_name) ?>"
                                             width="<?= (int)($offer_dims['width'] ?? 160) ?>"
                                             height="<?= (int)($offer_dims['height'] ?? 160) ?>"
                                             loading="lazy">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                                </div>
            <?php endif; ?>
            
            <div class="custom-delivery-banner">
                <div class="text">
                    <strong>ملاحظة هامة حول التوصيل:</strong><br>
                    التوصيل متوفر لجميع الولايات من 24 إلى 48 ساعة.
                </div>
                <div class="icon">🚚</div>
            </div>

<div id="orderFormSection" class="order-card reveal<?= !empty($offers) ? " has-offers" : "" ?>" style="--delay: 0.1s;">
                <div class="order-card-head">
                    <h2 class="order-form-title">إتمام عملية الطلب 📦</h2>
                    <span class="order-tag">أكمل طلبك خلال دقيقة</span>
                    <h2 class="product-title"><?= htmlspecialchars($product_name) ?></h2>
                    <div class="rating-row">
                        <span class="stars">★★★★★</span>
                        <span class="rating-text">(4.8)</span>
                    </div>
                    <div class="hero-price">
                        <span class="price-current"><?= htmlspecialchars($unit_price) ?> دج</span>
                        <?php if (!empty($discount_percent)): ?>
                            <span class="price-badge">خصم <?= $discount_percent ?>%</span>
                        <?php else: ?>
                            <span class="price-badge">عرض اليوم</span>
                        <?php endif; ?>
                    </div>
                    <div class="order-now-text">
                        <h3>اطلب الآن</h3>
                        <p>اترك بياناتك وسنتصل بك لتأكيد الطلب قبل الشحن.</p>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message) || !empty($order_details)): ?>
                    <?php
                        $order_date_display = '';
                        if (!empty($order_details['order_date'])) {
                            try {
                                $date = new DateTime($order_details['order_date']);
                                $order_date_display = $date->format('Y-m-d H:i');
                            } catch (Exception $e) {
                                $order_date_display = $order_details['order_date'];
                            }
                        }
                    ?>
                    <div class="order-success-banner" id="orderSuccessBanner">
                        <div class="order-success-card" role="status" aria-live="polite">
                            <div class="order-success-icon"><i class="fa fa-check-circle"></i></div>
                            <div class="order-success-title">&#1578;&#1605; &#1575;&#1587;&#1578;&#1604;&#1575;&#1605; &#1591;&#1604;&#1576;&#1603; &#1576;&#1606;&#1580;&#1575;&#1581;</div>
                            <div class="order-success-text">&#1588;&#1603;&#1585;&#1575; &#1593;&#1604;&#1609; &#1579;&#1602;&#1578;&#1603;&#1548; &#1587;&#1606;&#1578;&#1589;&#1604; &#1576;&#1603; &#1604;&#1578;&#1571;&#1603;&#1610;&#1583; &#1575;&#1604;&#1591;&#1604;&#1576; &#1602;&#1576;&#1604; &#1575;&#1604;&#1588;&#1581;&#1606;.</div>
                            <?php if (!empty($order_details)): ?>
                                <div class="order-success-grid">
                                    <?php if (!empty($order_details['customer_name'])): ?>
                                        <div class="order-success-item">
                                            <span class="label">&#1575;&#1604;&#1575;&#1587;&#1605;</span>
                                            <span class="value"><?= htmlspecialchars($order_details['customer_name']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order_details['product_name'])): ?>
                                        <div class="order-success-item">
                                            <span class="label">&#1575;&#1604;&#1605;&#1606;&#1578;&#1580;</span>
                                            <span class="value"><?= htmlspecialchars($order_details['product_name']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order_details['quantity'])): ?>
                                        <div class="order-success-item">
                                            <span class="label">&#1575;&#1604;&#1603;&#1605;&#1610;&#1577;</span>
                                            <span class="value"><?= htmlspecialchars($order_details['quantity']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order_details['total_price'])): ?>
                                        <div class="order-success-item">
                                            <span class="label">&#1575;&#1604;&#1605;&#1580;&#1605;&#1608;&#1593;</span>
                                            <span class="value"><?= number_format((float)$order_details['total_price'], 2, '.', ',') ?> &#1583;&#1580;</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order_date_display)): ?>
                                        <div class="order-success-item">
                                            <span class="label">&#1578;&#1575;&#1585;&#1610;&#1582; &#1575;&#1604;&#1591;&#1604;&#1576;</span>
                                            <span class="value"><?= htmlspecialchars($order_date_display) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="order-success-note">&#1604;&#1608; &#1578;&#1581;&#1576; &#1578;&#1593;&#1583;&#1610;&#1604; &#1575;&#1604;&#1591;&#1604;&#1576;&#1548; &#1575;&#1578;&#1589;&#1604; &#1576;&#1606;&#1575;.</div>
                            <button type="button" class="order-success-dismiss" id="orderSuccessDismiss">&#1605;&#1608;&#1575;&#1601;&#1602;</button>
                        </div>
                    </div>
                <?php endif; ?>

                


                <form action="" method="post" id="orderForm" class="modern-checkout-form">
                    <h3 class="checkout-title">إتمام عملية الطلب 📦</h3>
                    <input type="hidden" name="form1" value="1">
                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token']) ?>">
                    <?php if (!empty($offers)): ?>
                        <input type="hidden" name="selected_offer_id" id="selected_offer_id" value="<?= htmlspecialchars($default_offer_id, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    
                    <div class="grid-2-cols">
                        <div class="form-group form-field position-relative">
                            <label for="customer_name">الاسم الكامل *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required placeholder="اكتب اسمك هنا..." value="<?= isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : '' ?>">
                        </div>
                        <div class="form-group form-field position-relative">
                            <label for="customer_phone">رقم الهاتف *</label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required
                                   placeholder="06XXXXXXXX"
                                   pattern="[0-9]{9,10}"
                                   title="يرجى إدخال رقم هاتف جزائري صحيح (9-10 أرقام)"
                                   value="<?= isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : '' ?>">
                            <div id="phone-error" class="text-danger mt-1" style="display: none; font-size: 0.9rem;"></div>
                        </div>
                    </div>

                    <div class="grid-2-cols">
                        <div class="form-group">
                            <label for="wilaya">الولاية *</label>
                            <select class="form-control" id="wilaya" name="wilaya" required>
                                <option value="">اختر الولاية</option>
                                <?php foreach (array_keys($shipping_data) as $wilaya): ?>
                                    <option value="<?= htmlspecialchars($wilaya) ?>"><?= htmlspecialchars($wilaya) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="commune">البلدية *</label>
                            <select class="form-control" id="commune" name="commune" required>
                                <option value="">اختر البلدية</option>
                            </select>
                        </div>
                    </div>
                    <div class="row" id="desk_container" style="display: none;">
                        <div class="form-group col-12">
                            <label for="desk">المكتب (Stop Desk) *</label>
                            <select class="form-control" id="desk" name="desk">
                                <option value="">اختر المكتب</option>
                            </select>
                        </div>
                    </div>

                    <?php if(!empty($sizes)): ?>
                    <div class="form-group">
                        <label>المقاس *</label>
                        <div class="size-selector">
                            <?php foreach($sizes as $size_id): ?>
                                <div class="size-option" data-value="<?= $size_id ?>">
                                    <?= htmlspecialchars($size_names[$size_id]) ?>
                                </div>
                            <?php endforeach; ?>
                            <input type="hidden" name="selected_size" id="selected_size" value="">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group quantity-group">
                        <label for="quantity">الكمية *</label>
                        <div class="qty-control">
                            <button type="button" id="decreaseQuantity" class="qty-btn" aria-label="نقّص الكمية">-</button>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="<?= $p_qty ?>" required>
                            <button type="button" id="increaseQuantity" class="qty-btn" aria-label="زيّدة الكمية">+</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>نوع التوصيل *</label>
                        <div class="delivery-options">
                            <?php if ($product_delivery_mode === 'free'): ?>
                                <button type="button" class="delivery-btn selected" data-kind="free" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('free', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">توصيل مجاني</span>
                                    <span class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php elseif ($product_delivery_mode === 'home_only'): ?>
                                <button type="button" class="delivery-btn selected" data-kind="home" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">للمنزل</span>
                                    <span class="delivery-price-tag" id="homePriceBtn">0 دج</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="delivery-btn selected" data-kind="home" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">للمنزل</span>
                                    <span class="delivery-price-tag" id="homePriceBtn">0 دج</span>
                                </button>
                                <button type="button" class="delivery-btn" data-kind="office" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('office', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">للمكتب (Stop Desk)</span>
                                    <span class="delivery-price-tag" id="officePriceBtn">0 دج</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="delivery-options-note" id="deliveryOptionsNote" style="display: none;"></div>
                        <div class="delivery-info p-3 mb-3 is-hidden">
                            <div class="summary-row">
                                <span class="summary-label">سعر التوصيل</span>
                                <span id="delivery_price" class="summary-value delivery-price">0 دج</span>
                            </div>
                            <div class="summary-row total">
                                <span class="summary-label">المجموع الكلي</span>
                                <span id="total_price" class="summary-value total-value"><?= htmlspecialchars($unit_price) ?> دج</span>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="deliveryTypeInput" name="delivery_type" value="<?= htmlspecialchars(resolve_delivery_type_by_mode($_POST['delivery_type'] ?? '', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" id="total_price_hidden" name="total_price" value="<?= htmlspecialchars($unit_price) ?>">
                    <input type="hidden" name="selected_color" id="selected_color" value="<?= htmlspecialchars($selected_color_post ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-features">
                        <div class="feature-item"><i class="fa fa-shield-alt"></i> &#1590;&#1605;&#1575;&#1606;&#32;&#1575;&#1604;&#1580;&#1608;&#1583;&#1577;</div>
                        <div class="feature-item"><i class="fa fa-truck"></i> &#1578;&#1608;&#1589;&#1610;&#1604;&#32;&#1587;&#1585;&#1610;&#1593;</div>
                        <div class="feature-item"><i class="fa fa-cash-register"></i> &#1583;&#1601;&#1593;&#32;&#1593;&#1606;&#1583;&#32;&#1575;&#1604;&#1575;&#1587;&#1578;&#1604;&#1575;&#1605;</div>
                    </div>

                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    <input type="hidden" name="product_name" value="<?= htmlspecialchars($product_name) ?>">
                    <input type="hidden" name="unit_price" value="<?= $unit_price ?>">

                    <div class="order-cta">
                        <button type="submit" class="btn btn-buy-now btn-lg" name="form1">
                            🛒 تأكيد الطلب - اطلب الآن</button>
                    </div>
                    <p class="privacy-note">بياناتك محفوظة ولن تُستخدم إلا لتأكيد الطلب.</p>
                </form>
            </div>
        </div>
    </section>


    <?php if (in_array($product_template, ['landing_page.php', 'landing_page_2.php'], true) && ($landing_photo_1 || $landing_photo_2 || $landing_photo_3)): ?>
        <section class="landing-photos" id="landingPhotos">
            <div class="container">
                <div class="landing-photos-grid">
                    <?php if ($landing_photo_1): ?>
                        <figure class="landing-photo">
                            <?php
                            $landing1_url = get_front_optimized_image_url($landing_photo_1, 640, 58);
                            $landing1_dims = $carousel_photo_sizes[$landing_photo_1] ?? get_image_dimensions($landing_photo_1, 1200, 1200);
                            $landing1_webp_srcset = build_webp_srcset($landing_photo_1);
                            $landing_sizes = '(max-width: 768px) 94vw, 33vw';
                            ?>
                            <?php if ($landing1_webp_srcset !== ''): ?>
                                <picture>
                                    <source type="image/webp" srcset="<?= htmlspecialchars($landing1_webp_srcset) ?>" sizes="<?= $landing_sizes ?>">
                                    <img src="<?= htmlspecialchars($landing1_url, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($product_name) ?>"
                                         width="<?= (int)$landing1_dims['width'] ?>"
                                         height="<?= (int)$landing1_dims['height'] ?>"
                                         loading="lazy"
                                         decoding="async"
                                          sizes="<?= $landing_sizes ?>">
                                </picture>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($landing1_url, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($product_name) ?>"
                                     width="<?= (int)$landing1_dims['width'] ?>"
                                     height="<?= (int)$landing1_dims['height'] ?>"
                                     loading="lazy"
                                     decoding="async">
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>
                    <?php if ($landing_photo_2): ?>
                        <figure class="landing-photo">
                            <?php
                            $landing2_url = get_front_optimized_image_url($landing_photo_2, 640, 58);
                            $landing2_dims = $carousel_photo_sizes[$landing_photo_2] ?? get_image_dimensions($landing_photo_2, 1200, 1200);
                            $landing2_webp_srcset = build_webp_srcset($landing_photo_2);
                            $landing_sizes = '(max-width: 768px) 94vw, 33vw';
                            ?>
                            <?php if ($landing2_webp_srcset !== ''): ?>
                                <picture>
                                    <source type="image/webp" srcset="<?= htmlspecialchars($landing2_webp_srcset) ?>" sizes="<?= $landing_sizes ?>">
                                    <img src="<?= htmlspecialchars($landing2_url, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($product_name) ?>"
                                         width="<?= (int)$landing2_dims['width'] ?>"
                                         height="<?= (int)$landing2_dims['height'] ?>"
                                         loading="lazy"
                                         decoding="async"
                                          sizes="<?= $landing_sizes ?>">
                                </picture>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($landing2_url, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($product_name) ?>"
                                     width="<?= (int)$landing2_dims['width'] ?>"
                                     height="<?= (int)$landing2_dims['height'] ?>"
                                     loading="lazy"
                                     decoding="async">
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>
                    <?php if ($landing_photo_3): ?>
                        <figure class="landing-photo">
                            <?php
                            $landing3_url = get_front_optimized_image_url($landing_photo_3, 640, 58);
                            $landing3_dims = $carousel_photo_sizes[$landing_photo_3] ?? get_image_dimensions($landing_photo_3, 1200, 1200);
                            $landing3_webp_srcset = build_webp_srcset($landing_photo_3);
                            $landing_sizes = '(max-width: 768px) 94vw, 33vw';
                            ?>
                            <?php if ($landing3_webp_srcset !== ''): ?>
                                <picture>
                                    <source type="image/webp" srcset="<?= htmlspecialchars($landing3_webp_srcset) ?>" sizes="<?= $landing_sizes ?>">
                                    <img src="<?= htmlspecialchars($landing3_url, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($product_name) ?>"
                                         width="<?= (int)$landing3_dims['width'] ?>"
                                         height="<?= (int)$landing3_dims['height'] ?>"
                                         loading="lazy"
                                         decoding="async"
                                          sizes="<?= $landing_sizes ?>">
                                </picture>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($landing3_url, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($product_name) ?>"
                                     width="<?= (int)$landing3_dims['width'] ?>"
                                     height="<?= (int)$landing3_dims['height'] ?>"
                                     loading="lazy"
                                     decoding="async">
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="trust-strip">
        <div class="container trust-grid">
            <div class="trust-chip"><i class="fa fa-truck"></i> توصيل سريع 24-48 ساعة</div>
            <div class="trust-chip"><i class="fa fa-cash-register"></i> الدفع عند الاستلام</div>
            <div class="trust-chip"><i class="fa fa-undo"></i> إمكانية الاستبدال حسب الشروط</div>
            <div class="trust-chip"><i class="fa fa-check-circle"></i> فحص قبل الدفع</div>
        </div>
    </section>

    
    <section class="section steps" id="stepsSection">
        <div class="container">
            <h2 class="section-title">كيف يتم الطلب؟</h2>
            <div class="steps-grid">
                <div class="step-card reveal" style="--delay: 0s;">
                    <div class="step-index">1</div>
                    <h3>املأ النموذج</h3>
                    <p>أدخل معلوماتك بدقة لإتمام الشحن بسرعة.</p>
                </div>
                <div class="step-card reveal" style="--delay: 0.05s;">
                    <div class="step-index">2</div>
                    <h3>تأكيد سريع</h3>
                    <p>سنتواصل معك لتأكيد الطلب قبل الإرسال.</p>
                </div>
                <div class="step-card reveal" style="--delay: 0.1s;">
                    <div class="step-index">3</div>
                    <h3>توصيل آمن</h3>
                    <p>يصلك المنتج إلى باب البيت مع الدفع عند الاستلام.</p>
                </div>
            </div>
        </div>
    </section>

    
    <?php 
        $other_photos = $all_photos;
        array_shift($other_photos);
    ?>
    <?php if (!in_array($product_template, ['landing_page.php', 'landing_page_2.php'], true) && !empty($other_photos)): ?>
        <section class="section gallery">
            <div class="container">
                <h2 class="section-title">صور إضافية</h2>
                <div class="gallery-grid">
                    <?php foreach ($other_photos as $photo): ?>
                        <?php
                        $gallery_url = get_front_optimized_image_url($photo, 640, 58);
                        $gallery_dims = $carousel_photo_sizes[$photo] ?? get_image_dimensions($photo, 1200, 1200);
                        $gallery_webp_srcset = build_webp_srcset($photo);
                        $gallery_sizes = '(max-width: 768px) 94vw, 33vw';
                        ?>
                        <?php if ($gallery_webp_srcset !== ''): ?>
                            <picture>
                                <source type="image/webp" srcset="<?= htmlspecialchars($gallery_webp_srcset) ?>" sizes="<?= $gallery_sizes ?>">
                                <img src="<?= htmlspecialchars($gallery_url, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($product_name) ?>"
                                     width="<?= (int)$gallery_dims['width'] ?>"
                                     height="<?= (int)$gallery_dims['height'] ?>"
                                     loading="lazy"
                                     decoding="async"
                                     sizes="<?= $gallery_sizes ?>">
                            </picture>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($gallery_url, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($product_name) ?>"
                                 width="<?= (int)$gallery_dims['width'] ?>"
                                 height="<?= (int)$gallery_dims['height'] ?>"
                                 loading="lazy"
                                 decoding="async">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!in_array($product_template, ['landing_page.php', 'landing_page_2.php'], true) && !empty($additional_photos)): ?>
        <section class="section extra-photos">
            <div class="container">
                <h2 class="section-title">تفاصيل أقرب</h2>
                <div class="gallery-grid">
                    <?php foreach ($additional_photos as $photo): ?>
                        <?php
                            $extra_photo = $photo['photo'] ?? '';
                            $extra_url = get_front_optimized_image_url($extra_photo, 640, 58);
                            $extra_dims = $extra_photo !== '' ? get_image_dimensions($extra_photo, 1200, 1200) : ['width' => 1200, 'height' => 1200];
                        ?>
                        <img src="<?= htmlspecialchars($extra_url, ENT_QUOTES, 'UTF-8') ?>"
                             alt="صورة إضافية"
                             width="<?= (int)$extra_dims['width'] ?>"
                             height="<?= (int)$extra_dims['height'] ?>"
                             loading="lazy">
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php if (!empty($more_description)): ?>
        <section class="section details">
            <div class="container">
                <h2 class="section-title">تفاصيل إضافية</h2>
                <div class="details-card">
                    <div class="details-text"><?= nl2br(htmlspecialchars($more_description)) ?></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section faq" id="faqSection">
        <div class="container">
            <h2 class="section-title">أسئلة شائعة</h2>
            <div class="faq-grid">
                <details>
                    <summary>هل الدفع عند الاستلام متاح؟</summary>
                    <p>نعم، يمكنك الدفع عند استلام الطلب بعد التأكيد الهاتفي.</p>
                </details>
                <details>
                    <summary>كم مدة التوصيل؟</summary>
                    <p>عادةً بين 24 و48 ساعة حسب الولاية والبلدية.</p>
                </details>
                <details>
                    <summary>هل يمكن الاستبدال؟</summary>
                    <p>نعم، يوجد إمكانية للاستبدال حسب شروط المتجر وحالة المنتج.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="container final-cta-inner">
            <h2 class="section-title">جاهز تطلب الآن؟</h2>
            <p class="section-subtitle">الكميات محدودة والعرض متجدد. اطلب الآن واستفد من السعر الحالي.</p>
            <button type="button" class="btn-cta" data-scroll-order>
                <i class="fa fa-shopping-bag"></i> اطلب الآن
            </button>
        </div>
    </section>

    <div id="sticky-scroll-btn" class="sticky-cta">
        <button type="button" data-scroll-order aria-label="العودة إلى بطاقة الطلب">
            <i class="fa fa-shopping-bag"></i> اطلب الآن
        </button>
    </div>

    <script>
    (function() {
        function getOrderCard() {
            return document.getElementById('orderFormSection');
        }

        function scrollToOrderCard(event) {
            if (event) {
                event.preventDefault();
            }
            var form = getOrderCard();
            if (!form) {
                return;
            }
            var targetTop = form.getBoundingClientRect().top + window.pageYOffset - 18;
            window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
        }

        function updateStickyOrderButton() {
            var form = getOrderCard();
            var btn = document.getElementById('sticky-scroll-btn');
            if (!form || !btn) {
                return;
            }
            var rect = form.getBoundingClientRect();
            btn.classList.toggle('is-visible', rect.bottom < 0);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-scroll-order], a[href="#orderFormSection"]').forEach(function(trigger) {
                trigger.addEventListener('click', scrollToOrderCard);
            });
            updateStickyOrderButton();
        });
        window.addEventListener('scroll', updateStickyOrderButton, { passive: true });
        window.addEventListener('resize', updateStickyOrderButton);
    })();
    </script>
    </main>
</div>

<!-- Swiper CSS -->
<link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"></noscript>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var mainCarousel = document.querySelector('.landing-carousel-main');
    if (!mainCarousel || typeof Swiper !== 'function') {
        return;
    }

    var slideCount = mainCarousel.querySelectorAll('.swiper-slide').length;
    var landingSection = document.querySelector('.landing-carousel');
    if (landingSection && slideCount <= 1) {
        landingSection.classList.add('is-single');
    }
    var autoplayConfig = false;

    var thumbsCarousel = document.querySelector('.landing-carousel-thumbs');
    var thumbsSwiper = null;
    if (thumbsCarousel) {
        thumbsSwiper = new Swiper(thumbsCarousel, {
            slidesPerView: Math.min(5, slideCount),
            spaceBetween: 12,
            watchSlidesProgress: true,
            slideToClickedSlide: true,
            freeMode: true,
            breakpoints: {
                0: { slidesPerView: Math.min(3, slideCount) },
                768: { slidesPerView: Math.min(4, slideCount) },
                1200: { slidesPerView: Math.min(5, slideCount) }
            }
        });
    }

        var mainOptions = {
            slidesPerView: 1,
            spaceBetween: 16,
            loop: slideCount > 1,
            autoHeight: false,
            autoplay: autoplayConfig,
            watchOverflow: true,
            speed: 700,
            grabCursor: true,
        keyboard: {
            enabled: true
        },
        pagination: {
            el: '.landing-carousel-pagination',
            clickable: true
        },
        navigation: {
            nextEl: '.landing-carousel-next',
            prevEl: '.landing-carousel-prev'
        }
    };

    if (thumbsSwiper) {
        mainOptions.thumbs = { swiper: thumbsSwiper };
    }

    var mainSwiper = new Swiper(mainCarousel, mainOptions);
    window.landingMainSwiper = mainSwiper;
    if (thumbsSwiper) {
        window.landingThumbsSwiper = thumbsSwiper;
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.querySelector('.landing-menu-btn');
    const menuPanel = document.getElementById('landingMenu');
    const overlay = document.querySelector('.landing-menu-overlay');
    const closeItems = document.querySelectorAll('[data-menu-close]');

    if (!menuButton || !menuPanel || !overlay) {
        return;
    }

    const openMenu = () => {
        document.body.classList.add('menu-open');
        menuPanel.setAttribute('aria-hidden', 'false');
        menuPanel.removeAttribute('inert');
        menuButton.setAttribute('aria-expanded', 'true');
    };

    const closeMenu = () => {
        document.body.classList.remove('menu-open');
        menuPanel.setAttribute('aria-hidden', 'true');
        menuPanel.setAttribute('inert', '');
        menuButton.setAttribute('aria-expanded', 'false');
    };

    menuButton.addEventListener('click', function() {
        if (document.body.classList.contains('menu-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });
    overlay.addEventListener('click', closeMenu);

    closeItems.forEach(item => {
        item.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
});
</script>
<script src="assets/js/wilayas-communes.js" defer></script>
<script src="assets/js/site-security-device.js" defer></script>
<link rel="stylesheet" href="assets/css/checkout-polish.css?v=<?= filemtime(__DIR__ . '/assets/css/checkout-polish.css') ?>">

<script>window.__skipCheckoutInit = true;</script>
<script src="assets/js/checkout.js?v=<?= filemtime(__DIR__ . '/assets/js/checkout.js') ?>"></script>
<script>
const deliveryCacheData = <?= json_encode($delivery_cache_data); ?>;
const wilayasList = (deliveryCacheData.wilayas || []).map((w, index) => {
    const entry = (w && typeof w === 'object') ? w : { name: String(w || '') };
    const rawId = parseInt(entry.id || entry.wilaya_id || 0, 10);
    const id = rawId > 0 ? rawId : index + 1;
    const name = String(entry.name || entry.wilaya_name || '').trim();
    const label = (typeof window.getDeliveryLocationLabel === 'function') ? window.getDeliveryLocationLabel(entry, name) : (entry.label || name);
    return { id: String(id), code: String(id).padStart(2, '0'), name, name_ar: String(entry.name_ar || '').trim(), label };
}).filter(w => w.name);
const communesList = [];
(wilayasList || []).forEach((w) => {
    (deliveryCacheData.communes[w.name] || []).forEach(c => {
        communesList.push({ wilaya_id: String(w.id), name: c.name, id: c.name, label: c.label || c.name, name_ar: c.name_ar || '' });
    });
});

const shippingFees = <?= json_encode($shipping_data); ?>;
const offersData = <?= json_encode($offers_js); ?>;
const carouselPhotos = <?= json_encode($display_carousel_photos); ?>;
const carouselPhotoSizes = <?= json_encode($carousel_photo_sizes); ?>;
const carouselPhotoSrcsets = <?= json_encode($carousel_photo_srcsets); ?>;
const carouselPhotoImgSrcsets = <?= json_encode($carousel_photo_img_srcsets); ?>;
const carouselOptimizedPhotoUrls = <?= json_encode($carousel_photo_optimized_urls); ?>;
const carouselThumbUrls = <?= json_encode($carousel_thumb_optimized_urls); ?>;
const carouselSizes = '(max-width: 768px) 92vw, 920px';
const colorPhotoMap = <?= json_encode($color_photo_map); ?>;
const productName = <?= json_encode($product_name); ?>;
const productDeliveryMode = <?= json_encode($product_delivery_mode); ?>;
const deliveryProductId = <?= (int) $product_id; ?>;
let basePrice = <?= json_encode(floatval($unit_price)); ?>;
window.basePrice = basePrice;
window.shippingFees = shippingFees;
window.productDeliveryMode = productDeliveryMode;
window.deliveryCacheData = deliveryCacheData;
let selectedOffer = null;
let wilayaSelect = null;
let deliveryBtns = [];
let deliveryTypeInput = null;
let quantityInput = null;
let increaseBtn = null;
let decreaseBtn = null;
let offerCards = [];
let offerInput = null;
function rebuildCommuneOptions(allCommunes) {
    const communeSelect = document.getElementById('commune');
    if (!communeSelect) return;

    const previous = String(communeSelect.value || '').trim();
    communeSelect.innerHTML = '<option value="">اختر البلدية</option>';

    (allCommunes || []).forEach(function (commune) {
        const entry = (commune && typeof commune === 'object') ? commune : { name: String(commune || '') };
        const name = String(entry.name || '').trim();
        if (!name) return;
        const option = document.createElement('option');
        option.value = name;
        option.textContent = (typeof window.getDeliveryLocationLabel === 'function') ? window.getDeliveryLocationLabel(entry, name) : (entry.label || name);
        communeSelect.appendChild(option);
    });

    if (previous && Array.from(communeSelect.options).some(o => String(o.value) === previous)) {
        communeSelect.value = previous;
    } else {
        communeSelect.value = '';
    }
}


function getAvailableDeliveryOptions(wilaya, deliveryButtons) {
    const buttons = Array.isArray(deliveryButtons) ? deliveryButtons : Array.from(document.querySelectorAll('.delivery-btn'));

    if (productDeliveryMode === 'free') {
        const freeType = buttons[0] ? (buttons[0].getAttribute('data-type') || '').trim() : '';
        return freeType ? { [freeType]: 0 } : {};
    }

    if (!wilaya || !shippingFees[wilaya]) {
        return {};
    }

    const rawEntry = shippingFees[wilaya];
    if (rawEntry && typeof rawEntry === 'object' && !Array.isArray(rawEntry)) {
        const available = {};
        Object.keys(rawEntry).forEach((type) => {
            const price = Number(rawEntry[type] || 0);
            // 0 دج = غير متوفر (حسب المطلوب)
            if (price > 0) {
                available[type] = price;
            }
        });
        return available;
    }

    const fallbackType = buttons[0] ? (buttons[0].getAttribute('data-type') || '').trim() : '';
    const fallbackPrice = Number(rawEntry || 0);
    return (fallbackType && fallbackPrice > 0) ? { [fallbackType]: fallbackPrice } : {};
}

function refreshCommuneDeliveryAvailability() {
    updateDeliveryPrices();
    updateTotalPrice();
}

function updateDeliveryOptionsState() {
    const deliveryButtons = Array.from(document.querySelectorAll('.delivery-btn'));
    const note = document.getElementById('deliveryOptionsNote');
    const wilayaSelectEl = wilayaSelect || document.getElementById('wilaya');
    const communeSelect = document.getElementById('commune');
    const selectedWilaya = wilayaSelectEl ? wilayaSelectEl.value.trim() : '';
    const selectedCommune = communeSelect ? communeSelect.value.trim() : '';
    const availableOptions = getAvailableDeliveryOptions(selectedWilaya, deliveryButtons);

    const submitButton = document.querySelector('#orderForm button[type="submit"]');

    if (productDeliveryMode !== 'free' && !selectedWilaya) {
        deliveryButtons.forEach((button) => {
            button.classList.remove('is-hidden');
            button.disabled = false;
            button.style.opacity = '1';
        });
        if (!deliveryButtons.some(b => b.classList.contains('selected')) && deliveryButtons.length > 0) {
            deliveryButtons[0].classList.add('selected');
        }
        if (deliveryTypeInput && deliveryButtons[0]) {
            deliveryTypeInput.value = (document.querySelector('.delivery-btn.selected') || deliveryButtons[0]).getAttribute('data-type') || '';
        }
        if (note) {
            note.style.display = 'none';
        }
        if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
        return availableOptions;
    }

    const hasAvailable = productDeliveryMode === 'free'
        ? true
        : (!!selectedWilaya && Object.keys(availableOptions).length > 0);

    deliveryButtons.forEach((btn) => {
        const type = (btn.getAttribute('data-type') || '').trim();
        const isTypeAvailable = productDeliveryMode === 'free'
            ? true
            : (!!selectedWilaya && Object.prototype.hasOwnProperty.call(availableOptions, type));
        btn.classList.remove('is-hidden');
        btn.disabled = !isTypeAvailable;
        btn.style.opacity = !isTypeAvailable ? '0.55' : '1';
        btn.style.cursor = !isTypeAvailable ? 'not-allowed' : 'pointer';
        if (!isTypeAvailable) {
            btn.classList.remove('selected');
        }
    });

    if (submitButton) {
        submitButton.disabled = !hasAvailable;
        submitButton.style.opacity = !hasAvailable ? '0.6' : '';
        submitButton.style.cursor = !hasAvailable ? 'not-allowed' : '';
    }

    let selectedButton = deliveryButtons.find((button) => button.classList.contains('selected'));
    if (!selectedButton) {
        selectedButton = deliveryButtons.find((button) => !button.disabled) || null;
    }
    if (selectedButton) {
        selectedButton.classList.add('selected');
    }

    if (deliveryTypeInput) {
        deliveryTypeInput.value = selectedButton ? (selectedButton.getAttribute('data-type') || '') : '';
    }

    if (note) {
        if (productDeliveryMode !== 'free' && selectedWilaya && Object.keys(availableOptions).length === 0) {
            note.style.display = 'block';
            note.textContent = 'لا يوجد توصيل متاح لهذه الولاية/هذا النوع (الأسعار غير مُدخلة).';
            note.classList.add('is-error');
        } else {
            note.style.display = 'none';
        }
    }

    if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
    return availableOptions;
}

// تحديث أسعار التوصيل بجانب الأزرار
function updateDeliveryPrices() {
    const wilayaSelect = document.getElementById('wilaya');
    const communeSelect = document.getElementById('commune');
    const wilaya = wilayaSelect ? wilayaSelect.value.trim() : '';
    const commune = communeSelect ? communeSelect.value.trim() : '';
    const deliveryButtons = Array.from(document.querySelectorAll('.delivery-btn'));

    let homeSupported = true;
    let deskSupported = true;
    
    if (wilaya && typeof deliveryCacheData !== 'undefined' && deliveryCacheData.communes && deliveryCacheData.communes[wilaya]) {
        let cData = null;
        if (commune) {
            cData = deliveryCacheData.communes[wilaya].find(c => c.name === commune);
        }
        if (!cData) {
            cData = deliveryCacheData.communes[wilaya][0];
        }
        if (cData) {
            homeSupported = cData.home == 1;
            deskSupported = cData.desk == 1;
        }
    }

    deliveryButtons.forEach((button) => {
        const deliveryType = (button.getAttribute('data-type') || '').trim();
        const deliveryKind = (button.getAttribute('data-kind') || '').toLowerCase();
        const isDeskBtn = deliveryKind === 'office' || deliveryKind === 'desk' || deliveryKind === 'stopdesk' || deliveryType.toLowerCase().includes('مكتب') || deliveryType.toLowerCase().includes('office') || deliveryType.toLowerCase().includes('desk') || deliveryType.includes('Ã');
        
        let supported = true;
        if (wilaya) {
            supported = isDeskBtn ? deskSupported : homeSupported;
        }

        if (!supported) {
            button.classList.add('disabled');
            button.style.opacity = '0.5';
            button.disabled = true;
            let msgEl = button.querySelector('.not-supported-msg');
            if (!msgEl) {
                msgEl = document.createElement('div');
                msgEl.className = 'not-supported-msg text-danger mt-1';
                msgEl.style.fontSize = '12px';
                msgEl.style.fontWeight = 'bold';
                button.appendChild(msgEl);
            }
            msgEl.textContent = 'غير مدعوم في هذه المنطقة';
            
            if (button.classList.contains('selected')) {
                button.classList.remove('selected');
                const typeInput = document.getElementById('deliveryTypeInput') || document.querySelector('input[name="delivery_type"]');
                if (typeInput) typeInput.value = '';
                
                // Select first available option instead
                const firstAvailable = deliveryButtons.find(b => {
                    const t = (b.getAttribute('data-type') || '').trim();
                    const kind = (b.getAttribute('data-kind') || '').toLowerCase();
                    const dBtn = kind === 'office' || kind === 'desk' || kind === 'stopdesk' || t.toLowerCase().includes('مكتب') || t.toLowerCase().includes('office') || t.toLowerCase().includes('desk') || t.includes('Ã');
                    return dBtn ? deskSupported : homeSupported;
                });
                if (firstAvailable) {
                    firstAvailable.classList.add('selected');
                    if (typeInput) typeInput.value = firstAvailable.getAttribute('data-type');
                }
            }
        } else {
            button.classList.remove('disabled');
            button.style.opacity = '1';
            button.disabled = false;
            const msgEl = button.querySelector('.not-supported-msg');
            if (msgEl) msgEl.remove();
        }

        const priceTag = button.querySelector('.delivery-price-tag, .delivery-price');
        if (!priceTag) return;
        
        let buttonPrice = 0;
        if (wilaya && typeof deliveryCacheData !== 'undefined' && deliveryCacheData.communes && deliveryCacheData.communes[wilaya]) {
            let cData = null;
            if (commune) {
                cData = deliveryCacheData.communes[wilaya].find(c => c.name === commune);
            }
            if (!cData) cData = deliveryCacheData.communes[wilaya][0];
            
            if (cData) {
                buttonPrice = isDeskBtn ? cData.desk_price : cData.home_price;
            }
        } else if (wilaya && typeof shippingFees !== 'undefined' && shippingFees[wilaya]) {
             if (typeof shippingFees[wilaya] === 'object') {
                 buttonPrice = shippingFees[wilaya][deliveryType] || 0;
             } else {
                 buttonPrice = shippingFees[wilaya] || 0;
             }
        }

        priceTag.textContent = buttonPrice + ' دج';
    });
}


function initLandingForm() {
    wilayaSelect = document.getElementById('wilaya');
    if (wilayaSelect && typeof wilayasList !== 'undefined' && Array.isArray(wilayasList)) {
        // تعبئة الولايات بترتيب صحيح من القائمة الكاملة
        wilayaSelect.innerHTML = '<option value="">اختر الولاية</option>';
        wilayasList
          .slice()
          .sort((a, b) => parseInt(a.id) - parseInt(b.id))
          .forEach(wilaya => {
            const option = document.createElement('option');
            option.value = wilaya.name;
            option.textContent = wilaya.id + ' - ' + (wilaya.label || wilaya.name);
            wilayaSelect.appendChild(option);
          });

        // تحديث البلديات
        wilayaSelect.addEventListener('change', function () {
            const communeSelect = document.getElementById('commune');
            if (!communeSelect) return;
            
            communeSelect.innerHTML = '<option value="">اختر البلدية</option>';
            const selectedWilaya = this.value.trim();
            if (selectedWilaya && deliveryCacheData && deliveryCacheData.communes[selectedWilaya]) {
                const allCommunes = deliveryCacheData.communes[selectedWilaya];
                rebuildCommuneOptions(allCommunes);
            }
            updateDeliveryPrices();
            updateTotalPrice();
            refreshDeskOptions();
        });
    }

    const communeSelect = document.getElementById('commune');
    if (communeSelect) {
        communeSelect.addEventListener('change', function() {
            refreshCommuneDeliveryAvailability();
            refreshDeskOptions();
        });
    }
    
    const deskSelect = document.getElementById('desk');
    if (deskSelect) {
        deskSelect.addEventListener('change', function() {
            updateTotalPrice();
        });
    }

    function refreshDeskOptions() {
        const deskContainer = document.getElementById('desk_container');
        const deskSelect = document.getElementById('desk');
        const wSelect = document.getElementById('wilaya');
        const cSelect = document.getElementById('commune');
        const typeInput = document.getElementById('deliveryTypeInput');
        
        if (!deskContainer || !deskSelect || !wSelect || !cSelect || !typeInput) return;
        
        const selectedDeliveryButton = document.querySelector('.delivery-btn.selected');
        const selectedDeliveryKind = selectedDeliveryButton ? (selectedDeliveryButton.getAttribute('data-kind') || '').toLowerCase() : '';
        const isDesk = selectedDeliveryKind === 'office' || selectedDeliveryKind === 'desk' || selectedDeliveryKind === 'stopdesk' || typeInput.value === 'مكتب' || typeInput.value === 'office' || typeInput.value === 'stopdesk';
        
        if (isDesk && wSelect.value && cSelect.value) {
            const wName = wSelect.value.trim();
            const cName = cSelect.value.trim();
            
            deskSelect.innerHTML = '<option value="">اختر المكتب</option>';
            let hasDesks = false;
            
            if (deliveryCacheData && deliveryCacheData.desks && deliveryCacheData.desks[wName]) {
                const desks = deliveryCacheData.desks[wName].filter(d => d.commune === cName);
                desks.forEach(d => {
                    hasDesks = true;
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name + (d.address ? ' - ' + d.address : '');
                    deskSelect.appendChild(opt);
                });
            }
            
            if (hasDesks) {
                deskContainer.style.display = 'block';
                deskSelect.required = true;
            } else {
                // No desks for this commune but office delivery is selected? 
                // Ecotrack sometimes uses the commune itself as a stopdesk.
                deskContainer.style.display = 'none';
                deskSelect.required = false;
            }
        } else {
            deskContainer.style.display = 'none';
            deskSelect.required = false;
        }
    }

    deliveryBtns = Array.from(document.querySelectorAll('.delivery-btn'));
    deliveryTypeInput = document.getElementById('deliveryTypeInput');

    deliveryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.disabled) return; // Ignore if disabled
            deliveryBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            if (deliveryTypeInput) {
                deliveryTypeInput.value = this.getAttribute('data-type') || '';
            }
            updateTotalPrice();
            updateDeliveryStepHint(true);
            
            if (typeof refreshDeskOptions === 'function') {
                refreshDeskOptions();
            }
        });
    });

    quantityInput = document.getElementById('quantity');
    increaseBtn = document.getElementById('increaseQuantity');
    decreaseBtn = document.getElementById('decreaseQuantity');

    if (increaseBtn && quantityInput) {
        increaseBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value, 10) || 1;
            quantityInput.value = currentValue + 1;
            updateTotalPrice();
        });
    }

    if (decreaseBtn && quantityInput) {
        decreaseBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value, 10) || 1;
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
                updateTotalPrice();
            }
        });
    }

    if (quantityInput) {
        quantityInput.addEventListener('change', updateTotalPrice);
    }

    offerCards = Array.from(document.querySelectorAll('.offer-card'));
    offerInput = document.getElementById('selected_offer_id');

    if (offerCards.length) {
        offerCards.forEach(card => {
            card.addEventListener('click', function() {
                applyOfferSelection(this.getAttribute('data-offer-id'));
            });
        });
    }

    if (offersData && offersData.length) {
        offersData.forEach(function(offer) {
            if (offer && offer.photo) {
                ensureCarouselSlide(offer.photo);
            }
        });
        const preferredOffer = offersData.find(o => o && o.is_most_popular) || offersData[0];
        if (preferredOffer) {
            applyOfferSelection(preferredOffer.id);
        }
        if (increaseBtn) {
            increaseBtn.disabled = true;
        }
        if (decreaseBtn) {
            decreaseBtn.disabled = true;
        }
        if (quantityInput) {
            quantityInput.readOnly = true;
        }
    }

    if (communeSelect && communeSelect.value.trim()) {
        refreshCommuneDeliveryAvailability();
    } else {
        updateDeliveryPrices();
        updateTotalPrice();
    }
}

document.addEventListener('DOMContentLoaded', initLandingForm);

function applyOfferSelection(offerId) {
    if (!offersData || offersData.length === 0) {
        return;
    }
    const offer = offersData.find(item => String(item.id) === String(offerId));
    if (!offer) {
        return;
    }
    selectedOffer = offer;
    if (offerInput) {
        offerInput.value = offer.id;
    }
    offerCards.forEach(card => {
        const cardId = card.getAttribute('data-offer-id');
        card.classList.toggle('selected', String(cardId) === String(offer.id));
    });
    if (quantityInput) {
        quantityInput.value = offer.qty;
    }
    if (offer.photo) {
        const targetIndex = ensureCarouselSlide(offer.photo);
        if (targetIndex !== null && window.landingMainSwiper) {
            window.landingMainSwiper.update();
            if (typeof window.landingMainSwiper.slideToLoop === 'function') {
                window.landingMainSwiper.slideToLoop(targetIndex);
            } else {
                window.landingMainSwiper.slideTo(targetIndex);
            }
        }
    }
    updateTotalPrice();
}

function updateTotalPrice() {
    const quantityInput = document.getElementById('quantity');
    const wilayaSelect = document.getElementById('wilaya');
    const deliveryTypeInput = document.getElementById('deliveryTypeInput');
    if (!quantityInput || !wilayaSelect || !deliveryTypeInput) {
        console.warn('Missing required elements for pricing');
        return;
    }
    let quantity = parseInt(quantityInput.value, 10) || 1;
    const wilaya = wilayaSelect.value.trim();
    const deliveryType = deliveryTypeInput.value;
    const availableOptions = updateDeliveryOptionsState();
    let effectiveUnitPrice = basePrice;
    if (selectedOffer && selectedOffer.qty && selectedOffer.unit) {
        quantity = selectedOffer.qty;
        effectiveUnitPrice = selectedOffer.unit;
        if (quantityInput) {
            quantityInput.value = quantity;
        }
    }
    let shippingFee = 0;
    if (Object.prototype.hasOwnProperty.call(availableOptions, deliveryType)) {
        shippingFee = Number(availableOptions[deliveryType] || 0);
    }
    const total = quantity * effectiveUnitPrice + shippingFee;
    const deliveryPriceSpan = document.getElementById('delivery_price');
    if (deliveryPriceSpan) {
        deliveryPriceSpan.textContent = formatDeliveryPrice(shippingFee);
    }
    const deliveryBox = document.querySelector('.delivery-info');
    if (deliveryBox) {
        if (wilaya && Object.keys(availableOptions).length > 0 && deliveryType) {
            deliveryBox.classList.remove('is-hidden');
        } else {
            deliveryBox.classList.add('is-hidden');
        }
    }
    const totalAmountSpan = document.getElementById('total_price_btn');
    if (totalAmountSpan) {
        totalAmountSpan.textContent = total.toFixed(2) + ' \u062f\u062c';
    }
    const totalHiddenInput = document.getElementById('total_price_hidden');
    if (totalHiddenInput) {
        totalHiddenInput.value = total.toFixed(2);
    }
    const totalDisplayInput = document.getElementById('total_price');
    if (totalDisplayInput) {
        totalDisplayInput.textContent = total.toFixed(2) + ' \u062f\u062c';
    }

    const summaryProduct = document.getElementById('summary-product-price');
    if (summaryProduct) {
        summaryProduct.textContent = (quantity * effectiveUnitPrice).toFixed(2) + ' \u062f\u062c';
    }
    const summaryDelivery = document.getElementById('summary-delivery-price');
    if (summaryDelivery) {
        summaryDelivery.textContent = shippingFee === 0 ? '\u0645\u062c\u0627\u0646\u064a' : shippingFee + ' \u062f\u062c';
    }
    const summaryTotal = document.getElementById('summary-total-price');
    if (summaryTotal) {
        summaryTotal.textContent = total.toFixed(2) + ' \u062f\u062c';
    }
}


const carouselPhotoIndex = {};
if (Array.isArray(carouselPhotos)) {
    carouselPhotos.forEach((photo, index) => {
        if (photo && carouselPhotoIndex[photo] === undefined) {
            carouselPhotoIndex[photo] = index;
        }
    });
}

function ensureCarouselSlide(photo) {
    if (!photo || !window.landingMainSwiper) {
        return null;
    }
    if (carouselPhotoIndex[photo] !== undefined) {
        return carouselPhotoIndex[photo];
    }
    const size = (carouselPhotoSizes && carouselPhotoSizes[photo]) ? carouselPhotoSizes[photo] : { width: 1200, height: 1200 };
    const width = size && (size.width || size[0]) ? (size.width || size[0]) : 1200;
    const height = size && (size.height || size[1]) ? (size.height || size[1]) : 1200;
    const srcset = (carouselPhotoSrcsets && carouselPhotoSrcsets[photo]) ? carouselPhotoSrcsets[photo] : '';
    const imgSrcset = (carouselPhotoImgSrcsets && carouselPhotoImgSrcsets[photo]) ? carouselPhotoImgSrcsets[photo] : '';
    const photoUrl = (carouselOptimizedPhotoUrls && carouselOptimizedPhotoUrls[photo]) ? carouselOptimizedPhotoUrls[photo] : resolveFrontImageUrl(photo);
    const thumbUrl = (carouselThumbUrls && carouselThumbUrls[photo]) ? carouselThumbUrls[photo] : photoUrl;
    const imgTag = '<img src="' + photoUrl + '"' + (imgSrcset ? ' srcset="' + imgSrcset + '"' : '') + ' alt="' + productName + '" width="' + width + '" height="' + height + '" loading="lazy" decoding="async" sizes="' + carouselSizes + '">';
    const pictureHtml = srcset ? '<picture><source type="image/webp" srcset="' + srcset + '" sizes="' + carouselSizes + '">' + imgTag + '</picture>' : imgTag;
    const slideHtml = '<div class="swiper-slide"><div class="landing-carousel-item">' + pictureHtml + '</div></div>';
    window.landingMainSwiper.appendSlide(slideHtml);
        if (window.landingThumbsSwiper) {
            const thumbHtml = '<div class="swiper-slide"><img class="landing-carousel-thumb" src="' + thumbUrl + '" alt="' + productName + '" width="' + width + '" height="' + height + '" loading="lazy"></div>';
            window.landingThumbsSwiper.appendSlide(thumbHtml);
            window.landingThumbsSwiper.update();
        }
    const newIndex = carouselPhotos.length;
    carouselPhotos.push(photo);
    carouselPhotoIndex[photo] = newIndex;
    if (window.landingMainSwiper.params && window.landingMainSwiper.params.loop) {
        window.landingMainSwiper.loopDestroy();
        window.landingMainSwiper.loopCreate();
    }
    window.landingMainSwiper.update();
    return newIndex;
}

function parseRgbString(value) {
    const match = value.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!match) {
        return null;
    }
    return {
        r: parseInt(match[1], 10),
        g: parseInt(match[2], 10),
        b: parseInt(match[3], 10)
    };
}

function getRelativeLuminance(r, g, b) {
    const toLinear = (channel) => {
        const value = channel / 255;
        return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    };
    const red = toLinear(r);
    const green = toLinear(g);
    const blue = toLinear(b);
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function applyColorSwatchStyles() {
    const options = document.querySelectorAll('.color-option[data-color]');
    if (!options.length) {
        return;
    }
    options.forEach((option) => {
        const colorValue = option.getAttribute('data-color');
        if (!colorValue || !(window.CSS && CSS.supports && CSS.supports('color', colorValue))) {
            return;
        }
        const probe = document.createElement('span');
        probe.style.color = colorValue;
        probe.style.position = 'absolute';
        probe.style.opacity = '0';
        document.body.appendChild(probe);
        const computed = getComputedStyle(probe).color;
        document.body.removeChild(probe);
        const rgb = parseRgbString(computed);
        if (!rgb) {
            return;
        }
        const luminance = getRelativeLuminance(rgb.r, rgb.g, rgb.b);
        if (luminance > 0.6) {
            option.style.color = '#111111';
            option.style.textShadow = '0 1px 1px rgba(255, 255, 255, 0.35)';
        } else {
            option.style.color = '#ffffff';
            option.style.textShadow = '0 1px 2px rgba(0, 0, 0, 0.35)';
        }
    });
}

applyColorSwatchStyles();

const nextStepHint = document.getElementById('nextStepHint');
const nextStepOffers = document.querySelector('.order-offers-top') || document.querySelector('.order-offers');
const nextStepOrderSection = document.getElementById('orderFormSection');
const nextStepQuantityGroup = document.querySelector('.quantity-group');
const nextStepDeliveryOptions = document.querySelector('.delivery-options');
const nextStepCta = document.querySelector('.order-cta');
const hasOffers = document.querySelectorAll('.offer-card').length > 0;
const colorSelection = document.getElementById('colorSelection');
const selectedColorField = document.getElementById('selected_color');
if (colorSelection && selectedColorField && !selectedColorField.value.trim()) {
    colorSelection.classList.add('color-attention');
}

function resolveFrontImageUrl(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return '';
    }
    if (/^(?:https?:)?\/\//i.test(raw)) {
        return raw;
    }
    return 'assets/uploads/' + raw.replace(/^[/\\]+/, '');
}

function flashStep(element) {
    if (!element) {
        return;
    }
    element.classList.remove('step-highlight');
    void element.offsetWidth;
    element.classList.add('step-highlight');
}

function updateColorStepHint(colorName, shouldHighlight = true) {
    if (!nextStepHint) {
        return;
    }
    const safeColorName = (colorName || '').trim();
    if (!safeColorName) {
        return;
    }
    if (hasOffers && nextStepOffers) {
        nextStepHint.textContent = `تم اختيار اللون: ${safeColorName}. الخطوة التالية: اختر العرض المناسب ثم أكمل بياناتك.`;
        if (shouldHighlight) {
            flashStep(nextStepOffers);
        }
        return;
    }
    nextStepHint.textContent = `تم اختيار اللون: ${safeColorName}. الخطوة التالية: أكمل بياناتك ثم اختر الكمية ونوع التوصيل.`;
    if (shouldHighlight) {
        flashStep(nextStepOrderSection || nextStepQuantityGroup);
        flashStep(nextStepDeliveryOptions);
    }
}

function updateDeliveryStepHint(shouldHighlight = true) {
    if (!nextStepHint) {
        return;
    }
    nextStepHint.textContent = 'تم اختيار نوع التوصيل. الخطوة الأخيرة: اضغط زر تأكيد الطلب.';
    if (shouldHighlight) {
        flashStep(nextStepCta);
    }
}

document.querySelectorAll('.color-option').forEach(option => {
    option.addEventListener('click', function () {
        document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
        this.classList.add('selected');
        document.querySelectorAll('.color-option').forEach(el => el.setAttribute('aria-pressed', 'false'));
        this.setAttribute('aria-pressed', 'true');

        const colorId = this.getAttribute('data-value');
        const selectedColor = document.getElementById('selected_color');
        if (selectedColor) {
            selectedColor.value = colorId || '';
        }

        const slideIndexRaw = this.getAttribute('data-slide-index');
        const mappedPhoto = colorId && colorPhotoMap && colorPhotoMap[colorId] ? colorPhotoMap[colorId] : '';
        const photo = mappedPhoto || this.getAttribute('data-photo') || '';
        let targetIndex = null;

        if (slideIndexRaw) {
            const parsedIndex = parseInt(slideIndexRaw, 10);
            if (!isNaN(parsedIndex)) {
                targetIndex = parsedIndex;
            }
        } else if (photo) {
            targetIndex = ensureCarouselSlide(photo);
        }

        if (targetIndex !== null && window.landingMainSwiper) {
            window.landingMainSwiper.update();
            if (typeof window.landingMainSwiper.slideToLoop === 'function') {
                window.landingMainSwiper.slideToLoop(targetIndex);
            } else {
                window.landingMainSwiper.slideTo(targetIndex);
            }
        }

        clearColorError();
        if (colorSelection) {
            colorSelection.classList.remove('color-attention');
        }
        if (pendingColorSubmit) {
            pendingColorSubmit = false;
            const formEl = document.getElementById('orderForm');
            if (formEl) {
                if (typeof formEl.requestSubmit === 'function') {
                    formEl.requestSubmit();
                } else {
                    formEl.submit();
                }
            }
        }

        updateColorStepHint(this.textContent, true);
    });
});

function initDefaultColorSelection() {
    const selectedColorInput = document.getElementById('selected_color');
    const selectedValue = selectedColorInput ? selectedColorInput.value.trim() : '';
    if (!selectedValue) {
        return;
    }
    const selectedBtn = document.querySelector(`.color-option[data-value="${selectedValue}"]`) || document.querySelector('.color-option.selected');
    if (!selectedBtn) {
        return;
    }
    selectedBtn.classList.add('selected');
    selectedBtn.setAttribute('aria-pressed', 'true');
    if (colorSelection) {
        colorSelection.classList.remove('color-attention');
    }
    const mappedPhoto = selectedValue && colorPhotoMap && colorPhotoMap[selectedValue] ? colorPhotoMap[selectedValue] : '';
    const photo = mappedPhoto || selectedBtn.getAttribute('data-photo') || '';
    if (photo && carouselPhotoIndex[photo] === undefined) {
        ensureCarouselSlide(photo);
    }

    updateColorStepHint(selectedBtn.textContent, false);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDefaultColorSelection);
} else {
    initDefaultColorSelection();
}
// Size selection
document.querySelectorAll('.size-option').forEach(option => {
    option.addEventListener('click', function () {
        document.querySelectorAll('.size-option').forEach(el => el.classList.remove('selected'));
        this.classList.add('selected');
        const selectedSize = document.getElementById('selected_size');
        if (selectedSize) {
            selectedSize.value = this.getAttribute('data-value') || '';
        }
    });
});

// تبديل الصورة الرئيسية بالنقر على الصور المصغرة
document.querySelectorAll('.thumb-img').forEach(img => {
    img.addEventListener('click', function () {
        const mainImg = document.getElementById('main-product-image');
        const photo = this.getAttribute('data-photo');
        mainImg.src = resolveFrontImageUrl(photo);
    });
});

// تحريك الصور المصغرة تلقائياً
let currentThumbIndex = 0;
const thumbImages = document.querySelectorAll('.thumb-img');
const mainImage = document.getElementById('main-product-image');
const thumbContainer = document.querySelector('.d-flex.justify-content-center.gap-3');

if (thumbImages.length && mainImage) {
    function autoRotateThumbs() {
        thumbImages.forEach(thumb => thumb.classList.remove('selected-thumb'));
        thumbImages[currentThumbIndex].classList.add('selected-thumb');
        const photo = thumbImages[currentThumbIndex].getAttribute('data-photo');
        mainImage.src = resolveFrontImageUrl(photo);
        currentThumbIndex = (currentThumbIndex + 1) % thumbImages.length;
    }

    let thumbInterval = setInterval(autoRotateThumbs, 3000);

    if (thumbContainer) {
        thumbContainer.addEventListener('mouseenter', () => {
            clearInterval(thumbInterval);
        });

        thumbContainer.addEventListener('mouseleave', () => {
            thumbInterval = setInterval(autoRotateThumbs, 3000);
        });
    }
}

// عند تغيير الكمية، حدث السعر الإجمالي
let isSubmitting = false;
let lastClickTime = 0;
const CLICK_DELAY = 1000; // تأخير ثانية واحدة بين الضغطات

let pendingColorSubmit = false;
const colorError = document.getElementById('colorError');
function showColorError() {
    if (!colorError) {
        return;
    }
    if (colorError.dataset && colorError.dataset.message) {
        colorError.textContent = colorError.dataset.message;
    }
    colorError.classList.add('is-visible');
}
function clearColorError() {
    if (!colorError) {
        return;
    }
    colorError.classList.remove('is-visible');
}


// إضافة معالج نموذج الطلب مع منع الضغط المتكرر المحسن
var orderSubmitGuardForm = document.getElementById('orderForm');
if (orderSubmitGuardForm) {
orderSubmitGuardForm.addEventListener('submit', function(e) {
    const currentTime = Date.now();
    if (currentTime - lastClickTime < CLICK_DELAY) {
        e.preventDefault();
        alert('يرجى الانتظار ثانية واحدة قبل المحاولة مرة أخرى');
        return false;
    }
    
    if (isSubmitting) {
        e.preventDefault();
        alert('جاري إرسال الطلب، يرجى الانتظار...');
        return false;
    }
    
    // تحديث وقت آخر ضغطة
    const colorSection = document.getElementById('colorSelection');
    if (colorSection) {
        const selectedColorValue = document.getElementById('selected_color')?.value.trim();
        if (!selectedColorValue) {
            e.preventDefault();
            pendingColorSubmit = true;
            showColorError();
            flashStep(colorSection);
            return false;
        }
    }

    lastClickTime = currentTime;
    
    // الحصول على زر الإرسال
    const submitButton = this.querySelector('button[type="submit"]');
    
    // التحقق من أن الزر لم يتم تعطيله مسبقاً
    if (submitButton.disabled) {
        e.preventDefault();
        return false;
    }
    
    // تعيين حالة الإرسال
    isSubmitting = true;
    
    // تعطيل الزر فوراً لمنع الضغط المتكرر
    submitButton.disabled = true;
    submitButton.style.opacity = '0.6';
    submitButton.style.cursor = 'not-allowed';
    
    // تغيير نص الزر لإظهار حالة التحميل
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري الإرسال...';
    
    // إضافة رسالة للمستخدم
    const statusDiv = document.createElement('div');
    statusDiv.id = 'submit-status';
    statusDiv.style.cssText = 'background: rgba(193, 18, 31, 0.12); color: #000000; padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center;';
    statusDiv.innerHTML = 'جاري إرسال الطلب، يرجى عدم إغلاق الصفحة...';
    submitButton.parentNode.insertBefore(statusDiv, submitButton);
    
    // السماح للنموذج بالإرسال العادي (بدون preventDefault)
    // هذا سيضمن إرسال النموذج مرة واحدة فقط
});
}

// تم إزالة تحذير beforeunload لتفادي رسالة المتصفح أثناء إرسال الطلب

// إعادة تعيين حالة الإرسال عند تحميل الصفحة (في حالة إعادة التوجيه)
window.addEventListener('load', function() {
    isSubmitting = false;
});

// إضافة تنسيقات للرسالة
const style = document.createElement('style');
style.textContent = `
    .swal2-title-arabic {
        font-family: 'Cairo', sans-serif !important;
        font-size: 2.5rem !important;
        color: #000000 !important;
        margin-bottom: 1rem !important;
    }
    
    .swal2-content-arabic {
        font-family: 'Cairo', sans-serif !important;
        font-size: 1.2rem !important;
        color: rgba(0, 0, 0, 0.65) !important;
    }
    
    .swal2-confirm-button-arabic {
        font-family: 'Cairo', sans-serif !important;
        font-size: 1.2rem !important;
        padding: 12px 40px !important;
        border-radius: 10px !important;
        margin-top: 1rem !important;
    }
    
    .swal2-popup-arabic {
        border-radius: 20px !important;
        padding: 2rem !important;
        max-width: 500px !important;
    }
    
    .success-message {
        text-align: center;
        padding: 1rem;
    }
    
    .success-message h3 {
        color: #c1121f;
        font-size: 1.8rem;
        margin-bottom: 1rem;
    }
    
    .success-message p {
        color: rgba(0, 0, 0, 0.65);
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }
    
    .order-details {
        background: rgba(0, 0, 0, 0.04);
        padding: 1rem;
        border-radius: 10px;
        margin-top: 1rem;
    }
    
    .order-details p {
        margin: 0.5rem 0;
        font-size: 1rem;
    }
    
    .order-details strong {
        color: #000000;
        margin-left: 0.5rem;
    }
    
    .swal2-icon {
        transform: scale(1.2) !important;
        margin-bottom: 1rem !important;
    }
`;
document.head.appendChild(style);

const landingPixelProduct = {
    id: <?php echo json_encode((string)$product_id); ?>,
    content_name: <?php echo json_encode((string)$product_name); ?>,
    unit_price: <?php echo json_encode((float)$unit_price); ?>,
    currency: 'DZD'
};

function buildLandingPixelPayload(useCurrentTotal) {
    const quantity = Math.max(1, parseInt(document.getElementById('quantity')?.value || '1', 10) || 1);
    const currentTotal = parseFloat(document.getElementById('total_price_hidden')?.value || '');
    const value = useCurrentTotal && !Number.isNaN(currentTotal) && currentTotal > 0
        ? currentTotal
        : (landingPixelProduct.unit_price * quantity);

    return {
        id: landingPixelProduct.id,
        content_ids: [landingPixelProduct.id],
        content_name: landingPixelProduct.content_name,
        content_type: 'product',
        currency: landingPixelProduct.currency,
        quantity: quantity,
        num_items: quantity,
        value: value
    };
}

window.addEventListener('load', function() {
    if (typeof window.__trackViewContent === 'function') {
        window.__trackViewContent(buildLandingPixelPayload(false), {
            dedupeKey: 'landing-view-content-<?php echo (int)$product_id; ?>'
        });
    }

    // Just.ad / partners may validate against custom TikTok event names.
    // We mirror ViewContent into common partner names (once) to help validation succeed.
    try {
        if (window.ttq && typeof window.ttq.track === 'function') {
            const payload = buildLandingPixelPayload(false);
            const baseKey = 'landing-partner-view-<?php echo (int)$product_id; ?>';
            window.__pixelEventCache = window.__pixelEventCache || {};
            if (!window.__pixelEventCache[baseKey + ':ON_WEB_DETAIL']) {
                window.__pixelEventCache[baseKey + ':ON_WEB_DETAIL'] = true;
                window.ttq.track('ON_WEB_DETAIL', payload);
            }
            if (!window.__pixelEventCache[baseKey + ':SHOPPING']) {
                window.__pixelEventCache[baseKey + ':SHOPPING'] = true;
                window.ttq.track('SHOPPING', payload);
            }
        }
    } catch (e) {}
});

// حفظ الطلب غير المكتمل عند مغادرة الصفحة
let orderCompleted = false;
const orderSuccessFlag = <?php echo (!empty($success_message) || !empty($order_details) || isset($_GET['success'])) ? 'true' : 'false'; ?>;
if (orderSuccessFlag) {
    orderCompleted = true;
}


// عند إرسال النموذج بنجاح نعتبر الطلب مكتمل
const orderForm = document.getElementById('orderForm');
if(orderForm) {
    const fireLandingInitiateCheckout = function() {
        if (typeof window.__trackInitiateCheckout === 'function') {
            window.__trackInitiateCheckout(buildLandingPixelPayload(true), {
                dedupeKey: 'landing-initiate-checkout-<?php echo (int)$product_id; ?>'
            });
        }

        // Mirror to partner/custom TikTok event name that some connectors validate against.
        try {
            if (window.ttq && typeof window.ttq.track === 'function') {
                window.__pixelEventCache = window.__pixelEventCache || {};
                const key = 'landing-initiate-order-<?php echo (int)$product_id; ?>';
                if (!window.__pixelEventCache[key]) {
                    window.__pixelEventCache[key] = true;
                    window.ttq.track('INITIATE_ORDER', buildLandingPixelPayload(true));
                }
            }
        } catch (e) {}
    };

    orderForm.addEventListener('focusin', fireLandingInitiateCheckout, { once: true });
    orderForm.addEventListener('pointerdown', fireLandingInitiateCheckout, { once: true });
    orderForm.addEventListener('change', fireLandingInitiateCheckout, { once: true });
    orderForm.addEventListener('submit', function() {
        fireLandingInitiateCheckout();
        orderCompleted = true;
    });
}

function isReloadNavigation() {
    try {
        if (performance.getEntriesByType) {
            const entries = performance.getEntriesByType('navigation');
            if (entries && entries.length) {
                return entries[0].type === 'reload';
            }
        }
        if (performance.navigation) {
            return performance.navigation.type === 1;
        }
    } catch (err) {
        return false;
    }
    return false;
}

function resetOrderFormFields() {
    const form = document.getElementById('orderForm');
    if (!form) {
        return;
    }
    const nameInput = document.getElementById('customer_name');
    const phoneInput = document.getElementById('customer_phone');
    if (nameInput) nameInput.value = '';
    if (phoneInput) phoneInput.value = '';

    const wilayaSelect = document.getElementById('wilaya');
    const communeSelect = document.getElementById('commune');
    if (wilayaSelect) wilayaSelect.selectedIndex = 0;
    if (communeSelect) communeSelect.selectedIndex = 0;

    const selectedSizeInput = document.getElementById('selected_size');
    if (selectedSizeInput) selectedSizeInput.value = '';
    document.querySelectorAll('.size-option.selected').forEach(el => el.classList.remove('selected'));

    const selectedColorInput = document.getElementById('selected_color');
    if (selectedColorInput) selectedColorInput.value = '';
    document.querySelectorAll('.color-option').forEach(el => {
        el.classList.remove('selected');
        el.setAttribute('aria-pressed', 'false');
    });
    if (typeof clearColorError === 'function') {
        clearColorError();
    }

    const quantityInput = document.getElementById('quantity');
    if (quantityInput) quantityInput.value = 1;

    if (typeof applyOfferSelection === 'function' && Array.isArray(offersData) && offersData.length) {
        applyOfferSelection(offersData[0].id);
    }

    const deliveryInput = document.getElementById('deliveryTypeInput');
    const selectedDelivery = document.querySelector('.delivery-btn.selected') || document.querySelector('.delivery-btn');
    if (deliveryInput && selectedDelivery) {
        deliveryInput.value = selectedDelivery.getAttribute('data-type') || deliveryInput.value;
    }

    if (typeof updateTotalPrice === 'function') {
        updateTotalPrice();
    } else {
        const totalHiddenInput = document.getElementById('total_price_hidden');
        if (totalHiddenInput && typeof basePrice !== 'undefined') {
            totalHiddenInput.value = basePrice;
        }
        const totalDisplayInput = document.getElementById('total_price');
        if (totalDisplayInput && typeof basePrice !== 'undefined') {
            totalDisplayInput.textContent = parseFloat(basePrice).toFixed(2) + ' دج';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (isReloadNavigation()) {
        resetOrderFormFields();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const dismissBtn = document.getElementById('orderSuccessDismiss');
    const banner = document.getElementById('orderSuccessBanner');
    if (banner) {
        const page = document.querySelector('.page.landing-page');
        if (page) {
            const announcement = page.querySelector('.announcement-bar');
            if (announcement && announcement.nextElementSibling !== banner) {
                announcement.after(banner);
            } else if (!announcement && page.firstElementChild !== banner) {
                page.insertBefore(banner, page.firstElementChild);
            }
        }
    }
    if (dismissBtn && banner) {
        dismissBtn.addEventListener('click', function() {
            banner.style.display = 'none';
        });
    }
});

window.addEventListener('beforeunload', function (e) {
    if(orderCompleted || isSubmitting) return;
    const name = document.getElementById('customer_name')?.value.trim();
    const phone = document.getElementById('customer_phone')?.value.trim();
    const wilaya = document.getElementById('wilaya')?.value.trim();
    const commune = document.getElementById('commune')?.value.trim();
    if(name && phone) {
        const productId = <?php echo (int)$product_id; ?>;
        const productName = <?php echo json_encode($product_name); ?>;
        const unitPrice = <?php echo json_encode($unit_price); ?>;
        const quantity = document.getElementById('quantity')?.value || '';
        const selectedSize = document.getElementById('selected_size')?.value || '';
        const selectedColor = document.getElementById('selected_color')?.value || '';
        const deliveryType = document.getElementById('deliveryTypeInput')?.value || '';
        const totalPrice = document.getElementById('total_price_hidden')?.value || '';

        const data = new URLSearchParams({
            customer_name: name,
            customer_phone: phone
        });
        data.append('product_id', productId);
        data.append('product_name', productName);
        data.append('unit_price', unitPrice);
        if(quantity) data.append('quantity', quantity);
        if(selectedSize) data.append('selected_size', selectedSize);
        if(selectedColor) data.append('selected_color', selectedColor);
        if(deliveryType) data.append('delivery_type', deliveryType);
        if(totalPrice) data.append('total_price', totalPrice);
        if(wilaya) data.append('wilaya', wilaya);
        if(commune) data.append('commune', commune);
        if(window.siteDeviceId) data.append('device_id', window.siteDeviceId);
        data.append('source', 'landing');
        navigator.sendBeacon('save-incomplete-order.php', data);
    }
});

</script>


