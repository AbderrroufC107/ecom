<?php
// إعدادات الترميز الشاملة
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=3600, must-revalidate');

ob_start('ob_gzhandler');
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';
require_once __DIR__ . '/admin/inc/CSRF_Protect.php';
$csrf = new CSRF_Protect();

// -------------------------------------------------------
// Asset versioning helper for cache busting
// -------------------------------------------------------
if (!function_exists('asset_version')) {
    function asset_version($relative_path) {
        $absolute = __DIR__ . '/' . ltrim($relative_path, '/');
        if (is_file($absolute)) {
            return (int)filemtime($absolute);
        }
        return 0;
    }
}
if (!function_exists('asset_url')) {
    function asset_url($relative_path) {
        $v = asset_version($relative_path);
        return $relative_path . ($v ? '?v=' . $v : '');
    }
}

// -------------------------------------------------------
// File-based caching helper for expensive DB queries
// -------------------------------------------------------
if (!function_exists('cache_get_or_set')) {
    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    function cache_get_or_set($key, $ttl = 86400) {
        global $cache_dir;
        $file = $cache_dir . '/' . md5($key) . '.json';
        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        return null;
    }
    function cache_set($key, $data) {
        global $cache_dir;
        $file = $cache_dir . '/' . md5($key) . '.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
$error_message = '';
$success_message = '';
$error_message1 = '';
$success_message1 = '';

front_bootstrap_language($pdo);

$settings = cache_get_or_set('front_settings');
if ($settings === null) {
    $settings = front_get_settings($pdo);
    cache_set('front_settings', $settings);
}
$logo = $settings['logo'] ?? '';
$favicon = $settings['favicon'] ?? '';
$contact_email = $settings['contact_email'] ?? '';
$contact_phone = $settings['contact_phone'] ?? '';
$meta_title_home = $settings['meta_title_home'] ?? '';
$meta_keyword_home = $settings['meta_keyword_home'] ?? '';
$meta_description_home = $settings['meta_description_home'] ?? '';
$before_head = $settings['before_head'] ?? '';
$after_body = $settings['after_body'] ?? '';
$facebook_pixel_id = $settings['facebook_pixel_id'] ?? '';
$tiktok_pixel_id = $settings['tiktok_pixel_id'] ?? '';
$telegram_bot_token = $settings['telegram_bot_token'] ?? '';
$telegram_chat_id = $settings['telegram_chat_id'] ?? '';
$telegram_orders_enabled = isset($settings['telegram_orders_enabled']) ? (int)$settings['telegram_orders_enabled'] : 0;
$telegram_incomplete_enabled = isset($settings['telegram_incomplete_enabled']) ? (int)$settings['telegram_incomplete_enabled'] : 0;
$telegram_incomplete_chat_id = $settings['telegram_incomplete_chat_id'] ?? '';
$telegram_incomplete_bot_token = $settings['telegram_incomplete_bot_token'] ?? '';
if ($telegram_incomplete_chat_id === '') {
    $telegram_incomplete_chat_id = $telegram_chat_id;
}

$logo_url = trim((string)get_front_image_url($logo));
$favicon_url = trim((string)get_front_image_url($favicon));
$logo_fallback_text = trim((string)$meta_title_home);
if ($logo_fallback_text === '') {
    $logo_fallback_text = 'Store';
}

// Meta (Facebook) Pixel Advanced Matching (server-rendered from checkout form when available)
// We only pass non-sensitive fields the user already entered (e.g. phone/name). Meta will hash values in the browser.
$meta_adv_match = [];
try {
    $raw_phone = trim((string)($_POST['customer_phone'] ?? ''));
    if ($raw_phone !== '') {
        // Normalize Algerian phone to E.164-like: +213XXXXXXXXX (strip spaces, allow leading 0)
        $digits = preg_replace('/\D+/', '', $raw_phone);
        if ($digits !== '') {
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }
            if (str_starts_with($digits, '213')) {
                $digits = substr($digits, 3);
            }
            // keep last 9 digits if user typed extra
            if (strlen($digits) > 9) {
                $digits = substr($digits, -9);
            }
            if (strlen($digits) >= 8) {
                $meta_adv_match['ph'] = '+213' . $digits;
            }
        }
    }

    $raw_name = trim((string)($_POST['customer_name'] ?? ''));
    if ($raw_name !== '') {
        $parts = preg_split('/\s+/', $raw_name, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($parts)) {
            $meta_adv_match['fn'] = $parts[0];
            if (count($parts) > 1) {
                $meta_adv_match['ln'] = $parts[count($parts) - 1];
            }
        }
    }
} catch (Exception $e) {
    $meta_adv_match = [];
}
$meta_adv_match_json = !empty($meta_adv_match)
    ? json_encode($meta_adv_match, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '{}';

if (!function_exists('get_image_dimensions')) {
    function get_image_dimensions($image_value, $fallback_width = 180, $fallback_height = 60) {
        static $cache = [];
        $image_value = trim((string)$image_value);
        $key = $image_value . '|' . $fallback_width . 'x' . $fallback_height;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if ($image_value === '' || is_external_image_url($image_value)) {
            $cache[$key] = ['width' => (int)$fallback_width, 'height' => (int)$fallback_height];
            return $cache[$key];
        }

        if (strpos($image_value, 'assets/uploads/') === 0) {
            $image_value = substr($image_value, strlen('assets/uploads/'));
        } elseif (strpos($image_value, '../assets/uploads/') === 0) {
            $image_value = substr($image_value, strlen('../assets/uploads/'));
        }

        $full_path = __DIR__ . '/assets/uploads/' . ltrim($image_value, '/\\');
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

if (!function_exists('build_webp_srcset')) {
    function build_webp_srcset($photo, $widths = [480, 768, 1024, 1280]) {
        $photo = trim((string)$photo);
        if ($photo === '' || is_external_image_url($photo)) {
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
        return implode(', ', $srcset);
    }
}

$cur_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);
if (empty($page_preload_image) && $cur_page === 'index.php') {
    $stmt_preload = $pdo->prepare("SELECT photo FROM tbl_slider ORDER BY id ASC LIMIT 1");
    if ($stmt_preload->execute()) {
        $preload_photo = trim((string)$stmt_preload->fetchColumn());
        if (!empty($preload_photo)) {
            if (is_external_image_url($preload_photo)) {
                $page_preload_image = $preload_photo;
                $page_preload_srcset = '';
            } else {
                $page_preload_sizes = '(max-width: 992px) 92vw, 48vw';
                $page_preload_srcset = build_webp_srcset($preload_photo);
                $base = preg_replace('/\.(jpe?g|png)$/i', '', $preload_photo);
                if ($base === $preload_photo) {
                    $base = pathinfo($preload_photo, PATHINFO_FILENAME);
                }
                $preferred = __DIR__ . '/assets/uploads/' . $base . '-w768.webp';
                if (is_file($preferred)) {
                    $page_preload_image = 'assets/uploads/' . $base . '-w768.webp';
                } else {
                    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $preload_photo);
                    if ($webp && is_file(__DIR__ . '/assets/uploads/' . $webp)) {
                        $page_preload_image = 'assets/uploads/' . $webp;
                    } elseif (is_file(__DIR__ . '/assets/uploads/' . $preload_photo)) {
                        $page_preload_image = 'assets/uploads/' . $preload_photo;
                    }
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>

	<!-- Meta Tags -->
	<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
	<meta charset="utf-8">

	<!-- Favicon -->
	<?php if ($favicon_url !== ''): ?>
		<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>">
	<?php endif; ?>

	<?php
	$is_landing = !empty($is_landing_page);
	$is_index_pro = isset($page_stylesheet) && $page_stylesheet === 'assets/css/index-pro.css';
	$google_fonts_url = '';
	if (!empty($page_google_fonts)) {
		$google_fonts_url = $page_google_fonts;
	} elseif ($is_index_pro) {
		$google_fonts_url = 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Tajawal:wght@500;700&display=swap';
	}
	?>
	<?php if (!defined('EXTERNAL_IMAGE_PROXY_ENABLED') || EXTERNAL_IMAGE_PROXY_ENABLED !== false): ?>
		<link rel="preconnect" href="https://wsrv.nl" crossorigin>
	<?php endif; ?>
	<?php if ($google_fonts_url !== ''): ?>
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link rel="preload" href="<?php echo htmlspecialchars($google_fonts_url); ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="<?php echo htmlspecialchars($google_fonts_url); ?>"></noscript>
	<?php endif; ?>

	<?php
	$preload_srcset = isset($page_preload_srcset) ? trim($page_preload_srcset) : '';
	$preload_sizes = isset($page_preload_sizes) ? trim($page_preload_sizes) : '';
	?>
	<?php if (!empty($page_preload_image)): ?>
		<link rel="preload" as="image"
		      href="<?php echo htmlspecialchars($page_preload_image); ?>"
		      <?php if ($preload_srcset !== ''): ?>imagesrcset="<?php echo htmlspecialchars($preload_srcset); ?>"<?php endif; ?>
		      <?php if ($preload_sizes !== ''): ?>imagesizes="<?php echo htmlspecialchars($preload_sizes); ?>"<?php endif; ?>>
	<?php endif; ?>

	<!-- Stylesheets: single bundled file when available, fallback to individual -->
	<?php if (is_file(__DIR__ . '/assets/dist/styles.min.css')): ?>
		<link rel="preload" href="<?php echo asset_url('assets/dist/styles.min.css'); ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="<?php echo asset_url('assets/dist/styles.min.css'); ?>"></noscript>
	<?php else: ?>
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="preload" href="assets/css/font-awesome.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
	<noscript><link rel="stylesheet" href="assets/css/font-awesome.min.css"></noscript>
	<?php if (!$is_landing): ?>
		<link rel="stylesheet" href="assets/css/spacing.css">
		<link rel="stylesheet" href="assets/css/main.css">
		<link rel="stylesheet" href="assets/css/responsive.css">
	<?php endif; ?>

	<?php if (!$is_landing): ?>
		<link rel="preload" href="assets/css/owl.carousel.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/owl.carousel.min.css"></noscript>
		<link rel="preload" href="assets/css/owl.theme.default.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/owl.theme.default.min.css"></noscript>
		<link rel="preload" href="assets/css/magnific-popup.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/magnific-popup.css"></noscript>
		<link rel="preload" href="assets/css/rating.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/rating.css"></noscript>
		<link rel="preload" href="assets/css/bootstrap-touch-slider.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/bootstrap-touch-slider.css"></noscript>
		<link rel="preload" href="assets/css/animate.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/animate.min.css"></noscript>
		<link rel="preload" href="assets/css/tree-menu.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/tree-menu.css"></noscript>
		<link rel="preload" href="assets/css/select2.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
		<noscript><link rel="stylesheet" href="assets/css/select2.min.css"></noscript>
	<?php endif; ?>
	<?php endif; ?>

	<?php

	$page_content = cache_get_or_set('page_content');
	if ($page_content === null) {
	    $page_content = front_get_page_content($pdo);
	    cache_set('page_content', $page_content);
	}
	$about_meta_title = $page_content['about_meta_title'] ?? '';
	$about_meta_keyword = $page_content['about_meta_keyword'] ?? '';
	$about_meta_description = $page_content['about_meta_description'] ?? '';
	$faq_meta_title = $page_content['faq_meta_title'] ?? '';
	$faq_meta_keyword = $page_content['faq_meta_keyword'] ?? '';
	$faq_meta_description = $page_content['faq_meta_description'] ?? '';
	$blog_meta_title = $page_content['blog_meta_title'] ?? '';
	$blog_meta_keyword = $page_content['blog_meta_keyword'] ?? '';
	$blog_meta_description = $page_content['blog_meta_description'] ?? '';
	$contact_meta_title = $page_content['contact_meta_title'] ?? '';
	$contact_meta_keyword = $page_content['contact_meta_keyword'] ?? '';
	$contact_meta_description = $page_content['contact_meta_description'] ?? '';
	$pgallery_meta_title = $page_content['pgallery_meta_title'] ?? '';
	$pgallery_meta_keyword = $page_content['pgallery_meta_keyword'] ?? '';
	$pgallery_meta_description = $page_content['pgallery_meta_description'] ?? '';
	$vgallery_meta_title = $page_content['vgallery_meta_title'] ?? '';
	$vgallery_meta_keyword = $page_content['vgallery_meta_keyword'] ?? '';
	$vgallery_meta_description = $page_content['vgallery_meta_description'] ?? '';

	$cur_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);

	$page_meta_title = isset($page_meta_title) ? trim($page_meta_title) : '';
	$page_meta_description = isset($page_meta_description) ? trim($page_meta_description) : '';
	$page_meta_keywords = isset($page_meta_keywords) ? trim($page_meta_keywords) : '';
	$has_custom_meta = ($page_meta_title !== '' || $page_meta_description !== '' || $page_meta_keywords !== '');

	if ($has_custom_meta) {
		if ($page_meta_title === '') {
			$page_meta_title = $meta_title_home;
		}
		if ($page_meta_description === '') {
			$page_meta_description = $meta_description_home;
		}
		if ($page_meta_keywords === '' && !empty($meta_keyword_home)) {
			$page_meta_keywords = $meta_keyword_home;
		}
		?>
		<title><?php echo $page_meta_title; ?></title>
		<?php if (!empty($page_meta_keywords)): ?>
			<meta name="keywords" content="<?php echo $page_meta_keywords; ?>">
		<?php endif; ?>
		<?php if (!empty($page_meta_description)): ?>
			<meta name="description" content="<?php echo $page_meta_description; ?>">
		<?php endif; ?>
		<?php
	}
	
	if(!$has_custom_meta && ($cur_page == 'index.php' || $cur_page == 'login.php' || $cur_page == 'registration.php' || $cur_page == 'cart.php' || $cur_page == 'checkout.php' || $cur_page == 'forget-password.php' || $cur_page == 'reset-password.php' || $cur_page == 'product-category.php' || $cur_page == 'product.php')) {
		?>
		<title><?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}

	if(!$has_custom_meta && $cur_page == 'about.php') {
		?>
		<title><?php echo $about_meta_title; ?></title>
		<meta name="keywords" content="<?php echo $about_meta_keyword; ?>">
		<meta name="description" content="<?php echo $about_meta_description; ?>">
		<?php
	}
	if(!$has_custom_meta && $cur_page == 'faq.php') {
		?>
		<title><?php echo $faq_meta_title; ?></title>
		<meta name="keywords" content="<?php echo $faq_meta_keyword; ?>">
		<meta name="description" content="<?php echo $faq_meta_description; ?>">
		<?php
	}
	if(!$has_custom_meta && $cur_page == 'contact.php') {
		?>
		<title><?php echo $contact_meta_title; ?></title>
		<meta name="keywords" content="<?php echo $contact_meta_keyword; ?>">
		<meta name="description" content="<?php echo $contact_meta_description; ?>">
		<?php
	}
	if($cur_page == 'product.php')
	{
		$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
		$statement->execute(array($_REQUEST['id']));
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
		foreach ($result as $row) 
		{
		    $og_photo = $row['p_featured_photo'];
		    $og_title = $row['p_name'];
		    $og_slug = 'product.php?id='.$_REQUEST['id'];
			$og_description = substr(strip_tags($row['p_description']),0,200).'...';
		}
	}

	if(!$has_custom_meta && $cur_page == 'dashboard.php') {
		?>
		<title>Dashboard - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if(!$has_custom_meta && $cur_page == 'customer-profile-update.php') {
		?>
		<title>Update Profile - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if(!$has_custom_meta && $cur_page == 'customer-billing-shipping-update.php') {
		?>
		<title>Update Billing and Shipping Info - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if(!$has_custom_meta && $cur_page == 'customer-password-update.php') {
		?>
		<title>Update Password - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if(!$has_custom_meta && $cur_page == 'customer-order.php') {
		?>
		<title>Orders - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	?>
	
	<?php if($cur_page == 'blog-single.php'): ?>
		<meta property="og:title" content="<?php echo $og_title; ?>">
		<meta property="og:type" content="website">
		<meta property="og:url" content="<?php echo BASE_URL.$og_slug; ?>">
		<meta property="og:description" content="<?php echo $og_description; ?>">
		<?php $og_image_url = trim((string)get_front_image_url($og_photo ?? '')); ?>
		<?php if ($og_image_url !== ''): ?>
			<meta property="og:image" content="<?php echo htmlspecialchars($og_image_url, ENT_QUOTES, 'UTF-8'); ?>">
		<?php endif; ?>
	<?php endif; ?>

	<?php if($cur_page == 'product.php'): ?>
		<meta property="og:title" content="<?php echo $og_title; ?>">
		<meta property="og:type" content="website">
		<meta property="og:url" content="<?php echo BASE_URL.$og_slug; ?>">
		<meta property="og:description" content="<?php echo $og_description; ?>">
		<?php $og_image_url = trim((string)get_front_image_url($og_photo ?? '')); ?>
		<?php if ($og_image_url !== ''): ?>
			<meta property="og:image" content="<?php echo htmlspecialchars($og_image_url, ENT_QUOTES, 'UTF-8'); ?>">
		<?php endif; ?>
	<?php endif; ?>

<?php
// Dynamic Product Pixels
$has_dynamic_facebook = false;
$has_dynamic_tiktok = false;
$product_pixels = [];
$p_id_for_pixel = 0;
if (isset($_REQUEST['id'])) {
    $p_id_for_pixel = (int)$_REQUEST['id'];
} elseif (isset($_SESSION['purchase_pixel_data']['content_ids'][0])) {
    $p_id_for_pixel = (int)$_SESSION['purchase_pixel_data']['content_ids'][0];
}

if ($p_id_for_pixel > 0) {
    try {
        $stmt_px = $pdo->prepare("SELECT tp.* FROM tbl_pixel tp JOIN tbl_product_pixel tpp ON tp.id = tpp.pixel_id WHERE tpp.product_id = ?");
        $stmt_px->execute([$p_id_for_pixel]);
        $product_pixels = $stmt_px->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

if (!empty($product_pixels)) {
    foreach ($product_pixels as $px) {
        $network = strtolower($px['pixel_network']);
        $pid = addslashes($px['pixel_id']);
        if ($network === 'facebook') {
            $has_dynamic_facebook = true;
            $adv_json = $meta_adv_match_json;
            $adv_json = addslashes($adv_json);
            echo "<!-- Facebook Pixel Dynamic -->\n<script>\n!function(f,b,e,v,n,t,s)\n{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\nn.callMethod.apply(n,arguments):n.queue.push(arguments)};\nif(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\nn.queue=[];t=b.createElement(e);t.async=!0;\nt.src=v;s=b.getElementsByTagName(e)[0];\ns.parentNode.insertBefore(t,s)}(window, document,'script',\n'https://connect.facebook.net/en_US/fbevents.js');\nfbq('init', '{$pid}', {$adv_json});\nfbq('set', 'autoConfig', false, '{$pid}');\nfbq('track', 'PageView');\n</script>\n<noscript><img height=\"1\" width=\"1\" style=\"display:none\"\nsrc=\"https://www.facebook.com/tr?id={$pid}&ev=PageView&noscript=1\"\n/></noscript>\n<!-- End Facebook Pixel -->\n";
        } elseif ($network === 'tiktok') {
            $has_dynamic_tiktok = true;
            echo "<!-- TikTok Pixel Dynamic -->\n<script>\n!function (w, d, t) {\n  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=[\"page\",\"track\",\"identify\",\"instances\",\"debug\",\"on\",\"off\",\"once\",\"ready\",\"alias\",\"group\",\"enableCookie\",\"disableCookie\",\"holdConsent\",\"revokeConsent\",\"grantConsent\"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(\nvar e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r=\"https://analytics.tiktok.com/i18n/pixel/events.js\",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement(\"script\")\n;n.type=\"text/javascript\",n.async=!0,n.src=r+\"?sdkid=\"+e+\"&lib=\"+t;e=document.getElementsByTagName(\"script\")[0];e.parentNode.insertBefore(n,e)};\n  ttq.load('{$pid}');\n  ttq.page();\n}(window, document, 'ttq');\n</script>\n<!-- End TikTok Pixel -->\n";
        } elseif (!empty($px['pixel_script'])) {
            echo "<!-- Custom Pixel Dynamic -->\n" . $px['pixel_script'] . "\n<!-- End Custom Pixel -->\n";
        }
    }
}
?>
<?php if (!empty($facebook_pixel_id) && empty($has_dynamic_facebook)): ?>
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo addslashes($facebook_pixel_id); ?>', <?php echo $meta_adv_match_json; ?>);
fbq('set', 'autoConfig', false, '<?php echo addslashes($facebook_pixel_id); ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=<?php echo urlencode($facebook_pixel_id); ?>&ev=PageView&noscript=1"
/></noscript>
<!-- End Facebook Pixel Code -->
<?php endif; ?>

<?php if (!empty($tiktok_pixel_id) && empty($has_dynamic_tiktok)): ?>
<!-- TikTok Pixel Code Start -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(
var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")
;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};


  ttq.load('<?php echo addslashes($tiktok_pixel_id); ?>');
  ttq.page();
}(window, document, 'ttq');
</script>
<!-- TikTok Pixel Code End -->
<?php endif; ?>

<script>
window.__metaPixelEnabled = <?php echo (!empty($facebook_pixel_id) || !empty($has_dynamic_facebook)) ? 'true' : 'false'; ?>;
window.__tiktokPixelEnabled = <?php echo (!empty($tiktok_pixel_id) || !empty($has_dynamic_tiktok)) ? 'true' : 'false'; ?>;
window.__pixelEventCache = window.__pixelEventCache || {};
window.__pixelNormalizeEventData = window.__pixelNormalizeEventData || function (data) {
  var normalized = data && typeof data === 'object' ? Object.assign({}, data) : {};
  var rawIds = [];
  if (Array.isArray(normalized.content_ids)) {
    rawIds = normalized.content_ids;
  } else if (normalized.id !== undefined && normalized.id !== null && normalized.id !== '') {
    rawIds = [normalized.id];
  }
  normalized.content_ids = rawIds.map(function (id) {
    return String(id);
  });
  normalized.content_name = normalized.content_name || normalized.name || '';
  normalized.content_type = normalized.content_type || 'product';
  normalized.currency = normalized.currency || 'DZD';
  normalized.quantity = Math.max(1, Number(normalized.quantity || normalized.num_items || 1) || 1);
  normalized.value = Number(normalized.value || 0);
  return normalized;
};
window.__pixelBuildMetaPayload = window.__pixelBuildMetaPayload || function (data) {
  var normalized = window.__pixelNormalizeEventData(data);
  var itemPrice = normalized.quantity > 0 ? normalized.value / normalized.quantity : normalized.value;
  var payload = {
    content_type: normalized.content_type,
    content_name: normalized.content_name,
    content_ids: normalized.content_ids,
    value: normalized.value,
    currency: normalized.currency,
    num_items: normalized.quantity
  };
  if (normalized.content_ids.length) {
    payload.contents = normalized.content_ids.map(function (id) {
      return {
        id: id,
        quantity: normalized.quantity,
        item_price: itemPrice
      };
    });
  }
  return payload;
};
window.__pixelBuildTikTokPayload = window.__pixelBuildTikTokPayload || function (data) {
  var normalized = window.__pixelNormalizeEventData(data);
  var payload = {
    content_type: normalized.content_type,
    content_name: normalized.content_name,
    value: normalized.value,
    currency: normalized.currency,
    quantity: normalized.quantity
  };
  if (normalized.content_ids.length) {
    payload.content_id = normalized.content_ids[0];
    payload.content_ids = normalized.content_ids;
  }
  return payload;
};
window.__pixelTrackStandardEvent = window.__pixelTrackStandardEvent || function (eventName, data, options) {
  var normalized = window.__pixelNormalizeEventData(data);
  var opts = options && typeof options === 'object' ? options : {};
  var dedupeKey = opts.dedupeKey || [
    eventName,
    window.location.pathname,
    normalized.content_ids.join('|'),
    normalized.quantity
  ].join(':');
  if (!opts.allowRepeat && window.__pixelEventCache[dedupeKey]) {
    return false;
  }
  window.__pixelEventCache[dedupeKey] = true;
  if (window.__metaPixelEnabled && typeof window.fbq === 'function') {
    window.fbq('track', eventName, window.__pixelBuildMetaPayload(normalized));
  }
  if (window.__tiktokPixelEnabled && window.ttq && typeof window.ttq.track === 'function') {
    var tiktokEvent = eventName;
    if (eventName === 'Purchase') {
      tiktokEvent = 'CompletePayment';
    }
    window.ttq.track(tiktokEvent, window.__pixelBuildTikTokPayload(normalized));
  }
  return true;
};
window.__trackViewContent = window.__trackViewContent || function (data, options) {
  return window.__pixelTrackStandardEvent('ViewContent', data, options);
};
window.__trackInitiateCheckout = window.__trackInitiateCheckout || function (data, options) {
  return window.__pixelTrackStandardEvent('InitiateCheckout', data, options);
};
</script>

<?php
$purchase_pixel_payload = $_SESSION['purchase_pixel_data'] ?? null;
$purchase_pixel_fire = !empty($purchase_pixel_payload) && (!empty($facebook_pixel_id) || !empty($tiktok_pixel_id));
$pixel_debug_enabled = isset($_GET['pixel_debug']) && $_GET['pixel_debug'] === '1';
if ($purchase_pixel_fire) {
    $purchase_pixel_json = json_encode(
        $purchase_pixel_payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    echo '<script>'
        . 'window.__purchasePixelData = ' . $purchase_pixel_json . ';'
        . 'window.__purchasePixelSent = window.__purchasePixelSent || false;'
        . 'window.__firePurchasePixels = window.__firePurchasePixels || function (data) {'
        . '  if (!data || window.__purchasePixelSent) { return; }'
        . '  window.__purchasePixelSent = true;'
        . '  var payload = {'
        . '    value: Number(data.value || 0),'
        . '    currency: data.currency || "DZD",'
        . '    content_type: data.content_type || "product",'
        . '    content_name: data.content_name || "Order Purchase",'
        . '    content_ids: data.content_ids || [],'
        . '    num_items: Number(data.quantity || 1)'
        . '  };'
        . '  if (typeof window.fbq === "function") {'
        . '    window.fbq("track", "Purchase", payload);'
        . '  }'
        . '  if (window.ttq && typeof window.ttq.track === "function") {'
        . '    window.ttq.track("CompletePayment", {'
        . '      value: payload.value,'
        . '      currency: payload.currency,'
        . '      content_type: payload.content_type,'
        . '      content_name: payload.content_name,'
        . '      quantity: payload.num_items'
        . '    });'
        . '  }'
        . '};'
        . 'window.addEventListener("load", function () {'
        . '  if (!window.__purchasePixelData) { return; }'
        . '  window.setTimeout(function () {'
        . '    if (typeof window.__firePurchasePixels === "function") {'
        . '      window.__firePurchasePixels(window.__purchasePixelData);'
        . '    }'
        . '  }, 900);'
        . '});'
        . '</script>';
    unset($_SESSION['purchase_pixel_data']);
}
?>

<?php if ($pixel_debug_enabled && !empty($facebook_pixel_id)): ?>
<script>
window.addEventListener('load', function () {
  window.setTimeout(function () {
    var hasFbq = typeof window.fbq === 'function';
    var fbScript = document.querySelector('script[src*="connect.facebook.net/en_US/fbevents.js"]');
    var debugBox = document.createElement('div');
    debugBox.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:999999;background:#111;color:#fff;padding:14px 16px;border-radius:12px;font:13px/1.6 Arial,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35);max-width:360px;direction:ltr;text-align:left;';
    debugBox.innerHTML =
      '<strong style="display:block;margin-bottom:8px;">Meta Pixel Debug</strong>' +
      '<div>Pixel ID: <?php echo addslashes($facebook_pixel_id); ?></div>' +
      '<div>fbq available: ' + (hasFbq ? 'YES' : 'NO') + '</div>' +
      '<div>script tag found: ' + (fbScript ? 'YES' : 'NO') + '</div>' +
      '<div>purchase payload: ' + (window.__purchasePixelData ? 'YES' : 'NO') + '</div>' +
      '<div>purchase sent: ' + (window.__purchasePixelSent ? 'YES' : 'NO') + '</div>' +
      '<div style="margin-top:8px;color:#9ad1ff;">Disable ad blockers/privacy shields if fbq = NO.</div>';
    document.body.appendChild(debugBox);
  }, 1800);
});
</script>
<?php endif; ?>

<?php echo $before_head; ?>

<style>
.badge {
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
}
.badge-warning {
    background: #ffc107;
    color: #000;
}
.badge-info {
    background: #17a2b8;
    color: #fff;
}
.badge-primary {
    background: #007bff;
    color: #fff;
}
.badge-success {
    background: #28a745;
    color: #fff;
}
.badge-danger {
    background: #dc3545;
    color: #fff;
}

.logo-text-fallback {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 52px;
    padding: 0 12px;
    font-size: 1.3rem;
    font-weight: 800;
    color: #111;
}

/* تنسيقات جديدة لروابط تسجيل الدخول وإنشاء الحساب */
.header .right ul li a {
    display: inline-flex;
    align-items: Center;
    padding: 8px 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 600;
    margin: 0 5px;
}

/* تنسيقات جديدة لعرض معلومات المستخدم */
.user-profile {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    padding: 8px 15px;
    border-radius: 12px;
    border: 2px solid #7c3aed;
    margin: 0 5px;
    transition: all 0.3s ease;
}

.user-profile:hover {
    background: #7c3aed;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2);
}

.user-profile:hover .user-name,
.user-profile:hover .user-icon {
    color: #fff;
}

.user-icon {
    color: #7c3aed;
    font-size: 1.2rem;
    margin-left: 8px;
    transition: all 0.3s ease;
}

.user-name {
    color: #333;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.dashboard-link {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 8px;
    background: #7c3aed;
    color: #fff;
    font-weight: 600;
    transition: all 0.3s ease;
    margin: 0 5px;
}

.dashboard-link:hover {
    background: #6d28d9;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
    text-decoration: none;
}

.dashboard-link i {
    margin-left: 8px;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .user-profile {
        padding: 6px 12px;
    }
    
    .user-name {
        font-size: 0.9rem;
    }
    
    .dashboard-link {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
}
</style>

<?php if (!empty($page_stylesheet)): ?>
<link rel="stylesheet" href="<?php echo asset_url(ltrim($page_stylesheet, '/')); ?>">
<?php endif; ?>
</head>
<body class="<?php echo isset($body_class) ? htmlspecialchars($body_class) : ''; ?>">

<?php echo $after_body; ?>
<?php if (empty($is_landing_page)): ?>
<!--
<div id="preloader">
	<div id="status"></div>
</div>-->

<!-- top bar -->
<div class="top">
	<div class="container">
		<div class="row">
			<div class="col-md-6 col-sm-6 col-xs-12">
				<div class="left">
					<ul>
						<li><i class="fa fa-phone"></i> <?php echo $contact_phone; ?></li>
						<li><i class="fa fa-envelope-o"></i> <?php echo $contact_email; ?></li>
					</ul>
				</div>
			</div>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<div class="right">
					<ul>
						<?php
						$social_links = cache_get_or_set('social_links');
						if ($social_links === null) {
						    $stmt = $pdo->prepare("SELECT * FROM tbl_social");
						    $stmt->execute();
						    $social_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
						    cache_set('social_links', $social_links);
						}
						foreach ($social_links as $row) {
							?>
							<?php if($row['social_url'] != ''): ?>
							<li><a href="<?php echo $row['social_url']; ?>"><i class="<?php echo $row['social_icon']; ?>"></i></a></li>
							<?php endif; ?>
							<?php
						}
						?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>


<div class="header">
	<div class="container">
		<div class="row inner">
			<div class="col-md-4 logo">
				<a href="index.php">
					<?php if ($logo_url !== ''): ?>
						<?php $logo_dims = get_image_dimensions($logo, 180, 60); ?>
						<img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="logo image" width="<?php echo (int)$logo_dims['width']; ?>" height="<?php echo (int)$logo_dims['height']; ?>" loading="eager">
					<?php else: ?>
						<span class="logo-text-fallback"><?php echo htmlspecialchars($logo_fallback_text, ENT_QUOTES, 'UTF-8'); ?></span>
					<?php endif; ?>
				</a>
			</div>
			
			<div class="col-md-5 right">
				<ul>
					<?php if (empty($hide_auth_links)): ?>
						<?php if(isset($_SESSION['customer'])): ?>
							<li class="nav-item dropdown">
								<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
									<?php echo $_SESSION['customer']['cust_name']; ?>
								</a>
								<ul class="dropdown-menu" aria-labelledby="navbarDropdown">
									<li><a class="dropdown-item" href="dashboard.php">معلوماتي</a></li>
									<li><a class="dropdown-item" href="customer-order.php">طلباتي</a></li>
									<li><a class="dropdown-item" href="edit-profile.php">تعديل معلوماتي</a></li>
									<li><hr class="dropdown-divider"></li>
									<li><a class="dropdown-item" href="logout.php">تسجيل الخروج</a></li>
								</ul>
							</li>
						<?php else: ?>
							<li><a href="login.php"><i class="fa fa-sign-in"></i> <?php echo LANG_VALUE_9; ?></a></li>
							<li><a href="registration.php"><i class="fa fa-user-plus"></i> <?php echo LANG_VALUE_15; ?></a></li>
						<?php endif; ?>
					<?php endif; ?>


				</ul>
			</div>
			<div class="col-md-3 search-area">
				<form class="navbar-form navbar-left" role="search" action="search-result.php" method="get">
					<?php $csrf->echoInputField(); ?>
					<div class="form-group">
						<input type="text" class="form-control search-top" placeholder="<?php echo LANG_VALUE_2; ?>" name="search_text">
					</div>
					<button type="submit" class="btn btn-danger"><?php echo LANG_VALUE_3; ?></button>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="nav">
	<div class="container">
		<div class="row">
			<div class="col-md-12 pl_0 pr_0">
				<div class="menu-container">
					<div class="menu">
						<ul>
							<li><a href="index.php">Homme</a></li>
							
							<?php
							$category_tree = cache_get_or_set('category_tree');
							if ($category_tree === null) {
							    $category_tree = [];
							    $stmt_t = $pdo->prepare("SELECT * FROM tbl_top_category WHERE show_on_menu=1");
							    $stmt_t->execute();
							    foreach ($stmt_t->fetchAll(PDO::FETCH_ASSOC) as $top) {
							        $stmt_m = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id=?");
							        $stmt_m->execute([$top['tcat_id']]);
							        $mid_cats = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
							        foreach ($mid_cats as $mk => $mid) {
							            $stmt_e = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id=?");
							            $stmt_e->execute([$mid['mcat_id']]);
							            $mid_cats[$mk]['end_categories'] = $stmt_e->fetchAll(PDO::FETCH_ASSOC);
							        }
							        $top['mid_categories'] = $mid_cats;
							        $category_tree[] = $top;
							    }
							    cache_set('category_tree', $category_tree);
							}
							foreach ($category_tree as $row) {
								?>
								<li><a href="product-category.php?id=<?php echo $row['tcat_id']; ?>&type=top-category"><?php echo $row['tcat_name']; ?></a>
									<ul>
										<?php
										foreach ($row['mid_categories'] as $row1) {
											?>
											<li><a href="product-category.php?id=<?php echo $row1['mcat_id']; ?>&type=mid-category"><?php echo $row1['mcat_name']; ?></a>
												<ul>
													<?php
													foreach ($row1['end_categories'] as $row2) {
														?>
														<li><a href="product-category.php?id=<?php echo $row2['ecat_id']; ?>&type=end-category"><?php echo $row2['ecat_name']; ?></a></li>
														<?php
													}
													?>
												</ul>
											</li>
											<?php
										}
										?>
									</ul>
								</li>
								<?php
							}
							?>

							<?php
							$about_title = $page_content['about_title'] ?? '';
							$faq_title = $page_content['faq_title'] ?? '';
							$blog_title = $page_content['blog_title'] ?? '';
							$contact_title = $page_content['contact_title'] ?? '';
							$pgallery_title = $page_content['pgallery_title'] ?? '';
							$vgallery_title = $page_content['vgallery_title'] ?? '';
							?>

							<?php if (empty($hide_auth_links) && isset($_SESSION['customer'])): ?>
								<li><a href="dashboard.php"> Controle Panel</a></li>
							<?php endif; ?>

							<li><a href="about.php"><?php echo $about_title; ?></a></li>
							<li><a href="faq.php"><?php echo $faq_title; ?></a></li>
							<li><a href="contact.php"><?php echo $contact_title; ?></a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

