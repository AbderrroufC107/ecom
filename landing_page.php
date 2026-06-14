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
    require_once('inc/encryption.php');
    require_once('inc/site-security.php');

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
<?php
// Include encryption helpers
require_once('inc/encryption.php');
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
$shipping_data = [];
foreach ($resolved_company_ids as $candidate_company_id) {
    $candidate_shipping_data = [];
    $statement = $pdo->prepare("SELECT wilaya, price, delivery_type FROM tbl_delivery_price WHERE company_id = ?");
    $statement->execute([$candidate_company_id]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $shipping_row) {
        $type = resolve_delivery_type_by_mode($shipping_row['delivery_type'] ?? '', $product_delivery_mode);
        $candidate_shipping_data[$shipping_row['wilaya']][$type] = floatval($shipping_row['price']);
    }
    if (!empty($candidate_shipping_data)) {
        $shipping_data = $candidate_shipping_data;
        $company_id = $candidate_company_id;
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
function debug_post_data() {
    error_log('=== بيانات POST المستلمة ===');
    error_log('$_POST: ' . print_r($_POST, true));
    error_log('$_REQUEST: ' . print_r($_REQUEST, true));
    error_log('=== نهاية بيانات POST ===');
}

// دالة للتحقق من فورمات رقم الهاتف الجزائري
function validateAlgerianPhoneNumber($phone) {
    // إزالة المسافات والرموز الخاصة
    $phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
    
    // التحقق من أن الرقم يحتوي على أرقام فقط
    if (!preg_match('/^[0-9]+$/', $phone)) {
        return false;
    }
    
    // التحقق من الطول (يجب أن يكون بين 9 و 10 أرقام)
    if (strlen($phone) < 9 || strlen($phone) > 10) {
        return false;
    }
    
    // التحقق من أن يبدأ بـ 0 أو 213
    if (strlen($phone) == 10) {
        // رقم جزائري محلي يبدأ بـ 0
        if (!preg_match('/^0[5-7][0-9]{8}$/', $phone)) {
            return false;
        }
    } elseif (strlen($phone) == 9) {
        // رقم جزائري بدون الصفر الأول
        if (!preg_match('/^[5-7][0-9]{8}$/', $phone)) {
            return false;
        }
    } else {
        return false;
    }
    
    return true;
}

// دالة لتنسيق رقم الهاتف
function formatPhoneNumber($phone) {
    // إزالة المسافات والرموز الخاصة
    $phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
    
    // إذا كان الرقم 9 أرقام، أضف 0 في البداية
    if (strlen($phone) == 9) {
        $phone = '0' . $phone;
    }
    
    return $phone;
}

// دالة للتحقق من وجود طلبات سابقة برقم الهاتف
function checkExistingOrder($pdo, $customer_phone) {
    try {
        // التحقق من الطلبات المعلقة (pending)
        $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE customer_phone = ? AND LOWER(order_status) = 'pending' ORDER BY order_date DESC LIMIT 1");
        $stmt->execute([$customer_phone]);
        $pending_order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pending_order) {
            $order_id = $pending_order['order_id'] ?? ($pending_order['id'] ?? null);
            return [
                'exists' => true,
                'order_id' => $order_id,
                'order_date' => $pending_order['order_date'],
                'product_name' => $pending_order['product_name'],
                'status' => 'pending',
                'message' => 'لقد قمت بالطلب مسبقاً! انتظر للاتصال بك أو تأكيد الطلب.'
            ];
        }
        
        // ملاحظة: لا نمنع الطلبات غير المكتملة، يمكن للعميل إرسال طلب جديد
        
        return ['exists' => false, 'message' => 'لا توجد طلبات سابقة'];
        
    } catch (PDOException $e) {
        error_log('خطأ في التحقق من الطلبات السابقة: ' . $e->getMessage());
        return ['exists' => false, 'message' => 'خطأ في التحقق من الطلبات السابقة'];
    }
}

// دالة للتحقق من صحة اسم العميل
function validateCustomerName($name) {
    // إزالة المسافات الزائدة
    $name = trim($name);
    
    // التحقق من أن الاسم غير فارغ
    if (empty($name)) {
        return ['valid' => false, 'message' => 'الاسم مطلوب'];
    }
    
    // التحقق من الطول الأدنى (3 أحرف على الأقل)
    if (mb_strlen($name) < 3) {
        return ['valid' => false, 'message' => 'يجب أن يحتوي الاسم على 3 أحرف على الأقل'];
    }
    
    // التحقق من الطول الأقصى (50 حرف)
    if (mb_strlen($name) > 50) {
        return ['valid' => false, 'message' => 'يجب أن يكون الاسم أقل من 50 حرف'];
    }
    
    // التحقق من الحروف المسموحة (عربية، لاتينية، مسافات)
    if (!preg_match('/^[\p{Arabic}\p{Latin}\s]+$/u', $name)) {
        return ['valid' => false, 'message' => 'يجب أن يحتوي الاسم على حروف عربية أو لاتينية فقط'];
    }
    
    // التحقق من عدم وجود أرقام أو رموز خاصة
    if (preg_match('/[0-9@#\$%\^&\*\(\)\+=\[\]\{\}\|\\:";\'<>,\?\/~`!]/', $name)) {
        return ['valid' => false, 'message' => 'لا يُسمح بالأرقام أو الرموز الخاصة في الاسم'];
    }
    
    // التحقق من تكرار الحروف (أكثر من حرفين متتالين)
    if (preg_match('/(.)\1{2,}/u', $name)) {
        return ['valid' => false, 'message' => 'لا يُسمح بتكرار نفس الحرف أكثر من مرتين متتاليتين'];
    }
    
    // قائمة الأسماء المزيفة المحظورة
    $blacklist = [
        'test', 'testing', 'tester', 'user', 'client', 'customer', 'name', 'unknown', 'anonymous',
        'aaa', 'bbb', 'ccc', 'ddd', 'eee', 'fff', 'ggg', 'hhh', 'iii', 'jjj', 'kkk', 'lll', 'mmm',
        'nnn', 'ooo', 'ppp', 'qqq', 'rrr', 'sss', 'ttt', 'uuu', 'vvv', 'www', 'xxx', 'yyy', 'zzz',
        'abc', 'xyz', 'qwe', 'asd', 'zxc', '123', '456', '789', '000', '111', '222', '333', '444',
        '555', '666', '777', '888', '999', 'admin', 'administrator', 'root', 'guest', 'visitor',
        'dummy', 'fake', 'spam', 'bot', 'robot', 'auto', 'automatic', 'system', 'server', 'api'
    ];
    
    // التحقق من وجود كلمات محظورة (غير حساسة لحالة الأحرف)
    $name_lower = mb_strtolower($name, 'UTF-8');
    foreach ($blacklist as $forbidden) {
        if (strpos($name_lower, $forbidden) !== false) {
            return ['valid' => false, 'message' => 'الاسم يحتوي على كلمات غير مسموحة'];
        }
    }
    
    // التحقق من أن الاسم لا يحتوي على مسافات متعددة
    if (preg_match('/\s{2,}/', $name)) {
        return ['valid' => false, 'message' => 'لا يُسمح بوجود مسافات متعددة في الاسم'];
    }
    
    // التحقق من أن الاسم لا يبدأ أو ينتهي بمسافة
    if ($name !== trim($name)) {
        return ['valid' => false, 'message' => 'لا يُسمح بوجود مسافات في بداية أو نهاية الاسم'];
    }
    
    return ['valid' => true, 'message' => 'الاسم صحيح'];
}

// إضافة دالة للتحقق من الاتصال بقاعدة البيانات
function checkDatabaseConnection($pdo) {
    try {
        $pdo->query('SELECT 1');
        error_log('الاتصال بقاعدة البيانات ناجح');
        return true;
    } catch (PDOException $e) {
        error_log('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
        return false;
    }
}

// إضافة دالة للتحقق من الطلبات غير المكتملة
function checkIncompleteOrders($pdo, $customer_phone) {
    try {
        if (!checkDatabaseConnection($pdo)) {
            return [];
        }

        if (!ensure_incomplete_orders_table($pdo)) {
            return [];
        }

        $stmt = $pdo->prepare("SELECT * FROM incomplete_orders WHERE customer_phone = ?");
        $stmt->execute([$customer_phone]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error checking incomplete orders: ' . $e->getMessage());
        return [];
    }
}

// Save incomplete order
function saveIncompleteOrder($pdo, $product_id, $product_name, $customer_name, $customer_phone, $extra = []) {
    if (!checkDatabaseConnection($pdo)) {
        return false;
    }

    $quantity = isset($extra['quantity']) && $extra['quantity'] !== '' ? (int)$extra['quantity'] : null;
    $unit_price = isset($extra['unit_price']) && $extra['unit_price'] !== '' ? (float)$extra['unit_price'] : null;
    $total_price = isset($extra['total_price']) && $extra['total_price'] !== '' ? (float)$extra['total_price'] : null;
    $selected_size = isset($extra['selected_size']) ? trim($extra['selected_size']) : '';
    $selected_color = isset($extra['selected_color']) ? trim($extra['selected_color']) : '';
    $wilaya = isset($extra['wilaya']) ? trim($extra['wilaya']) : '';
    $commune = isset($extra['commune']) ? trim($extra['commune']) : '';
    $delivery_type = isset($extra['delivery_type']) ? trim($extra['delivery_type']) : '';
    $address = isset($extra['address']) ? trim($extra['address']) : '';

    $security_check = site_security_evaluate_order($pdo, [
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'wilaya' => $wilaya,
        'commune' => $commune,
        'address' => $address,
        'device_id' => $_POST['device_id'] ?? null
    ]);
    if ($security_check['action'] !== 'allow') {
        site_security_record_rejected_attempt($pdo, $security_check);
        return false;
    }
    if (($security_check['context']['phone'] ?? '') !== '') {
        $customer_phone = $security_check['context']['phone'];
    }

    if (!ensure_incomplete_orders_table($pdo)) {
        return false;
    }
    $customer_ip = $security_check['context']['ip_address'] ?? site_security_client_ip();
    $device_id = $security_check['context']['device_id'] ?? site_security_device_id();
    $user_agent = $security_check['context']['user_agent'] ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $existing_id = null;
    if ($product_id !== null && $product_id !== '') {
        $stmt = $pdo->prepare("SELECT id FROM incomplete_orders WHERE customer_phone = ? AND product_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$customer_phone, $product_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM incomplete_orders WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$customer_phone]);
    }
    $existing_id = $stmt->fetchColumn();

    if ($existing_id) {
        $update_stmt = $pdo->prepare("UPDATE incomplete_orders SET customer_name=?, product_id=?, product_name=?, quantity=?, unit_price=?, total_price=?, selected_size=?, selected_color=?, wilaya=?, commune=?, address=?, delivery_type=?, customer_ip=?, device_id=?, user_agent=?, last_updated=NOW() WHERE id=?");
        return $update_stmt->execute([$customer_name, $product_id, $product_name, $quantity, $unit_price, $total_price, $selected_size, $selected_color, $wilaya, $commune, $address, $delivery_type, $customer_ip, $device_id, $user_agent, $existing_id]);
    }

    $insert_stmt = $pdo->prepare("INSERT INTO incomplete_orders (customer_name, customer_phone, product_id, product_name, quantity, unit_price, total_price, selected_size, selected_color, wilaya, commune, address, delivery_type, customer_ip, device_id, user_agent, created_at, last_updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())");
    return $insert_stmt->execute([$customer_name, $customer_phone, $product_id, $product_name, $quantity, $unit_price, $total_price, $selected_size, $selected_color, $wilaya, $commune, $address, $delivery_type, $customer_ip, $device_id, $user_agent]);
}
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

                    // Telegram notification for completed orders (when enabled)
                    if (!empty($telegram_bot_token) && !empty($telegram_chat_id) && !empty($telegram_orders_enabled)) {
                        $telegram = new TelegramNotification($telegram_bot_token, $telegram_chat_id);
                        $selected_color_id = $_POST['selected_color'] ?? '';
                        $selected_color_name = $selected_color_id;
                        if ($selected_color_id !== '' && isset($color_names[$selected_color_id])) {
                            $selected_color_name = $color_names[$selected_color_id];
                        } elseif ($selected_color_id !== '' && ctype_digit($selected_color_id)) {
                            $selected_color_name = '';
                        }
                        $orderData = [
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
                        $telegram->sendOrderNotification($orderData);
                    }


                    $pdo->commit();
                    $auto_sms_result = admin_send_order_sms_automation($pdo, 'order_created', $created_order_context);
                    if (empty($auto_sms_result['skipped']) && empty($auto_sms_result['success'])) {
                        error_log('Automatic SMS failed for order #' . $created_order_id . ': ' . trim((string) ($auto_sms_result['error'] ?? 'Gateway error')));
                    }
                    
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
<style>
:root {
    --font-display: "Changa", "Cairo", sans-serif;
    --font-banner: "Tajawal", "Changa", sans-serif;
    --font-body: "Cairo", sans-serif;
    --color-ink: #000000;
    --color-muted: rgba(0, 0, 0, 0.65);
    --color-accent: #c1121f;
    --color-accent-dark: #8f1318;
    --color-accent-soft: rgba(193, 18, 31, 0.12);
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

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0 !important;
    padding: 0 !important;
    scrollbar-gutter: stable both-edges;
}

body {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

.page.landing-page {
    direction: rtl;
    text-align: right;
    font-family: var(--font-body);
    color: var(--color-ink);
    background: #f3f4f6;
    background-image: radial-gradient(rgba(0, 0, 0, 0.04) 1px, transparent 1px);
    background-size: 28px 28px;
    margin: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 80px;
    overflow-anchor: none;
}

.landing-page .container {
    max-width: 480px;
    width: min(100%, 480px);
    padding-inline: clamp(10px, 3vw, 18px);
    box-sizing: border-box;
}

/* Force mobile layout even on desktop */
.landing-page #main-content {
    width: 100%;
    max-width: 480px;
    margin-inline: auto;
    background: #ffffff;
    min-height: 100vh;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.12);
}

.announcement-bar {
    background: #000000;
    color: #ffffff;
    padding: 16px 0;
    font-weight: 800;
    letter-spacing: 0.3px;
    overflow: hidden;
    margin-top: 0 !important;
    font-family: var(--font-banner);
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    top: 0;
    min-height: 56px;
    overflow-anchor: none;
    contain: layout paint;
}

.announcement-bar .container {
    max-width: 100%;
}

.announcement-track {
    display: inline-flex;
    align-items: center;
    gap: 70px;
    width: max-content;
    white-space: nowrap;
    animation: announcement-scroll 24s linear infinite;
    will-change: transform;
}

.announcement-item {
    font-size: 1.6rem;
    line-height: 1.4;
}

@keyframes announcement-scroll {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(100%);
    }
}

.landing-header {
    background: rgba(255, 224, 150, 0.35);
    padding: 16px 0 24px;
    margin-bottom: 0;
    min-height: 92px;
}

.landing-carousel {
    padding: 0;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.04) 0%, rgba(255, 255, 255, 0.98) 55%, rgba(255, 255, 255, 0) 100%);
    position: relative;
    z-index: 1;
    overflow: hidden;
}

.landing-carousel::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 18%, rgba(193, 18, 31, 0.12), transparent 45%),
        radial-gradient(circle at 82% 12%, rgba(0, 0, 0, 0.06), transparent 40%);
    opacity: 0.7;
    pointer-events: none;
}

.landing-carousel .container {
    position: relative;
    z-index: 2;
    padding-inline: 0 !important;
}

.landing-carousel-frame {
    position: relative;
    padding: 0;
    border-radius: 0;
    background: transparent;
    border: 0;
    box-shadow: none;
    min-height: 0;
    overflow-anchor: none;
}

.landing-carousel-title {
    display: none;
    margin: 4px auto 14px;
    max-width: min(680px, 94%);
    text-align: center;
    font-family: var(--font-display);
    font-size: clamp(1.4rem, 2.2vw, 2rem);
    font-weight: 800;
    color: var(--color-ink);
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 16px;
    padding: 10px 16px;
    box-shadow: var(--shadow-card);
}

.landing-order {
    padding: 36px 0 16px;
}

.landing-order .order-card {
    max-width: 640px;
    margin: 0 auto;
}

.landing-carousel-main {
    padding-bottom: 24px;
    position: relative;
    min-height: clamp(320px, 72vw, 780px);
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
    align-items: center;
}

.landing-carousel-item {
    width: 100%;
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0;
    box-shadow: none;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 0;
    aspect-ratio: var(--landing-carousel-ratio, 1 / 1);
    margin: 0;
}

.landing-carousel-item::after {
    content: none;
}

.landing-carousel-item picture {
    display: flex;
    width: 100%;
    height: 100%;
}

.landing-carousel-item img {
    width: 100%;
    height: 100%;
    max-height: none;
    object-fit: contain;
    border-radius: 0;
    border: 0;
    display: block;
    background: transparent;
    transition: transform 0.6s ease;
}

.landing-carousel-item:hover img {
    transform: none;
}

.landing-carousel-thumbs {
    display: none;
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

.landing-carousel-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.16), 0 12px 24px rgba(0, 0, 0, 0.2);
}

.landing-carousel-btn.swiper-button-disabled {
    opacity: 0.4;
    cursor: default;
    box-shadow: none;
}

.landing-carousel.is-single .landing-carousel-nav,
.landing-carousel.is-single .landing-carousel-pagination {
    display: none;
}

@media (min-width: 1024px) {
    .landing-carousel-thumb {
        height: 72px;
    }
}

.landing-carousel .swiper-pagination-bullet {
    width: 18px;
    height: 4px;
    border-radius: 999px;
    background: #000;
    opacity: 0.25;
    margin: 0 4px !important;
}

.landing-carousel .swiper-pagination-bullet-active {
    opacity: 1;
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

.landing-menu-btn:focus-visible {
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.15);
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

.landing-menu-icon::before {
    top: -6px;
}

.landing-menu-icon::after {
    top: 6px;
}

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

body.menu-open {
    overflow: hidden;
}

body.menu-open .landing-menu-overlay {
    opacity: 1;
    pointer-events: auto;
}

body.menu-open .landing-menu-panel {
    transform: translateX(0);
}

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

.landing-logo .logo-text-fallback {
    display: inline-flex;
    align-items: center;
    min-height: 72px;
    padding: 0 10px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #111;
}

.landing-header-spacer {
    display: none;
}

@media (min-width: 992px) {
    .landing-header-inner {
        gap: 24px;
    }

    .landing-logo img {
        height: 80px;
        max-width: 300px;
    }

    .landing-menu-inline {
        display: flex;
    }

    .landing-menu-btn {
        display: none;
    }

    .landing-header-spacer {
        display: none;
    }
}

.landing-hero {
    position: relative;
    padding: 60px 0 40px;
    overflow: hidden;
}

.landing-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: none;
    opacity: 0;
    pointer-events: none;
}

.hero-grid {
    position: relative;
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap: 40px;
    z-index: 1;
}

.hero-left {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.eyebrow {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.eyebrow-pill {
    background: #ffffff;
    border: 1px solid var(--color-border);
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-accent-dark);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.hero-title {
    font-family: var(--font-display);
    font-size: 2.6rem;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
}

.hero-subtitle {
    font-size: 1.05rem;
    color: var(--color-muted);
    margin: 0;
    line-height: 1.9;
}

.hero-media {
    display: grid;
    gap: 16px;
}

.media-card {
    background: var(--color-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 14px;
}

.media-card img {
    width: 100%;
    border-radius: calc(var(--radius-lg) - 8px);
    display: block;
}

.media-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 12px 14px;
    text-align: center;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

.stat-title {
    font-size: 0.85rem;
    color: var(--color-muted);
}

.stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--color-ink);
}

.stat-stars {
    color: var(--color-accent);
    letter-spacing: 2px;
    font-size: 0.9rem;
}

.hero-points {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 10px;
}

.hero-points li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 10px 14px;
    font-weight: 600;
    color: var(--color-ink);
}

.hero-points i {
    color: var(--color-accent);
    margin-top: 2px;
}

.hero-price {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.price-current {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-accent);
}

.price-old {
    font-size: 1.2rem;
    color: var(--color-muted);
    text-decoration: line-through;
}

.price-badge {
    background: var(--color-accent-soft);
    color: var(--color-accent-dark);
    padding: 6px 14px;
    border-radius: 999px;
    font-weight: 700;
}

.hero-cta {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-cta {
    background: var(--color-accent);
    color: #ffffff;
    border: none;
    padding: 14px 28px;
    font-size: 1.1rem;
    border-radius: 16px;
    font-weight: 700;
    box-shadow: 0 16px 30px rgba(193, 18, 31, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(193, 18, 31, 0.3);
}

.cta-note {
    font-size: 0.9rem;
    color: var(--color-muted);
}

.trust-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--color-ink);
}

.trust-item i {
    color: var(--color-accent);
}

.order-card {
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 18px 30px rgba(0, 0, 0, 0.08);
    padding: 28px;
    border: 2px solid var(--color-ink);
    overflow-anchor: none;
    contain: layout paint;
}

.order-card-head {
    display: grid;
    gap: 8px;
    text-align: center;
    margin-bottom: 12px;
}

.order-form-title {
    font-family: var(--font-display);
    font-size: 3rem;
    font-weight: 800;
    margin: 0 0 6px;
    color: var(--color-ink);
}

.order-card .order-tag,
.order-card .product-title,
.order-card .rating-row,
.order-card .hero-price,
.order-card .order-now-text {
    display: none;
}

.order-tag {
    background: var(--color-accent-soft);
    color: var(--color-accent);
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 1rem;
    width: fit-content;
}

.product-title {
    font-family: var(--font-display);
    font-size: 2rem;
    margin: 0;
}

.rating-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.rating-row .stars {
    color: var(--color-accent);
    letter-spacing: 2px;
}

.order-now-text {
    background: var(--color-accent-soft);
    border-radius: 16px;
    padding: 16px;
    border: 1px dashed rgba(193, 18, 31, 0.3);
}

.order-now-text h3 {
    margin: 0 0 6px;
    font-size: 1.4rem;
}

.order-now-text p {
    margin: 0;
    color: var(--color-muted);
    font-size: 1.2rem;
    line-height: 1.8;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    flex-direction: column;
}

.form-group.col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
    width: 100%;
}

.form-group label {
    font-weight: 800;
    margin-bottom: 6px;
    color: var(--color-ink);
    font-size: 1.85rem;
}

.form-field {
    width: 100%;
    max-width: 100%;
    margin: 0;
}

.form-control {
    border: 2px solid var(--color-ink);
    border-radius: 12px;
    padding: 20px 46px 20px 16px;
    font-size: 1.35rem;
    line-height: 1.4;
    min-height: 64px;
    transition: border 0.2s ease;
    background: #ffffff;
    background-image: linear-gradient(#ffffff, #ffffff), linear-gradient(var(--color-ink), var(--color-ink));
    background-size: 8px 8px, 12px 12px;
    background-repeat: no-repeat;
    background-position: right 18px center, right 16px center;
    text-align: right;
    direction: rtl;
}

.form-control:focus {
    border-color: var(--color-ink);
    box-shadow: none;
}

.qty-control {
    display: flex;
    align-items: center;
    gap: 10px;
    direction: rtl;
}

.qty-control .form-control {
    flex: 1;
    text-align: center;
    padding: 12px;
    background-image: none;
    font-size: 1.35rem;
}

.qty-btn {
    width: 48px;
    height: 48px;
    border: 2px solid var(--color-ink);
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

.qty-btn:active {
    transform: scale(0.98);
}

.qty-btn:focus-visible {
    outline: 2px solid var(--color-ink);
    outline-offset: 2px;
}

.qty-control input[type="number"]::-webkit-outer-spin-button,
.qty-control input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.qty-control input[type="number"] {
    -moz-appearance: textfield;
}

.alert,
.alert-danger,
.alert-success {
    background: var(--color-accent-soft);
    border: 1px solid rgba(193, 18, 31, 0.25);
    color: var(--color-ink);
}

.order-success-card {
    background: #ffffff;
    border: 1px solid rgba(46, 204, 113, 0.35);
    border-radius: 18px;
    padding: 18px 20px;
    margin: 0;
    box-shadow: 0 16px 30px rgba(0, 0, 0, 0.08);
    text-align: center;
}

.order-success-banner {
    position: relative;
    margin: 16px auto 0;
    width: min(92vw, 720px);
    z-index: 5;
    animation: successSlideDown 0.35s ease;
}

.order-success-dismiss {
    margin-top: 14px;
    padding: 10px 22px;
    border-radius: 999px;
    border: none;
    background: var(--color-success);
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 18px rgba(46, 204, 113, 0.25);
}

.order-success-dismiss:focus {
    outline: 2px solid rgba(46, 204, 113, 0.45);
    outline-offset: 3px;
}

@keyframes successSlideDown {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 576px) {
    .order-success-banner {
        width: calc(100% - 24px);
        margin-top: 12px;
    }
}

.order-success-icon {
    font-size: 2.4rem;
    color: var(--color-success);
    margin-bottom: 8px;
}

.order-success-title {
    font-size: 1.8rem;
    font-weight: 800;
    margin: 0 0 6px;
    color: var(--color-ink);
}

.order-success-text {
    margin: 0 0 14px;
    color: var(--color-muted);
    font-size: 1.18rem;
}

.order-success-grid {
    display: grid;
    gap: 10px;
    background: rgba(0, 0, 0, 0.04);
    border-radius: 14px;
    padding: 12px 14px;
    text-align: right;
    font-size: 1rem;
}

.order-success-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.order-success-item .label {
    color: var(--color-muted);
    font-weight: 600;
}

.order-success-item .value {
    color: var(--color-ink);
    font-weight: 700;
}

.order-success-note {
    margin-top: 12px;
    font-weight: 700;
    color: var(--color-ink);
}

.text-danger,
.text-warning {
    color: var(--color-accent) !important;
}

.text-muted {
    color: var(--color-muted) !important;
}

select.form-control {
    text-align: right;
}

.input-icon {
    display: none;
}

.size-selector,
.color-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

.size-option,
.color-option {
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 8px 14px;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.2s ease;
    font-weight: 600;
    font-size: 1.05rem;
}

.size-option.selected {
    border-color: var(--color-accent);
    background: var(--color-accent-soft);
    color: var(--color-accent);
}

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
    overflow-anchor: none;
    contain: layout paint;
}

.order-color-select::before {
    content: "";
    position: absolute;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 76px;
    height: 4px;
    border-radius: 999px;
    background: var(--color-accent);
    opacity: 0.9;
}

.order-color-title {
    font-size: 1.55rem;
    font-weight: 800;
    margin: 10px 0 6px;
    color: var(--color-ink);
    display: block;
}

.order-color-title strong {
    color: var(--color-accent);
}

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
    box-shadow: 0 10px 20px rgba(193, 18, 31, 0.15);
    min-height: 40px;
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.order-color-error.is-visible {
    visibility: visible;
    opacity: 1;
}

.order-color-error::before {
    content: "!";
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--color-accent);
    color: #ffffff;
    font-size: 0.85rem;
    font-weight: 800;
}

.next-step-hint {
    margin-top: 12px;
    padding: 10px 14px;
    border-radius: 14px;
    border: 1px dashed rgba(193, 18, 31, 0.45);
    background: rgba(193, 18, 31, 0.08);
    color: var(--color-ink);
    font-size: 1.05rem;
    font-weight: 700;
    text-align: center;
    box-shadow: 0 10px 18px rgba(193, 18, 31, 0.08);
    min-height: 66px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.order-color-select.color-attention {
    animation: none;
    box-shadow: 0 0 0 3px rgba(193, 18, 31, 0.18), 0 18px 34px rgba(0, 0, 0, 0.1);
}

@keyframes colorPulse {
    0% { box-shadow: 0 0 0 0 rgba(193, 18, 31, 0.18); }
    50% { box-shadow: 0 0 0 6px rgba(193, 18, 31, 0.18); }
    100% { box-shadow: 0 0 0 0 rgba(193, 18, 31, 0.18); }
}
.step-highlight {
    animation: stepGlow 1.2s ease;
}

@keyframes stepGlow {
    0% {
        box-shadow: 0 0 0 0 rgba(193, 18, 31, 0.25);
    }
    50% {
        box-shadow: 0 0 0 4px rgba(193, 18, 31, 0.25);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(193, 18, 31, 0);
    }
}

.color-option {
    appearance: none;
    border: 2px solid rgba(0, 0, 0, 0.18);
    background: #ffffff;
    color: var(--color-ink);
    border-radius: 999px;
    padding: 8px 18px;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    position: relative;
    isolation: isolate;
}

.color-option::before {
    content: "";
    position: absolute;
    inset: 3px;
    border-radius: 999px;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
    opacity: 0.35;
    pointer-events: none;
}

.color-option:hover {
    transform: translateY(-2px);
}

.color-option:focus-visible {
    outline: 3px solid rgba(0, 0, 0, 0.2);
    outline-offset: 2px;
}

.color-option.selected {
    border-color: var(--color-ink);
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.18), 0 12px 22px rgba(193, 18, 31, 0.28);
    transform: translateY(-1px) scale(1.02);
    font-weight: 700;
}

.color-option.selected::before {
    opacity: 1;
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.7), inset 0 0 0 4px rgba(0, 0, 0, 0.35);
}

.color-option.selected::after {
    content: "\2713";
    position: absolute;
    top: -6px;
    right: -6px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #ffffff;
    color: #111111;
    border: 2px solid #111111;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.18);
    z-index: 1;
}

.delivery-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-top: 8px;
    min-height: 58px;
    align-content: start;
}

.delivery-options-note {
    margin-top: 10px;
    font-size: 14px;
    color: rgba(17, 24, 39, 0.72);
    min-height: 42px;
    display: flex;
    align-items: center;
}

.delivery-options-note.is-error {
    color: #b42318;
}

.delivery-btn.is-hidden {
    display: none !important;
}

.delivery-btn {
    border: 1px solid var(--color-border);
    border-radius: 12px;
    background: #ffffff;
    color: var(--color-ink);
    padding: 10px 12px;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.delivery-btn:hover {
    border-color: var(--color-ink);
}

.delivery-btn.selected {
    background: var(--color-accent-soft);
    border-color: var(--color-accent);
    color: var(--color-ink);
}

.delivery-price-tag {
    background: rgba(0, 0, 0, 0.06);
    color: var(--color-ink);
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 1.15rem;
    font-weight: 600;
}

.delivery-btn.selected .delivery-price-tag {
    background: var(--color-accent);
    color: #ffffff;
}

.delivery-info {
    position: relative;
    background: #ffffff;
    border-radius: 12px;
    border: 2px solid var(--color-ink);
    box-shadow: none;
    padding: 12px 14px;
    min-height: 118px;
    overflow-anchor: none;
    contain: layout paint;
    transition: opacity 0.2s ease;
}

.delivery-info.is-hidden {
    display: block;
    visibility: hidden;
    opacity: 0;
    pointer-events: none;
}

.delivery-info::before {
    content: none;
}

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
    color: var(--color-ink);
    font-size: 1.2rem;
}

.summary-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 1.45rem;
}

.summary-value {
    font-weight: 700;
    color: var(--color-ink);
    font-size: 1.55rem;
}

.delivery-price {
    font-size: 2rem;
}

.summary-value.total-value {
    color: var(--color-success);
    font-size: 2.3rem;
}

.delivery-price {
    color: var(--color-ink);
    font-weight: 700;
}

#total_price {
    font-weight: 700;
}

.btn-buy-now {
    position: relative;
    overflow: hidden;
    background: var(--color-success);
    border: 2px solid var(--color-success);
    color: #ffffff;
    font-weight: 700;
    padding: 16px 20px;
    border-radius: 12px;
    letter-spacing: 0.2px;
    box-shadow: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    width: 100%;
    font-size: 1.15rem;
}

.btn-buy-now::after {
    content: none;
}

.btn-buy-now:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 0 rgba(0, 0, 0, 0.2);
    filter: brightness(0.98);
}

.btn-buy-now:active {
    transform: translateY(0);
    box-shadow: 0 12px 22px rgba(0, 0, 0, 0.12);
}

.btn-buy-now:focus-visible {
    outline: 3px solid rgba(0, 0, 0, 0.3);
    outline-offset: 2px;
}

.privacy-note {
    font-size: 0.95rem;
    color: var(--color-muted);
    margin-top: 10px;
    text-align: center;
}

.order-cta {
    display: flex;
    justify-content: center;
    margin-top: 12px;
}

.order-cta .btn-buy-now {
    width: 100%;
}

.form-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    border-top: 1px solid rgba(0, 0, 0, 0.15);
    border-bottom: 1px solid rgba(0, 0, 0, 0.15);
    padding: 10px 0;
    margin: 10px 0 18px;
    text-align: center;
    font-size: 0.85rem;
}

.form-features .feature-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-weight: 700;
    color: var(--color-ink);
}

.form-features .feature-item i {
    color: var(--color-ink);
}

.form-features .feature-item:not(:last-child) {
    border-inline-start: 1px solid rgba(0, 0, 0, 0.15);
    padding-inline-start: 6px;
}

.trust-strip {
    padding: 8px 0 4px;
}

.trust-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-items: stretch;
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
    flex: 1 1 calc(25% - 10px);
}

.section {
    padding: 50px 0;
}

.section.steps,
.section.details,
.section.faq {
    padding: 12px 0;
}

@media (max-width: 1024px) {
    .landing-page .trust-grid {
        display: flex !important;
        flex-wrap: wrap;
    }

    .landing-page .trust-chip {
        flex: 1 1 calc(50% - 10px);
        width: auto !important;
    }
}

@media (max-width: 640px) {
    .landing-page .trust-grid {
        gap: 8px;
    }
}

.section-title {
    font-family: var(--font-display);
    font-size: 2rem;
    margin-bottom: 12px;
}

.section-subtitle {
    color: var(--color-muted);
    margin-bottom: 24px;
    line-height: 1.8;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
}

.benefit-card {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
    display: grid;
    gap: 10px;
}

.benefit-card h3 {
    margin: 0;
    font-size: 1.1rem;
}

.benefit-card p {
    margin: 0;
    color: var(--color-muted);
    line-height: 1.7;
}

.benefit-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--color-accent-soft);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-accent);
    font-size: 1.2rem;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
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

.step-card h3 {
    margin: 0 0 8px;
    font-size: 1.1rem;
}

.step-card p {
    margin: 0;
    color: var(--color-muted);
    line-height: 1.7;
}

.proof-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
}

.review-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid var(--color-border);
    padding: 18px;
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.05);
}

.review-card p {
    margin: 12px 0 16px;
    color: var(--color-ink);
    line-height: 1.7;
}

.review-stars {
    color: var(--color-accent);
}

.review-meta {
    color: var(--color-muted);
    font-size: 0.85rem;
}

.landing-photos {
    padding: 8px 0 24px;
}

.landing-photos .container {
    max-width: 480px;
    width: min(100%, 480px);
    padding-inline: 0 !important;
}

.landing-photos-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-top: 0;
}

.landing-photo {
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0;
    box-shadow: none;
    width: 100%;
    max-width: 100%;
    margin: 0;
}

.landing-photo img {
    width: 100%;
    max-width: 100%;
    height: auto;
    border-radius: 0;
    display: block;
    margin: 0;
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

.details-text {
    font-size: 1.3rem;
}

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

.faq-grid summary {
    font-weight: 600;
    cursor: pointer;
}

.faq-grid p {
    margin: 10px 0 0;
    color: var(--color-muted);
    line-height: 1.7;
}

.final-cta {
    padding: 50px 0 70px;
}

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
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    display: none;
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
}

.sticky-cta button:hover {
    background: var(--color-accent-dark);
}

.reveal {
    opacity: 1;
    transform: none;
    animation: none;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 992px) {
    .hero-grid {
        grid-template-columns: 1fr;
    }

    .order-card {
        order: 2;
    }

    .hero-left {
        order: 1;
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2.1rem;
    }

    .price-current {
        font-size: 1.6rem;
    }

    .announcement-bar {
        padding: 14px 0;
    }

    .announcement-item {
        font-size: 1.35rem;
    }

    .landing-header {
        padding: 14px 0 20px;
        margin-bottom: 0;
    }

    .landing-carousel {
        padding: 20px 0 28px;
    }

    .landing-carousel-main {
        padding-bottom: 18px;
        min-height: clamp(260px, 68vw, 540px);
    }

    .landing-carousel-frame {
        padding: 0;
        border-radius: 0;
    }

    .landing-carousel-title {
        font-size: 1.35rem;
        padding: 8px 12px;
        margin-bottom: 10px;
    }

    .landing-carousel-item {
        padding: 0;
        border-radius: 14px;
    }

    .landing-carousel-item img {
        height: 100%;
    }

    .landing-carousel-thumb {
        height: 52px;
    }

    .landing-carousel-thumbs {
        margin-top: 8px;
        padding: 6px;
        border-radius: 14px;
    }

    .landing-carousel-nav {
        padding: 0 6px;
    }

    .landing-carousel-btn {
        width: 36px;
        height: 36px;
    }

    .landing-carousel-btn svg {
        width: 16px;
        height: 16px;
    }

    .landing-order {
        padding: 28px 0 20px;
    }

    .landing-header-inner {
        gap: 8px;
    }

    .landing-menu-btn {
        width: 38px;
        height: 38px;
    }

    .landing-logo img {
        height: 60px;
        max-width: min(220px, 75vw);
    }

    .form-row {
        flex-direction: column;
    }

    .form-group.col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }

    .order-form-title {
        font-size: 2.5rem;
    }

    .order-color-title {
        font-size: 1.2rem;
    }

    .order-color-note {
        font-size: 0.9rem;
    }

    .form-group label {
        font-size: 1.6rem;
        font-weight: 800;
    }

    .form-control,
    .qty-control .form-control {
        font-size: 1.2rem;
    }

    .order-now-text h3 {
        font-size: 1.25rem;
    }

    .order-now-text p {
        font-size: 1.05rem;
        line-height: 1.8;
    }

    .offer-card {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 82px;
        padding: 8px 10px;
    }

    .offer-old {
        font-size: 1.2rem;
    }

    .offer-new {
        font-size: 1.55rem;
    }

    .offer-qty-price {
        font-size: 1.2rem;
    }

    .offer-badge {
        font-size: 1.05rem;
        padding: 6px 12px;
    }

    .offer-thumb {
        width: 78px;
        height: 78px;
    }

    .qty-btn {
        font-size: 22px;
    }

    .size-option,
    .color-option,
    .summary-label,
    .summary-value {
        font-size: 1.3rem;
    }

    .delivery-price {
        font-size: 1.7rem;
    }

    .summary-value.total-value {
        font-size: 1.9rem;
    }

    .delivery-btn {
        font-size: 1rem;
    }

    .delivery-price-tag {
        font-size: 1.7rem;
    }

    .btn-buy-now {
        font-size: 1.05rem;
    }

    .privacy-note {
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .landing-page .container {
        padding-inline: 10px;
    }

    .order-offers-top {
        margin: 8px auto 18px;
        max-width: 100%;
    }

    .offer-title {
        font-size: 1.1rem;
        line-height: 1.45;
        padding: 10px 14px;
        border-radius: 18px;
        margin-bottom: 8px;
    }

    .offer-grid {
        gap: 10px;
    }

    .offer-card {
        grid-template-columns: minmax(0, 1fr) 82px;
        grid-template-areas:
            "price thumb"
            "details thumb";
        gap: 8px 10px;
        padding: 10px;
        border-radius: 14px;
    }

    .offer-price-stack {
        grid-area: price;
        align-items: flex-start;
    }

    .offer-details {
        grid-area: details;
        align-items: flex-start;
        text-align: right;
    }

    .offer-old {
        font-size: 0.95rem;
        line-height: 1.25;
        word-break: break-word;
    }

    .offer-new {
        font-size: 1.25rem;
        line-height: 1.1;
        word-break: break-word;
    }

    .offer-qty-price {
        font-size: 1rem;
        line-height: 1.25;
        word-break: break-word;
    }

    .offer-badge {
        font-size: 0.9rem;
        padding: 5px 10px;
        border-radius: 999px;
        white-space: normal;
    }

    .offer-special-label {
        font-size: 1.18rem;
    }

    .offer-special-desc {
        font-size: 1.08rem;
        line-height: 1.8;
    }

    .offer-thumb {
        grid-area: thumb;
        width: 82px;
        height: 82px;
        justify-self: end;
        align-self: center;
    }
}

@media (max-width: 390px) {
    .landing-page .container {
        padding-inline: 8px;
    }

    .offer-title {
        font-size: 1rem;
        padding: 9px 12px;
    }

    .offer-card {
        grid-template-columns: minmax(0, 1fr) 72px;
        gap: 7px 8px;
        padding: 8px;
    }

    .offer-old {
        font-size: 0.88rem;
    }

    .offer-new {
        font-size: 1.1rem;
    }

    .offer-qty-price {
        font-size: 0.92rem;
    }

    .offer-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
    }

    .offer-special-label {
        font-size: 1.06rem;
    }

    .offer-special-desc {
        font-size: 1rem;
        line-height: 1.72;
    }

    .offer-thumb {
        width: 72px;
        height: 72px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .reveal {
        animation: none;
        opacity: 1;
        transform: none;
    }

    .btn-cta,
    .btn-buy-now {
        transition: none;
    }
}

.order-card.has-offers .quantity-group {
    display: none;
}

.order-offers {
    margin: 16px 0 10px;
    display: grid;
    gap: 12px;
}

.order-offers-top {
    margin: 10px auto 22px;
    max-width: 640px;
    width: 100%;
    overflow-anchor: none;
    contain: layout paint;
    box-sizing: border-box;
    padding: 18px 16px;
    border-radius: 18px;
    background: #f3f4f6;
    border: 1px solid rgba(17, 24, 39, 0.08);
}

.offer-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.7rem;
    text-align: center;
    margin-bottom: 6px;
    padding: 0;
    border-radius: 0;
    background: transparent;
    color: #111827;
    text-shadow: none;
    animation: none;
}

@keyframes offerPulse {
    0% {
        opacity: 0.65;
        transform: scale(0.98);
        box-shadow: 0 0 0 rgba(193, 18, 31, 0.0);
    }
    50% {
        opacity: 1;
        transform: scale(1.02);
        box-shadow: 0 0 18px rgba(193, 18, 31, 0.25);
    }
    100% {
        opacity: 0.7;
        transform: scale(0.99);
        box-shadow: 0 0 0 rgba(193, 18, 31, 0.0);
    }
}

.offer-grid {
    display: grid;
    gap: 12px;
    width: 100%;
}

.offer-card {
    background: #ffffff;
    border: 2px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px 14px;
    display: grid;
    grid-template-columns: 1fr 108px;
    grid-template-areas:
        "content thumb";
    gap: 10px 12px;
    align-items: center;
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    direction: rtl;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    position: relative;
    padding-inline-end: 56px;
}

.offer-card:hover {
    border-color: rgba(239, 68, 68, 0.85);
    box-shadow: 0 10px 18px rgba(0, 0, 0, 0.06);
}

.offer-card.selected {
    border-color: rgba(239, 68, 68, 1);
    background: #fef2f2;
    box-shadow: 0 10px 18px rgba(239, 68, 68, 0.12);
}

.offer-card.is-popular {
    border-color: rgba(239, 68, 68, 1);
    background: #fef2f2;
    box-shadow: 0 8px 14px rgba(239, 68, 68, 0.12);
}

.offer-card.selected.is-popular {
    border-color: rgba(239, 68, 68, 1);
    background: #fef2f2;
    box-shadow: 0 10px 18px rgba(239, 68, 68, 0.14);
}

.offer-popular-badge {
    position: absolute;
    top: -12px;
    left: 16px;
    background: #dc2626;
    color: #ffffff;
    border: none;
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 0.9rem;
    line-height: 1;
    box-shadow: 0 12px 22px rgba(220, 38, 38, 0.22);
    pointer-events: none;
    z-index: 2;
}

.offer-select-dot {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    border-radius: 999px;
    border: 2px solid rgba(239, 68, 68, 0.9);
    background: #ffffff;
    box-sizing: border-box;
}

.offer-card.selected .offer-select-dot,
.offer-card.is-popular .offer-select-dot {
    background: rgba(239, 68, 68, 1);
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
}

.offer-content {
    grid-area: content;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-width: 0;
}

.offer-price-stack {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
    min-width: 0;
}

.offer-old {
    font-size: 0.98rem;
    color: rgba(107, 114, 128, 1);
    text-decoration: line-through;
}

.offer-new {
    color: rgba(220, 38, 38, 1);
    font-size: 1.75rem;
    font-weight: 900;
}

.offer-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-start;
    text-align: right;
    direction: rtl;
    min-width: 0;
}

.offer-qty-price {
    font-weight: 800;
    font-size: 1.15rem;
    color: #111827;
    direction: ltr;
}

.offer-badge {
    background: #dcfce7;
    color: #166534;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 900;
    font-size: 0.82rem;
    width: fit-content;
    max-width: 100%;
}

.offer-card--special .offer-details {
    align-items: flex-start;
    text-align: right;
    gap: 8px;
}

.offer-card--special {
    align-items: start;
}

.offer-special-label {
    font-weight: 800;
    font-size: 1.4rem;
    color: var(--color-ink);
}

.offer-special-desc {
    color: #243240;
    font-size: 1.28rem;
    font-weight: 600;
    line-height: 1.9;
    display: block;
    overflow: visible;
    white-space: normal;
    word-break: break-word;
}

.offer-thumb {
    grid-area: thumb;
    width: 92px;
    height: 92px;
    border-radius: 14px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
    display: grid;
    place-items: center;
    overflow: hidden;
}

.offer-thumb img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
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
                    التوصيل متوفر لجميع الولايات: 400 دج للمكتب، و500 دج أو 600 دج للمنزل حسب الولاية.
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

                

                <style>
                /* Modern Checkout Form Overrides */
                .modern-checkout-form {
                    direction: rtl;
                    /* مهم: لا نريد "بطاقة داخل بطاقة" لأن الغلاف الخارجي هو الـ order-card */
                    background: transparent;
                    border: none;
                    border-radius: 0;
                    padding: 0;
                    box-shadow: none;
                    margin-bottom: 0;
                }
                .modern-checkout-form .checkout-title {
                    display: none;
                }
                .modern-checkout-form .form-group {
                    margin-bottom: 20px;
                    display: flex;
                    flex-direction: column;
                }
                .modern-checkout-form label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #374151;
                    font-size: 1.1rem;
                    text-align: right;
                }
                .modern-checkout-form .form-control,
                .modern-checkout-form select.form-control {
                    width: 100% !important;
                    height: 52px !important;
                    min-height: 52px !important;
                    padding: 0 16px !important;
                    font-size: 1.15rem !important;
                    border: 1px solid #6b7280 !important;
                    border-radius: 8px !important;
                    background: #ffffff !important;
                    transition: all 0.2s !important;
                    box-shadow: none !important;
                    text-align: right !important;
                    background-image: none !important;
                    appearance: auto;
                }
                .modern-checkout-form .form-control:focus {
                    border-color: #3b82f6 !important;
                    background: #fff !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
                }
                .modern-checkout-form .phone-wrapper {
                    display: flex !important;
                    flex-direction: row-reverse !important;
                    border: 1px solid #6b7280 !important;
                    border-radius: 8px !important;
                    height: 52px !important;
                    background: #ffffff !important;
                    direction: rtl !important;
                    overflow: hidden !important;
                    transition: all 0.2s !important;
                    width: 100% !important;
                }
                .modern-checkout-form .phone-wrapper:focus-within {
                    border-color: #3b82f6 !important;
                    background: #ffffff !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
                }
                .modern-checkout-form .phone-wrapper input {
                    flex: 1 !important;
                    border: none !important;
                    background: transparent !important;
                    padding: 0 16px !important;
                    height: 100% !important;
                    min-height: 100% !important;
                    direction: rtl !important;
                    text-align: right !important;
                    outline: none !important;
                    width: 100% !important;
                    font-size: 1.15rem !important;
                }
                .modern-checkout-form .phone-wrapper .country-code {
                    background: #f3f4f6 !important;
                    padding: 0 16px !important;
                    border-left: none !important;
                    border-right: 1px solid #6b7280 !important;
                    display: flex !important;
                    align-items: center !important;
                    font-weight: 700 !important;
                    color: #374151 !important;
                    direction: ltr !important;
                    font-size: 1.15rem !important;
                }
                .modern-checkout-form .grid-2-cols {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin-bottom: 20px;
                }
                .modern-checkout-form .grid-2-cols .form-group {
                    margin-bottom: 0;
                }
                .modern-checkout-form .delivery-options {
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                    flex-wrap: nowrap;
                }
                .modern-checkout-form .delivery-btn {
                    flex: 0 1 190px;
                    max-width: 190px !important;
                    border: 2px solid #e5e7eb !important;
                    border-radius: 12px !important;
                    padding: 8px 10px !important;
                    background: #fff !important;
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center !important;
                    justify-content: center !important;
                    cursor: pointer !important;
                    transition: all 0.2s !important;
                    height: 80px !important;
                }
                @media (max-width: 576px) {
                    .modern-checkout-form .delivery-options {
                        flex-wrap: nowrap !important;
                        gap: 8px !important;
                    }
                    .modern-checkout-form .delivery-btn {
                        flex: 1 1 calc(50% - 4px) !important;
                        max-width: calc(50% - 4px) !important;
                        min-width: 0 !important;
                    }
                }
                .modern-checkout-form .delivery-btn.selected {
                    border-color: #3b82f6 !important;
                    background: #f0fdf4 !important;
                }
                .modern-checkout-form .delivery-btn .radio-icon {
                    width: 18px !important;
                    height: 18px !important;
                    border-radius: 50% !important;
                    border: 2px solid #d1d5db !important;
                    margin-bottom: 8px !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    transition: all 0.2s !important;
                }
                .modern-checkout-form .delivery-btn.selected .radio-icon {
                    border-color: #3b82f6 !important;
                }
                .modern-checkout-form .delivery-btn.selected .radio-icon::after {
                    content: '' !important;
                    width: 10px !important;
                    height: 10px !important;
                    background: #3b82f6 !important;
                    border-radius: 50% !important;
                }
                .modern-checkout-form .delivery-label {
                    font-weight: 700 !important;
                    font-size: 1.15rem !important;
                    color: #111827 !important;
                    margin-bottom: 2px !important;
                }
                .modern-checkout-form .delivery-desc {
                    font-size: 0.95rem !important;
                    color: #6b7280 !important;
                }
                .modern-checkout-form .delivery-options-note {
                    font-size: 0.85rem;
                    color: #6b7280;
                    margin-top: 8px;
                    text-align: right;
                }
                .modern-checkout-form .btn-buy-now {
                    width: 100% !important;
                    height: 56px !important;
                    border-radius: 8px !important;
                    font-size: 1.25rem !important;
                    margin-top: 6px !important;
                    transform: translateY(-4px);
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                }
                .modern-checkout-form .qty-control {
                    display: flex;
                    align-items: center;
                    border: 1px solid #6b7280;
                    border-radius: 8px;
                    height: 52px;
                    overflow: hidden;
                    background: #fff;
                    width: 100%;
                }
                .modern-checkout-form .qty-control input {
                    border: none !important;
                    text-align: center !important;
                    font-weight: 600 !important;
                    height: 100% !important;
                    min-height: 100% !important;
                }
                .modern-checkout-form .qty-control .qty-btn {
                    width: 52px;
                    height: 100%;
                    background: #f3f4f6;
                    border: none;
                    font-size: 1.2rem;
                    cursor: pointer;
                    transition: background 0.2s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .modern-checkout-form .qty-control .qty-btn:hover {
                    background: #e5e7eb;
                }
                </style>

                <form action="" method="post" id="orderForm" class="modern-checkout-form">
                    <h3 class="checkout-title">إتمام عملية الطلب 📦</h3>
                    <input type="hidden" name="form1" value="1">
                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token']) ?>">
                    <?php if (!empty($offers)): ?>
                        <input type="hidden" name="selected_offer_id" id="selected_offer_id" value="<?= htmlspecialchars($default_offer_id, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    
                    <div class="form-group form-field position-relative">
                        <label for="customer_name">الاسم الكامل *</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required placeholder="اكتب اسمك هنا..." value="<?= isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : '' ?>">
                    </div>
                    
                    <div class="form-group form-field position-relative">
                        <label for="customer_phone">رقم الهاتف *</label>
                        <div class="phone-wrapper">
                            <div class="country-code">
                                <img src="https://flagcdn.com/w20/dz.png" alt="DZ"> +213
                            </div>
                            <input type="tel" id="customer_phone" name="customer_phone" required 
                                   placeholder="551 23 45 67"  
                                   pattern="[0-9]{9,10}" 
                                   title="يرجى إدخال رقم هاتف جزائري صحيح"
                                   value="<?= isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : '' ?>">
                        </div>
                        <div id="phone-error" class="text-danger mt-1" style="display: none; font-size: 0.9rem;"></div>
                        <div id="phone-error-duplicate-removed" class="text-danger mt-1" style="display: none; font-size: 0.9rem;"></div>
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

                    <div class="form-group">
                        <label>نوع التوصيل *</label>
                        <div class="delivery-options">
                            <?php if ($product_delivery_mode === 'free'): ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('free', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">توصيل مجاني</span>
                                    <span class="delivery-desc text-muted">مريح</span>
                                </button>
                            <?php elseif ($product_delivery_mode === 'home_only'): ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">للمنزل</span>
                                    <span class="delivery-desc text-muted">مريح</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">للمنزل</span>
                                    <span class="delivery-desc text-muted">مريح</span>
                                </button>
                                <button type="button" class="delivery-btn" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('office', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="radio-icon"></div>
                                    <span class="delivery-label">للمكتب (Stop Desk)</span>
                                    <span class="delivery-desc" style="color: #059669; font-weight: bold;">أرخص وأسرع</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="delivery-options-note" id="deliveryOptionsNote">اختر الولاية لعرض خيارات التوصيل المتاحة.</div>
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
            <button type="button" class="btn-cta" onclick="document.getElementById('orderFormSection').scrollIntoView({behavior: 'smooth'})">
                <i class="fa fa-shopping-bag"></i> اطلب الآن
            </button>
        </div>
    </section>

    <div id="sticky-scroll-btn" class="sticky-cta">
        <button type="button" onclick="document.getElementById('orderFormSection').scrollIntoView({behavior: 'smooth'})">
            <i class="fa fa-shopping-bag"></i> اطلب الآن
        </button>
    </div>

    <script>
    window.addEventListener('scroll', function() {
        var form = document.getElementById('orderFormSection');
        var btn = document.getElementById('sticky-scroll-btn');
        if (form && btn) {
            var rect = form.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > window.innerHeight) {
                btn.style.display = 'block';
            } else {
                btn.style.display = 'none';
            }
        }
    });
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

<script>
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
        const name = String(commune || '').trim();
        if (!name) return;
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        communeSelect.appendChild(option);
    });

    if (previous && Array.from(communeSelect.options).some(o => String(o.value) === previous)) {
        communeSelect.value = previous;
    } else {
        communeSelect.value = '';
    }
}

function formatDeliveryPrice(price) {
    const numericPrice = Number(price || 0);
    return Number.isInteger(numericPrice) ? (numericPrice + ' دج') : (numericPrice.toFixed(2) + ' دج');
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

    const hasAvailable = productDeliveryMode === 'free'
        ? true
        : (!!selectedWilaya && Object.keys(availableOptions).length > 0);

    // تجميد كل زر على حدة حسب توفر سعر نوعه في الولاية المختارة.
    // إذا نوع التوصيل غير موجود/سعره 0 => الزر يتجمد وحده.
    deliveryButtons.forEach((btn) => {
        const type = (btn.getAttribute('data-type') || '').trim();
        const isTypeAvailable = productDeliveryMode === 'free'
            ? true
            : (!!selectedWilaya && Object.prototype.hasOwnProperty.call(availableOptions, type));
        btn.disabled = !isTypeAvailable;
        btn.style.opacity = !isTypeAvailable ? '0.55' : '';
        btn.style.cursor = !isTypeAvailable ? 'not-allowed' : '';
    });
    if (submitButton) {
        submitButton.disabled = !hasAvailable;
        submitButton.style.opacity = !hasAvailable ? '0.6' : '';
        submitButton.style.cursor = !hasAvailable ? 'not-allowed' : '';
    }

    // إذا الزر المختار أصبح غير متوفر، نشيل الاختيار ونحوّله لأول زر متاح.
    deliveryButtons.forEach((button) => {
        if (button.disabled) {
            button.classList.remove('selected');
        }
    });

    // Always ensure at least one enabled button is selected
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
        if (productDeliveryMode !== 'free' && !selectedWilaya) {
            note.textContent = 'اختر الولاية لعرض خيارات التوصيل المتاحة.';
            note.classList.remove('is-error');
        } else if (productDeliveryMode !== 'free' && selectedWilaya && Object.keys(availableOptions).length === 0) {
            note.textContent = 'لا يوجد توصيل متاح لهذه الولاية/هذا النوع (الأسعار غير مُدخلة).';
            note.classList.add('is-error');
        } else {
            note.textContent = 'اختر طريقة التوصيل المناسبة لك.';
            note.classList.remove('is-error');
        }
    }

    return availableOptions;
}

// تحديث أسعار التوصيل بجانب الأزرار
function updateDeliveryPrices() {
    const wilayaSelectEl = wilayaSelect || document.getElementById('wilaya');
    if (!wilayaSelectEl) {
        return;
    }
    const wilaya = wilayaSelectEl.value.trim();
    const deliveryButtons = Array.from(document.querySelectorAll('.delivery-btn'));
    const availableOptions = updateDeliveryOptionsState();

    deliveryButtons.forEach((button) => {
        const priceTag = button.querySelector('.delivery-price-tag');
        if (!priceTag) {
            return;
        }

        const deliveryType = (button.getAttribute('data-type') || '').trim();
        if (Object.prototype.hasOwnProperty.call(availableOptions, deliveryType)) {
            priceTag.textContent = formatDeliveryPrice(availableOptions[deliveryType]);
        } else if (productDeliveryMode === 'free') {
            priceTag.textContent = '0 دج';
        } else {
            priceTag.textContent = '--';
        }
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
            option.textContent = wilaya.id + ' - ' + wilaya.name;
            wilayaSelect.appendChild(option);
          });

        // تحديث البلديات
        wilayaSelect.addEventListener('change', function () {
            const communeSelect = document.getElementById('commune');
            if (!communeSelect) {
                return;
            }
            communeSelect.innerHTML = '<option value="">اختر البلدية</option>';
            const selectedWilaya = this.value.trim();
            if (selectedWilaya && typeof communesList !== 'undefined') {
                const wilayaId = wilayasList.find(w => w.name === selectedWilaya)?.id;
                if (wilayaId && communesList[wilayaId]) {
                    const allCommunes = communesList[wilayaId].slice();
                    rebuildCommuneOptions(allCommunes);
                    updateDeliveryPrices();
                    updateTotalPrice();
                    return;
                }
            }
            updateDeliveryPrices();
            updateTotalPrice();
        });

        // عند تغيير الولاية، حدث أسعار التوصيل
        wilayaSelect.addEventListener('change', function() {
            updateDeliveryPrices();
            updateTotalPrice();
        });
    }

    const communeSelect = document.getElementById('commune');
    if (communeSelect) {
        communeSelect.addEventListener('change', function() {
            refreshCommuneDeliveryAvailability();
        });
    }

    deliveryBtns = Array.from(document.querySelectorAll('.delivery-btn'));
    deliveryTypeInput = document.getElementById('deliveryTypeInput');

    deliveryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            deliveryBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            if (deliveryTypeInput) {
                deliveryTypeInput.value = this.getAttribute('data-type') || '';
            }
            updateTotalPrice();
            updateDeliveryStepHint(true);
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


// إضافة معالج النموذج مع منع الضغط المتكرر المحسن
document.querySelector('form').addEventListener('submit', function(e) {
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


<style>
/* Override for mobile delivery options to keep them side by side */
@media (max-width: 768px) {
    .modern-checkout-form .delivery-options,
    #orderForm .delivery-options,
    .delivery-options {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        justify-content: space-between !important;
        gap: 8px !important;
    }
    .modern-checkout-form .delivery-btn,
    #orderForm .delivery-btn,
    .delivery-btn {
        flex: 1 1 calc(50% - 4px) !important;
        max-width: calc(50% - 4px) !important;
        min-width: 0 !important;
        width: 100% !important;
    }
}
</style>
