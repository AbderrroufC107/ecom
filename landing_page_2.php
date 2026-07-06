<?php
require_once __DIR__ . '/inc/next-customer-bridge.php';
next_customer_redirect('/landing_page_2', ['id' => $_GET['id'] ?? $_REQUEST['id'] ?? '']);

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
<?php
// Include encryption helpers
require_once('inc/encryption.php');
require_once __DIR__ . '/inc/incomplete-orders.php';
require_once __DIR__ . '/inc/checkout-functions.php';

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
try {
    $stmt_offers = $pdo->prepare("SELECT offer_id, offer_qty, offer_unit_price, offer_type, offer_description, offer_photo, is_active FROM tbl_product_offer WHERE p_id = ? AND is_active = 1 ORDER BY sort_order ASC, offer_qty ASC");
    $stmt_offers->execute([$product_id]);
    $base_unit_price = floatval($unit_price);
    $special_offer_label_index = 1;
    foreach ($stmt_offers->fetchAll(PDO::FETCH_ASSOC) as $offer_row) {
        $offer_type = (string)($offer_row['offer_type'] ?? 'quantity');
        $qty = (int)$offer_row['offer_qty'];
        $unit = floatval($offer_row['offer_unit_price']);
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
                'photo' => (string)($offer_row['offer_photo'] ?? '')
            ];
            $offers_js[] = [
                'id' => (int)$offer_row['offer_id'],
                'type' => 'special',
                'qty' => $qty,
                'unit' => $unit,
                'photo' => (string)($offer_row['offer_photo'] ?? '')
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
                'photo' => ''
            ];
            $offers_js[] = [
                'id' => (int)$offer_row['offer_id'],
                'type' => 'quantity',
                'qty' => $qty,
                'unit' => $unit,
                'photo' => ''
            ];
        }
    }
} catch (PDOException $e) {
    error_log('Failed to load offers: ' . $e->getMessage());
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
        $redirect_url = 'landing_page_2.php';
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
                    $delivery_type = resolve_available_delivery_type_for_wilaya($shipping_data, $wilaya, $product_delivery_mode, $delivery_type);
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
                    error_log('Blocked landing 2 order attempt before validation: ' . json_encode([
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
                            assign_order_by_strategy($pdo, $created_order_id, 'landing_page_2');
                        }
                    }

                    checkout_dispatch_order_post_tasks([
                        'order_id' => $created_order_id,
                        'source' => 'landing_page_2',
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
                    $redirect_url = 'landing_page_2.php?id=' . $redirect_id . '&success=1';
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
<style>
:root {
    --font-display: "Changa", "Cairo", sans-serif;
    --font-banner: "Tajawal", "Changa", sans-serif;
    --font-body: "Cairo", sans-serif;
    --color-ink: #171717;
    --color-muted: rgba(23, 23, 23, 0.62);
    --color-accent: #c1121f;
    --color-accent-dark: #8f1318;
    --color-accent-soft: rgba(193, 18, 31, 0.12);
    --color-announcement: #ffd43b;
    --color-announcement-ink: #1f1a00;
    --color-success: #2ecc71;
    --color-sand: #ffffff;
    --color-sky: #ffffff;
    --color-card: #ffffff;
    --color-border: rgba(0, 0, 0, 0.12);
    --radius-lg: 22px;
    --radius-md: 16px;
    --shadow-soft: 0 20px 60px rgba(0, 0, 0, 0.12);
    --shadow-card: 0 12px 30px rgba(0, 0, 0, 0.1);
}

html,
body {
    max-width: 100%;
    overflow-x: hidden !important;
}

.landing-page,
.landing-page *,
.landing-page *::before,
.landing-page *::after {
    box-sizing: border-box;
}

.landing-page {
    direction: rtl;
    font-family: var(--font-body);
    color: var(--color-ink);
    background: #f7f7f6;
    width: 100%;
    max-width: 100vw;
    overflow-x: hidden;
    padding-bottom: 28px;
}

.landing-page .container {
    width: min(960px, calc(100vw - 32px));
    max-width: 100%;
    margin: 0 auto;
    padding-inline: 0;
}

.landing-page img,
.landing-page picture {
    max-width: 100%;
}

.landing-page section,
.landing-page form,
.landing-page .landing-carousel,
.landing-page .landing-order,
.landing-page .trust-strip,
.landing-page .section,
.landing-page .landing-photos,
.landing-page .final-cta {
    max-width: 100%;
    overflow-x: clip;
}

.announcement-bar {
    background: var(--color-announcement);
    color: var(--color-announcement-ink);
    padding: 9px 0;
    overflow: hidden;
    border-bottom: 1px solid rgba(31, 26, 0, 0.16);
}

.announcement-track {
    display: flex;
    white-space: nowrap;
    animation: scrollAnnouncement 18s linear infinite;
}

.announcement-item {
    font-size: 1.18rem;
    font-weight: 800;
    padding: 0 40px;
    flex-shrink: 0;
}

@keyframes scrollAnnouncement {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.landing-header {
    padding: 18px 0 24px;
}

.landing-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    direction: rtl;
}

.landing-menu-inline {
    display: none;
    align-items: center;
    gap: 16px;
    font-family: var(--font-display);
    font-weight: 600;
    flex: 1;
    justify-content: center;
    order: 1;
}

.landing-menu-inline a {
    text-decoration: none;
    color: var(--color-ink);
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid transparent;
    transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.landing-menu-inline a:hover,
.landing-menu-inline a:focus-visible {
    background: var(--color-ink);
    color: #ffffff;
    border-color: var(--color-ink);
    transform: translateY(-1px);
}

.landing-menu-btn {
    width: 44px;
    height: 44px;
    border: 2px solid var(--color-ink);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
    flex-shrink: 0;
    order: 2;
    cursor: pointer;
    padding: 0;
    appearance: none;
    outline: none;
}

.landing-menu-icon {
    position: relative;
    width: 18px;
    height: 2px;
    background: var(--color-ink);
}

.landing-menu-icon::before,
.landing-menu-icon::after {
    content: "";
    position: absolute;
    left: 0;
    width: 18px;
    height: 2px;
    background: var(--color-ink);
}

.landing-menu-icon::before { top: -6px; }
.landing-menu-icon::after { top: 6px; }

.landing-menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
    z-index: 9990;
}

.landing-menu-panel {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: min(320px, 80vw);
    background: #ffffff;
    border-right: 2px solid var(--color-ink);
    transform: translateX(-100%);
    transition: transform 0.25s ease;
    z-index: 10000;
    padding: 20px 18px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: var(--font-display);
    direction: rtl;
    text-align: right;
}

.landing-menu-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--color-ink);
}

.landing-menu-close {
    width: 36px;
    height: 36px;
    border: 2px solid var(--color-ink);
    border-radius: 10px;
    background: #ffffff;
    color: var(--color-ink);
    font-size: 20px;
    line-height: 1;
    display: grid;
    place-items: center;
    cursor: pointer;
    padding: 0;
    appearance: none;
}

.landing-menu-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 10px;
    font-size: 1rem;
}

.landing-menu-list a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: var(--color-ink);
    border: 2px solid var(--color-ink);
    border-radius: 12px;
    padding: 10px 12px;
    background: #ffffff;
    transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.landing-menu-list a:hover {
    background: var(--color-accent);
    color: #ffffff;
    transform: translateX(2px);
}

body.menu-open { overflow: hidden; }
body.menu-open .landing-menu-overlay { opacity: 1; pointer-events: auto; }
body.menu-open .landing-menu-panel { transform: translateX(0); }

.landing-logo {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.85);
    order: 0;
    flex-shrink: 0;
    padding: 6px 12px;
    border-radius: 14px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
}

.landing-logo img {
    height: 72px;
    width: auto;
    max-width: min(260px, 75vw);
    display: block;
}

.landing-header-spacer { display: none; }

@media (min-width: 992px) {
    .landing-header-inner { gap: 24px; }
    .landing-logo img { height: 80px; max-width: 300px; }
    .landing-menu-inline { display: flex; }
    .landing-menu-btn { display: none; }
}

.landing-carousel {
    padding: 28px 0 32px;
}

.landing-carousel-frame {
    padding: 16px 16px 12px;
    border-radius: 28px;
    background: #f0f0f0;
}

.landing-carousel-title {
    font-family: var(--font-display);
    font-size: 1.55rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 12px;
    color: var(--color-ink);
}

.landing-carousel-main {
    padding-bottom: 24px;
    position: relative;
    height: clamp(240px, 52vw, 500px);
}

.landing-carousel .swiper {
    position: relative;
    overflow: hidden;
}

.landing-carousel .swiper-wrapper {
    display: flex;
    width: 100%;
    height: 100%;
}

.landing-carousel .swiper-slide {
    flex: 0 0 100%;
    height: 100%;
}

.landing-carousel-main .swiper-slide {
    display: flex;
    justify-content: center;
}

.landing-carousel-item {
    width: min(920px, 100%);
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 22px;
    padding: 18px;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.12);
    position: relative;
    overflow: hidden;
    display: grid;
    place-items: center;
    height: 100%;
    aspect-ratio: 16 / 9;
}

.landing-carousel-item::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(130deg, rgba(255, 255, 255, 0.15), transparent 45%);
    pointer-events: none;
}

.landing-carousel-item img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 16px;
    display: block;
    background: #fff;
    transition: transform 0.6s ease;
}

.landing-carousel-thumbs {
    margin-top: 12px;
    padding: 8px 10px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: var(--shadow-card);
}

.landing-carousel-thumb {
    width: 100%;
    height: 64px;
    object-fit: contain;
    border-radius: 10px;
    border: 1px solid transparent;
    opacity: 0.5;
    transition: opacity 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    display: block;
    cursor: pointer;
    background: #fff;
}

.landing-carousel-thumbs .swiper-slide-thumb-active .landing-carousel-thumb {
    opacity: 1;
    border-color: var(--color-ink);
    box-shadow: 0 10px 18px rgba(0, 0, 0, 0.14);
    transform: translateY(-2px);
}

.landing-carousel-nav {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    pointer-events: none;
}

.landing-carousel-btn {
    pointer-events: auto;
    width: 46px;
    height: 46px;
    border-radius: 999px;
    border: 1px solid rgba(0, 0, 0, 0.12);
    background: rgba(255, 255, 255, 0.95);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.16);
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    cursor: pointer;
}

.landing-carousel-btn svg {
    width: 20px;
    height: 20px;
    stroke: var(--color-ink);
    stroke-width: 2.5;
    fill: none;
}

.landing-carousel-btn:hover {
    transform: translateY(-1px) scale(1.02);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.18);
}

.landing-carousel-btn.swiper-button-disabled {
    opacity: 0.4;
    cursor: default;
    box-shadow: none;
}

.landing-carousel.is-single .landing-carousel-nav,
.landing-carousel.is-single .landing-carousel-pagination { display: none; }

@media (min-width: 1024px) { .landing-carousel-thumb { height: 72px; } }

.landing-carousel .swiper-pagination-bullet {
    width: 18px;
    height: 4px;
    border-radius: 999px;
    background: #000;
    opacity: 0.25;
    margin: 0 4px !important;
}

.landing-carousel .swiper-pagination-bullet-active { opacity: 1; }

.landing-order {
    padding: 30px 0 18px;
}

.landing-order .order-card {
    max-width: 560px;
    margin: 0 auto;
}

.order-card {
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 18px 44px rgba(17, 24, 39, 0.1);
    padding: 22px;
    border: 1px solid rgba(17, 24, 39, 0.14);
    overflow: hidden;
}

.order-card-head {
    display: grid;
    gap: 6px;
    text-align: center;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(17, 24, 39, 0.08);
}

.order-form-title {
    font-family: var(--font-display);
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.25;
    margin: 0;
    color: var(--color-ink);
}

.order-card .order-tag,
.order-card .product-title,
.order-card .rating-row,
.order-card .hero-price,
.order-card .order-now-text { display: none; }

.order-color-select {
    margin: 0 auto 22px;
    max-width: 640px;
    background: linear-gradient(180deg, rgba(193, 18, 31, 0.16) 0%, #ffffff 58%);
    border: 1px solid rgba(193, 18, 31, 0.22);
    border-radius: 20px;
    padding: 22px 20px 18px;
    box-shadow: 0 18px 34px rgba(0, 0, 0, 0.1);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.order-color-title {
    font-size: 1.55rem;
    font-weight: 800;
    margin: 10px 0 6px;
    color: var(--color-ink);
    display: block;
}

.order-color-title strong { color: var(--color-accent); }

.order-color-note {
    margin: 0 0 12px;
    color: var(--color-muted);
    font-size: 1.05rem;
}

.order-color-error {
    margin: 10px auto 14px;
    color: #c1121f;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(193, 18, 31, 0.12);
    border: 1px solid rgba(193, 18, 31, 0.25);
    padding: 8px 14px;
    border-radius: 999px;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    flex-direction: column;
}

.landing-page .form-group {
    margin-bottom: 14px;
}

.landing-page .form-field {
    width: 100%;
    max-width: 100%;
    flex: 0 0 100%;
    padding: 0;
}

.form-group label {
    display: block;
    font-weight: 800;
    margin-bottom: 7px;
    color: var(--color-ink);
    font-size: 1.05rem;
    line-height: 1.45;
}

.form-control {
    border: 1.5px solid rgba(17, 24, 39, 0.18);
    border-radius: 12px;
    padding: 0 16px;
    font-size: 1rem;
    line-height: 1.4;
    height: 52px;
    min-height: 52px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    background: #ffffff;
    text-align: right;
    direction: rtl;
    width: 100%;
    font-family: inherit;
    outline: none;
}

.form-control:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 4px rgba(193, 18, 31, 0.1);
}

select.form-control { text-align: right; }

.phone-wrapper {
    display: flex;
    border: 2px solid var(--color-ink);
    border-radius: 12px;
    overflow: hidden;
    direction: rtl;
    height: 64px;
}

.phone-wrapper .country-code {
    background: #f3f4f6;
    padding: 10px 15px;
    border-left: 2px solid var(--color-ink);
    display: flex;
    align-items: center;
    font-weight: 700;
    color: var(--color-ink);
    direction: ltr;
}

.phone-wrapper .country-code img { width: 20px; margin-right: 8px; }

.phone-wrapper input {
    border: none;
    border-radius: 0;
    width: 100%;
    padding: 10px 15px;
    direction: ltr;
    text-align: left;
    font-size: 1.35rem;
    font-family: inherit;
    outline: none;
    background: transparent;
}

.qty-control {
    display: flex;
    align-items: center;
    gap: 8px;
    direction: rtl;
}

.qty-control .form-control {
    flex: 1;
    text-align: center;
    padding: 0 12px;
    font-size: 1rem;
}

.qty-btn {
    width: 44px;
    height: 44px;
    border: 1.5px solid rgba(17, 24, 39, 0.18);
    border-radius: 12px;
    background: #ffffff;
    color: var(--color-ink);
    font-size: 24px;
    font-weight: 700;
    display: grid;
    place-items: center;
    transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
    cursor: pointer;
}

.qty-btn:hover {
    background: var(--color-accent);
    color: #ffffff;
    transform: translateY(-1px);
}

.size-selector, .color-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

.size-option, .color-option {
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 8px 14px;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.2s ease;
    font-weight: 600;
    font-size: 1.05rem;
}

.size-option.selected, .color-option.selected {
    border-color: var(--color-accent);
    background: var(--color-accent-soft);
    color: var(--color-accent);
}

.delivery-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.delivery-btn {
    border: 1.5px solid rgba(17, 24, 39, 0.14);
    border-radius: 12px;
    background: #ffffff;
    color: var(--color-ink);
    padding: 11px 12px;
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    cursor: pointer;
    min-width: 0;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.delivery-btn:hover { border-color: rgba(193, 18, 31, 0.55); }

.delivery-btn.selected {
    background: var(--color-accent-soft);
    border-color: var(--color-accent);
    color: var(--color-ink);
    box-shadow: 0 0 0 3px rgba(193, 18, 31, 0.08);
}

.delivery-price-tag {
    background: rgba(0, 0, 0, 0.06);
    color: var(--color-ink);
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 800;
    white-space: nowrap;
}

.delivery-btn.selected .delivery-price-tag {
    background: var(--color-accent);
    color: #ffffff;
}

.delivery-options-note {
    grid-column: 1 / -1;
}

.delivery-info {
    position: relative;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid rgba(17, 24, 39, 0.12);
    padding: 12px 14px;
    margin-top: 10px;
}

.delivery-info.is-hidden { display: none; }

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    font-weight: 700;
    color: var(--color-ink);
}

.summary-row.total {
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid rgba(0, 0, 0, 0.15);
    font-size: 1.2rem;
}

.summary-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 1rem;
}

.summary-value {
    font-weight: 700;
    color: var(--color-ink);
    font-size: 1rem;
}

.summary-value.total-value {
    color: var(--color-success);
    font-size: 1.35rem;
}

.btn-buy-now {
    position: relative;
    overflow: hidden;
    background: var(--color-success);
    border: 1px solid var(--color-success);
    color: #ffffff;
    font-weight: 700;
    padding: 14px 18px;
    border-radius: 12px;
    letter-spacing: 0.2px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    width: 100%;
    font-size: 1rem;
    font-family: inherit;
    cursor: pointer;
}

.btn-buy-now:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 0 rgba(0, 0, 0, 0.2);
    filter: brightness(0.98);
}

.btn-buy-now:active { transform: translateY(0); }

.order-cta {
    display: flex;
    justify-content: center;
    margin-top: 12px;
}

.order-cta .btn-buy-now { width: 100%; }

.form-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    border-top: 1px solid rgba(17, 24, 39, 0.08);
    border-bottom: 1px solid rgba(17, 24, 39, 0.08);
    padding: 10px 0;
    margin: 8px 0 14px;
    text-align: center;
    font-size: 0.78rem;
}

.form-features .feature-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-weight: 700;
    color: var(--color-ink);
    min-width: 0;
    line-height: 1.45;
}

.privacy-note {
    font-size: 0.82rem;
    color: var(--color-muted);
    margin-top: 10px;
    text-align: center;
}

.trust-strip { padding: 8px 0 4px; }

.trust-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    max-width: 760px;
    justify-content: center;
}

.trust-chip {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
    width: 100%;
    min-height: 64px;
    flex: 1 1 calc(50% - 10px);
    max-width: 360px;
}

.section { padding: 50px 0; }
.section.steps, .section.details, .section.faq { padding: 12px 0; }

@media (max-width: 1024px) {
    .landing-page .trust-chip { flex: 1 1 calc(50% - 10px); width: auto !important; }
}

@media (max-width: 640px) { .landing-page .trust-grid { gap: 8px; } }

.section-title {
    font-family: var(--font-display);
    font-size: 2rem;
    margin-bottom: 12px;
    text-align: center;
}

.section-subtitle {
    color: var(--color-muted);
    margin-bottom: 24px;
    line-height: 1.8;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 18px;
    max-width: 760px;
    margin: 0 auto;
}

.step-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 18px;
    border: 1px solid var(--color-border);
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.05);
}

.step-index {
    font-weight: 700;
    color: var(--color-accent-dark);
    margin-bottom: 8px;
    font-size: 1.2rem;
}

.step-card h3 { margin: 0 0 8px; font-size: 1.1rem; }
.step-card p { margin: 0; color: var(--color-muted); line-height: 1.7; }

.landing-photos { padding: 8px 0 24px; }
.landing-photos .container { max-width: 760px; }

.landing-photos-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.landing-photo {
    background: transparent;
    border: 0;
    padding: 0;
    box-shadow: none;
    width: 100%;
    max-width: 560px;
    margin: 0 auto;
}

.landing-photo img {
    width: 100%;
    max-width: 560px;
    height: auto;
    border-radius: 18px;
    display: block;
    margin: 0 auto;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
}

.gallery-grid img {
    width: 100%;
    border-radius: 18px;
    box-shadow: var(--shadow-card);
    display: block;
}

.details-card {
    background: linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);
    border-radius: 20px;
    padding: 22px 24px;
    border: 2px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 16px 28px rgba(0, 0, 0, 0.08);
    line-height: 1.9;
    color: var(--color-ink);
    position: relative;
    overflow: hidden;
}

.details-card::before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 6px;
    height: 100%;
    background: var(--color-accent);
    opacity: 0.6;
}

.details-text { font-size: 1.3rem; }

.faq-grid {
    display: grid;
    gap: 12px;
}

.faq-grid details {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: 14px 16px;
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.05);
}

.faq-grid summary { font-weight: 600; cursor: pointer; }
.faq-grid p { margin: 10px 0 0; color: var(--color-muted); line-height: 1.7; }

.final-cta { padding: 50px 0 70px; }

.final-cta-inner {
    background: var(--color-accent-soft);
    border-radius: 24px;
    padding: 30px;
    text-align: center;
    box-shadow: var(--shadow-soft);
    border: 1px solid rgba(193, 18, 31, 0.2);
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
    background: var(--color-accent);
    color: #ffffff;
    border: none;
    padding: 12px 26px;
    border-radius: 999px;
    font-weight: 700;
    box-shadow: 0 12px 28px rgba(193, 18, 31, 0.35);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-family: inherit;
}

.sticky-cta button:hover { background: var(--color-accent-dark); }

.reveal {
    opacity: 0;
    transform: translateY(16px);
    animation: fadeUp 0.8s ease forwards;
    animation-delay: var(--delay, 0s);
}

@keyframes fadeUp {
    to { opacity: 1; transform: translateY(0); }
}

.order-card.has-offers .quantity-group { display: none; }

.order-offers {
    margin: 16px 0 10px;
    display: grid;
    gap: 12px;
}

.order-offers-top {
    margin: 10px auto 22px;
    max-width: 560px;
    width: 100%;
    direction: rtl;
}

.offer-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.7rem;
    text-align: center;
    margin-bottom: 6px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255, 212, 59, 0.34);
    color: #3a2f00;
}

.offer-grid {
    display: grid;
    gap: 12px;
    width: 100%;
}

.offer-card {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 10px;
    display: grid;
    grid-template-columns: minmax(92px, 118px) minmax(0, 1fr) 74px;
    gap: 12px;
    align-items: center;
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    direction: rtl;
    width: 100%;
    overflow: hidden;
    max-width: 760px;
    margin: 0 auto;
}

.offer-card:hover { border-color: rgba(0, 0, 0, 0.2); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08); }

.offer-card.selected {
    border-color: var(--color-success);
    background: rgba(46, 204, 113, 0.08);
    box-shadow: 0 12px 26px rgba(46, 204, 113, 0.22);
}

.offer-price-stack {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
    direction: ltr;
    min-width: 0;
}

.offer-old {
    font-size: 1rem;
    color: var(--color-muted);
    text-decoration: line-through;
    white-space: nowrap;
}

.offer-new {
    color: var(--color-ink);
    font-size: 1.35rem;
    font-weight: 800;
    white-space: nowrap;
}

.offer-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-end;
    text-align: right;
    direction: rtl;
    min-width: 0;
}

.offer-special-label,
.offer-special-desc,
.offer-qty-price {
    max-width: 100%;
    overflow-wrap: anywhere;
}

.offer-qty-price { font-weight: 800; font-size: 1.1rem; color: var(--color-ink); direction: ltr; }

.offer-badge {
    background: var(--color-success);
    color: #ffffff;
    padding: 5px 9px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    width: fit-content;
    max-width: 100%;
    white-space: nowrap;
}

.offer-thumb {
    width: 74px;
    aspect-ratio: 1 / 1;
    border-radius: 10px;
    overflow: hidden;
    background: #f7f7f7;
}

.offer-thumb img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

@media (max-width: 768px) {
    .landing-page .trust-grid { display: flex !important; flex-wrap: wrap; }
    .landing-page .trust-chip { flex: 1 1 calc(50% - 10px); width: auto !important; }
    .landing-page .container { width: min(100% - 20px, 960px); }
    .announcement-item { font-size: 1rem; padding: 0 26px; }
    .landing-header { padding: 14px 0 20px; margin-bottom: 0; }
    .landing-carousel { padding: 20px 0 28px; }
    .landing-carousel-main { padding-bottom: 18px; height: clamp(220px, 70vw, 360px); }
    .landing-carousel-frame { padding: 12px 12px 8px; border-radius: 22px; }
    .landing-carousel-title { font-size: 1.35rem; padding: 8px 12px; margin-bottom: 10px; }
    .landing-carousel-item { padding: 12px; border-radius: 18px; }
    .landing-carousel-thumb { height: 52px; }
    .order-offers-top { max-width: 100%; }
    .offer-card {
        grid-template-columns: 82px minmax(0, 1fr) 64px;
        gap: 8px;
        padding: 8px;
    }
    .offer-thumb { width: 64px; }
    .offer-new { font-size: 1.1rem; }
    .offer-old { font-size: 0.88rem; }
    .offer-qty-price { font-size: 0.95rem; }
    .offer-badge { font-size: 0.78rem; padding: 4px 7px; }
    .landing-carousel-thumbs { margin-top: 8px; padding: 6px; border-radius: 14px; }
    .landing-carousel-nav { padding: 0 6px; }
    .landing-carousel-btn { width: 36px; height: 36px; }
    .landing-carousel-btn svg { width: 16px; height: 16px; }
    .landing-order { padding: 24px 0 18px; }
    .landing-header-inner { gap: 8px; }
    .landing-menu-btn { width: 38px; height: 38px; }
    .landing-logo img { height: 60px; max-width: min(220px, 75vw); }
    .order-card { padding: 18px 14px; border-radius: 16px; }
    .order-form-title { font-size: 1.65rem; }
    .form-group label { font-size: 0.98rem; font-weight: 800; }
    .form-control, .qty-control .form-control { font-size: 0.95rem; height: 50px; min-height: 50px; }
    .delivery-options { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .delivery-btn { padding: 10px 8px; font-size: 0.82rem; flex-wrap: wrap; }
    .delivery-price-tag { font-size: 0.8rem; }
    .size-option, .color-option, .summary-label, .summary-value { font-size: 0.95rem; }
    .delivery-price { font-size: 1rem; }
    .summary-value.total-value { font-size: 1.2rem; }
    .form-features { font-size: 0.72rem; gap: 4px; }
    .btn-buy-now { font-size: 0.95rem; }
}
</style>
<div class="page landing-page" id="top">
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
                    
                    
    <div class="landing-header">
        <div class="container landing-header-inner">
            <a class="landing-logo" href="index.php">
                <?php $landing_logo_url = trim((string)get_front_optimized_image_url($logo, 260, 60)); ?>
                <?php if ($landing_logo_url !== ''): ?>
                    <?php $logo_dims = get_image_dimensions($logo, 180, 60); ?>
                    <img src="<?php echo htmlspecialchars($landing_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" width="<?php echo (int)$logo_dims['width']; ?>" height="<?php echo (int)$logo_dims['height']; ?>" loading="lazy" decoding="async" fetchpriority="low">
                <?php else: ?>
                    <span class="logo-text-fallback"><?php echo htmlspecialchars(trim((string)($meta_title_home ?? 'Store')), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </a>
            <nav class="landing-menu-inline" aria-label="القائمة الرئيسية">
                <a href="#top">الرئيسية</a>
                <a href="#orderFormSection">اطلب الآن</a>
                <a href="#landingPhotos">صور هذا المنتج</a>
                <a href="#stepsSection">خطوات الطلب</a>
                <a href="#faqSection">الأسئلة الشائعة</a>
            </nav>
            <div class="landing-header-spacer" aria-hidden="true"></div>
            <button type="button" class="landing-menu-btn" aria-label="فتح القائمة" aria-controls="landingMenu" aria-expanded="false">
                <span class="landing-menu-icon"></span>
            </button>
        </div>
    </div>
    <div class="landing-menu-overlay" aria-hidden="true"></div>
    <nav class="landing-menu-panel" id="landingMenu" aria-hidden="true" inert>
        <div class="landing-menu-head">
            <span class="landing-menu-title">القائمة</span>
            <button type="button" class="landing-menu-close" data-menu-close aria-label="إغلاق القائمة">×</button>
        </div>
        <ul class="landing-menu-list">
            <li><a href="#top" data-menu-close>الرئيسية</a></li>
            <li><a href="#orderFormSection" data-menu-close>اطلب الآن</a></li>
            <li><a href="#landingPhotos" data-menu-close>صور هذا المنتج</a></li>
            <li><a href="#stepsSection" data-menu-close>خطوات الطلب</a></li>
            <li><a href="#faqSection" data-menu-close>الأسئلة الشائعة</a></li>
        </ul>
    </nav>
    <?php if (!empty($display_carousel_photos)): ?>
        <?php $landing_carousel_count = count($display_carousel_photos); ?>
        <section class="landing-carousel<?= $landing_carousel_count <= 1 ? ' is-single' : '' ?>" style="--landing-carousel-ratio: <?= htmlspecialchars($landing_carousel_ratio, ENT_QUOTES, 'UTF-8') ?>;">
            <div class="container">
                <div class="landing-carousel-frame">
                    <?php if (!empty($product_name)): ?>
                        <div class="landing-carousel-title"><?= htmlspecialchars($product_name) ?></div>
                    <?php endif; ?>
                    <div class="swiper landing-carousel-main">
                        <div class="swiper-wrapper">
                            <?php $carousel_sizes = '(max-width: 768px) 92vw, 920px'; ?>
                            <?php foreach ($display_carousel_photos as $index => $photo): ?>
                                <div class="swiper-slide">
                                    <div class="landing-carousel-item">
                                        <?php
                                            $photo_url = $carousel_photo_optimized_urls[$photo] ?? get_front_optimized_image_url($photo, 980, 58);
                                            $photo_dims = $carousel_photo_sizes[$photo] ?? get_image_dimensions($photo, 1200, 1200);
                                            $photo_webp = resolve_webp_src($photo);
                                            $photo_webp_srcset = $carousel_photo_srcsets[$photo] ?? build_webp_srcset($photo);
                                            $photo_fallback_srcset = $carousel_photo_img_srcsets[$photo] ?? '';
                                            $is_first_slide = ($index === 0);
                                            $loading_attr = $is_first_slide ? 'eager' : 'lazy';
                                            $decoding_attr = $is_first_slide ? 'sync' : 'async';
                                        ?>
                                        <?php if ($photo_webp !== ''): ?>
                                            <picture>
                                                <source type="image/webp"
                                                        srcset="<?= htmlspecialchars($photo_webp_srcset !== '' ? $photo_webp_srcset : $photo_webp) ?>"
                                                        sizes="<?= $carousel_sizes ?>">
                                                <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                                                     alt="<?= htmlspecialchars($product_name) ?>"
                                                     width="<?= (int)($photo_dims['width'] ?? 1200) ?>"
                                                     height="<?= (int)($photo_dims['height'] ?? 1200) ?>"
                                                     loading="<?= $loading_attr ?>"
                                                     decoding="<?= $decoding_attr ?>"
                                                     <?= $photo_fallback_srcset !== '' ? 'srcset="' . htmlspecialchars($photo_fallback_srcset, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                     sizes="<?= $carousel_sizes ?>"
                                                     <?= $is_first_slide ? 'fetchpriority="high"' : '' ?>>
                                            </picture>
                                        <?php else: ?>
                                            <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                                                 alt="<?= htmlspecialchars($product_name) ?>"
                                                 width="<?= (int)($photo_dims['width'] ?? 1200) ?>"
                                                 height="<?= (int)($photo_dims['height'] ?? 1200) ?>"
                                                 loading="<?= $loading_attr ?>"
                                                 decoding="<?= $decoding_attr ?>"
                                                 <?= $photo_fallback_srcset !== '' ? 'srcset="' . htmlspecialchars($photo_fallback_srcset, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                 sizes="<?= $carousel_sizes ?>"
                                                 <?= $is_first_slide ? 'fetchpriority="high"' : '' ?>>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="landing-carousel-nav">
                            <button type="button" class="landing-carousel-btn landing-carousel-prev" aria-label="Previous slide">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                            <button type="button" class="landing-carousel-btn landing-carousel-next" aria-label="Next slide">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="swiper-pagination landing-carousel-pagination"></div>
                    </div>
                </div>
                <?php if ($landing_carousel_count > 1): ?>
                    <div class="swiper landing-carousel-thumbs">
                        <div class="swiper-wrapper">
                            <?php foreach ($display_carousel_photos as $photo): ?>
                                <div class="swiper-slide">
                                    <?php
                                    $thumb_url = $carousel_thumb_optimized_urls[$photo] ?? get_front_optimized_image_url($photo, 140, 52);
                                    $thumb_dims = $carousel_photo_sizes[$photo] ?? get_image_dimensions($photo, 160, 160);
                                    ?>
                                    <img class="landing-carousel-thumb"
                                         src="<?= htmlspecialchars($thumb_url, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($product_name) ?>"
                                         width="<?= (int)($thumb_dims['width'] ?? 160) ?>"
                                         height="<?= (int)($thumb_dims['height'] ?? 160) ?>"
                                         loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
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
                    <div class="offer-title">عرض خاص لفترة محدودة</div>
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
                            <div class="offer-card<?= $index === 0 ? ' selected' : '' ?><?= $offer['type'] === 'special' ? ' offer-card--special' : '' ?>" data-offer-id="<?= $offer['id'] ?>" data-offer-qty="<?= $offer['qty'] ?>" data-offer-unit="<?= $offer['unit'] ?>" data-offer-type="<?= htmlspecialchars($offer['type'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="offer-price-stack">
                                    <?php if (!empty($offer['base_total']) && $offer['base_total'] > $offer['offer_total']): ?>
                                        <span class="offer-old"><?= number_format($offer['base_total'], 0, '.', ',') ?> &#1583;&#1580;</span>
                                    <?php endif; ?>
                                    <span class="offer-new"><?= number_format($offer['offer_total'], 0, '.', ',') ?> &#1583;&#1580;</span>
                                </div>
                                <div class="offer-details">
                                    <?php if ($offer['type'] === 'special'): ?>
                                        <div class="offer-special-label"><?= htmlspecialchars($offer['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($offer['description'])): ?>
                                            <div class="offer-special-desc"><?= htmlspecialchars($offer['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <span class="offer-badge"><?= $offer['discount'] > 0 ? ('تخفيض ' . $offer['discount'] . '%') : 'سعر خاص' ?></span>
                                    <?php else: ?>
                                        <div class="offer-qty-price"><?= $offer['qty'] ?>X <?= number_format($offer['unit'], 0, '.', ',') ?> &#1583;&#1580;</div>
                                        <span class="offer-badge">&#1578;&#1582;&#1601;&#1610;&#1590; <?= $offer['discount'] ?>%</span>
                                    <?php endif; ?>
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
<div id="orderFormSection" class="order-card reveal<?= !empty($offers) ? " has-offers" : "" ?>" style="--delay: 0.1s;">
                <div class="order-card-head">
                    <h2 class="order-form-title">&#1573;&#1578;&#1605;&#1575;&#1605;&#32;&#1593;&#1605;&#1604;&#1610;&#1577;&#32;&#1575;&#1604;&#1591;&#1604;&#1576;</h2>
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

                

                <form action="" method="post" id="orderForm">
                    <input type="hidden" name="form1" value="1">
                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token']) ?>">
                    <?php if (!empty($offers)): ?>
                        <input type="hidden" name="selected_offer_id" id="selected_offer_id" value="<?= $offers[0]['id'] ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group form-field col-md-6 position-relative">
                            <label for="customer_name">الاسم الكامل *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required placeholder="&#1575;&#1603;&#1578;&#1576;&#32;&#1575;&#1587;&#1605;&#1603;&#32;&#1607;&#1606;&#1575;&#46;&#46;&#46;" value="<?= isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : '' ?>">
                        </div>
                        <div class="form-group form-field col-md-6 position-relative">
                            <label for="customer_phone">رقم الهاتف *</label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required 
                                   placeholder="06XXXXXXXX"  
                                   pattern="[0-9]{9,10}" 
                                   title="يرجى إدخال رقم هاتف جزائري صحيح (9-10 أرقام)"
                                   value="<?= isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : '' ?>">
                            <div id="phone-error" class="text-danger mt-1" style="display: none; font-size: 0.9rem;"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group form-field col-md-6">
                            <label for="wilaya">الولاية *</label>
                            <select class="form-control" id="wilaya" name="wilaya" required>
                                <option value="">اختر الولاية</option>
                                <?php foreach (array_keys($shipping_data) as $wilaya): ?>
                                    <option value="<?= htmlspecialchars($wilaya) ?>"><?= htmlspecialchars($wilaya) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-field col-md-6">
                            <label for="commune">البلدية *</label>
                            <select class="form-control" id="commune" name="commune" required>
                                <option value="">اختر البلدية</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" id="desk_container" style="display: none;">
                        <div class="form-group form-field">
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
                            <button type="button" id="decreaseQuantity" class="qty-btn" aria-label="&#1606;&#1602;&#1589;&#32;&#1575;&#1604;&#1603;&#1605;&#1610;&#1577;">-</button>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="<?= $p_qty ?>" required>
                            <button type="button" id="increaseQuantity" class="qty-btn" aria-label="&#1586;&#1610;&#1575;&#1583;&#1577;&#32;&#1575;&#1604;&#1603;&#1605;&#1610;&#1577;">+</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>&#1606;&#1608;&#1593;&#32;&#1575;&#1604;&#1578;&#1608;&#1589;&#1610;&#1604; *</label>
                        <div class="delivery-options">
                            <?php if ($product_delivery_mode === 'free'): ?>
                                <button type="button" class="delivery-btn selected" data-kind="free" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('free', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل مجاني</span>
                                    <span class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php elseif ($product_delivery_mode === 'home_only'): ?>
                                <button type="button" class="delivery-btn selected" data-kind="home" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل للمنزل</span>
                                    <span id="homePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="delivery-btn selected" data-kind="home" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل للمنزل</span>
                                    <span id="homePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                                <button type="button" class="delivery-btn" data-kind="office" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('office', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل للمكتب</span>
                                    <span id="officePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php endif; ?>
                        <div class="delivery-options-note" id="deliveryOptionsNote" style="display: none;"></div>
                    </div>
                    </div>


                    <div class="form-group">
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
                            <i class="fa fa-shopping-cart me-2"></i> &#1578;&#1571;&#1603;&#1610;&#1583;&#32;&#1575;&#1604;&#1591;&#1604;&#1576;&#32;&#45;&#32;&#1571;&#1591;&#1604;&#1576;&#32;&#1575;&#1604;&#1570;&#1606;
                        </button>
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
</body>
</html>


