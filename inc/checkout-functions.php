<?php
if (defined('CHECKOUT_FUNCTIONS_LOADED')) return;
define('CHECKOUT_FUNCTIONS_LOADED', true);

/* ── Phone helpers ── */
function validateAlgerianPhoneNumber($phone) {
    $phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
    if (!preg_match('/^[0-9]+$/', $phone)) return false;
    $len = strlen($phone);
    if ($len < 9 || $len > 10) return false;
    if ($len == 10 && !preg_match('/^0[5-7][0-9]{8}$/', $phone)) return false;
    if ($len == 9 && !preg_match('/^[5-7][0-9]{8}$/', $phone)) return false;
    return true;
}

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);
    if (strlen($phone) == 9) $phone = '0' . $phone;
    return $phone;
}

/* ── Name validation ── */
function validateCustomerName($name) {
    $name = trim($name);
    if (empty($name)) return ['valid' => false, 'message' => 'الاسم مطلوب'];
    if (mb_strlen($name) < 3) return ['valid' => false, 'message' => 'يجب أن يحتوي الاسم على 3 أحرف على الأقل'];
    if (mb_strlen($name) > 50) return ['valid' => false, 'message' => 'يجب أن يكون الاسم أقل من 50 حرف'];
    if (!preg_match('/^[\p{Arabic}\p{Latin}\s]+$/u', $name)) return ['valid' => false, 'message' => 'يجب أن يحتوي الاسم على حروف عربية أو لاتينية فقط'];
    if (preg_match('/[0-9@#\$%\^&\*\(\)\+=\[\]\{\}\|\\:";\'<>,\?\/~`!]/', $name)) return ['valid' => false, 'message' => 'لا يُسمح بالأرقام أو الرموز الخاصة في الاسم'];
    if (preg_match('/(.)\1{2,}/u', $name)) return ['valid' => false, 'message' => 'لا يُسمح بتكرار نفس الحرف أكثر من مرتين متتاليتين'];
    $blacklist = ['test','testing','tester','user','client','customer','name','unknown','anonymous','aaa','bbb','ccc','ddd','eee','fff','ggg','hhh','iii','jjj','kkk','lll','mmm','nnn','ooo','ppp','qqq','rrr','sss','ttt','uuu','vvv','www','xxx','yyy','zzz','abc','xyz','qwe','asd','zxc','123','456','789','000','111','222','333','444','555','666','777','888','999','admin','administrator','root','guest','visitor','dummy','fake','spam','bot','robot','auto','automatic','system','server','api'];
    foreach ($blacklist as $forbidden) {
        if (mb_stripos($name, $forbidden, 0, 'UTF-8') !== false) return ['valid' => false, 'message' => 'الاسم يحتوي على كلمات غير مسموحة'];
    }
    if (preg_match('/\s{2,}/', $name)) return ['valid' => false, 'message' => 'لا يُسمح بوجود مسافات متعددة في الاسم'];
    if ($name !== trim($name)) return ['valid' => false, 'message' => 'لا يُسمح بوجود مسافات في بداية أو نهاية الاسم'];
    return ['valid' => true, 'message' => 'الاسم صحيح'];
}

/* ── Order checking ── */
function checkExistingOrder($pdo, $customer_phone) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE customer_phone = ? AND LOWER(order_status) = 'pending' ORDER BY order_date DESC LIMIT 1");
        $stmt->execute([$customer_phone]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            return ['exists' => true, 'order_id' => $order['order_id'] ?? $order['id'] ?? null, 'order_date' => $order['order_date'], 'product_name' => $order['product_name'], 'status' => 'pending', 'message' => 'لقد قمت بالطلب مسبقاً! انتظر للاتصال بك أو تأكيد الطلب.'];
        }
        return ['exists' => false, 'message' => 'لا توجد طلبات سابقة'];
    } catch (PDOException $e) {
        error_log('checkExistingOrder error: ' . $e->getMessage());
        return ['exists' => false, 'message' => 'خطأ في التحقق من الطلبات السابقة'];
    }
}

/* ── DB check ── */
function checkDatabaseConnection($pdo) {
    try { $pdo->query('SELECT 1'); return true; }
    catch (PDOException $e) { error_log('DB connection failed: ' . $e->getMessage()); return false; }
}

/* ── Incomplete orders ── */
function checkIncompleteOrders($pdo, $customer_phone) {
    try {
        if (!checkDatabaseConnection($pdo)) return [];
        if (!function_exists('ensure_incomplete_orders_table') || !ensure_incomplete_orders_table($pdo)) return [];
        $stmt = $pdo->prepare("SELECT * FROM incomplete_orders WHERE customer_phone = ?");
        $stmt->execute([$customer_phone]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('checkIncompleteOrders: ' . $e->getMessage()); return []; }
}

function saveIncompleteOrder($pdo, $product_id, $product_name, $customer_name, $customer_phone, $extra = []) {
    if (!checkDatabaseConnection($pdo)) return false;
    $quantity = isset($extra['quantity']) && $extra['quantity'] !== '' ? (int)$extra['quantity'] : null;
    $unit_price = isset($extra['unit_price']) && $extra['unit_price'] !== '' ? (float)$extra['unit_price'] : null;
    $total_price = isset($extra['total_price']) && $extra['total_price'] !== '' ? (float)$extra['total_price'] : null;
    $selected_size = isset($extra['selected_size']) ? trim($extra['selected_size']) : '';
    $selected_color = isset($extra['selected_color']) ? trim($extra['selected_color']) : '';
    $wilaya = isset($extra['wilaya']) ? trim($extra['wilaya']) : '';
    $commune = isset($extra['commune']) ? trim($extra['commune']) : '';
    $delivery_type = isset($extra['delivery_type']) ? trim($extra['delivery_type']) : '';
    $address = isset($extra['address']) ? trim($extra['address']) : '';

    if (function_exists('site_security_evaluate_order')) {
        $sec = site_security_evaluate_order($pdo, ['customer_name' => $customer_name, 'customer_phone' => $customer_phone, 'wilaya' => $wilaya, 'commune' => $commune, 'address' => $address, 'device_id' => $_POST['device_id'] ?? null]);
        if ($sec['action'] !== 'allow') {
            if (function_exists('site_security_record_rejected_attempt')) site_security_record_rejected_attempt($pdo, $sec);
            return false;
        }
        if (!empty($sec['context']['phone'])) $customer_phone = $sec['context']['phone'];
    }

    if (!function_exists('ensure_incomplete_orders_table') || !ensure_incomplete_orders_table($pdo)) return false;
    $customer_ip = function_exists('site_security_client_ip') ? site_security_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    $device_id = function_exists('site_security_device_id') ? site_security_device_id() : ($_POST['device_id'] ?? '');
    $user_agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $existing_id = null;
    if ($product_id !== null && $product_id !== '') {
        $s = $pdo->prepare("SELECT id FROM incomplete_orders WHERE customer_phone = ? AND product_id = ? ORDER BY created_at DESC LIMIT 1");
        $s->execute([$customer_phone, $product_id]);
    } else {
        $s = $pdo->prepare("SELECT id FROM incomplete_orders WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 1");
        $s->execute([$customer_phone]);
    }
    $existing_id = $s->fetchColumn();

    if ($existing_id) {
        $u = $pdo->prepare("UPDATE incomplete_orders SET customer_name=?, product_id=?, product_name=?, quantity=?, unit_price=?, total_price=?, selected_size=?, selected_color=?, wilaya=?, commune=?, address=?, delivery_type=?, customer_ip=?, device_id=?, user_agent=?, last_updated=NOW() WHERE id=?");
        return $u->execute([$customer_name, $product_id, $product_name, $quantity, $unit_price, $total_price, $selected_size, $selected_color, $wilaya, $commune, $address, $delivery_type, $customer_ip, $device_id, $user_agent, $existing_id]);
    }

    $i = $pdo->prepare("INSERT INTO incomplete_orders (customer_name, customer_phone, product_id, product_name, quantity, unit_price, total_price, selected_size, selected_color, wilaya, commune, address, delivery_type, customer_ip, device_id, user_agent, created_at, last_updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())");
    return $i->execute([$customer_name, $customer_phone, $product_id, $product_name, $quantity, $unit_price, $total_price, $selected_size, $selected_color, $wilaya, $commune, $address, $delivery_type, $customer_ip, $device_id, $user_agent]);
}

/* ── Debug ── */
function debug_post_data() {
    error_log('=== POST data received ===');
    error_log('$_POST: ' . print_r($_POST, true));
    error_log('$_REQUEST: ' . print_r($_REQUEST, true));
    error_log('=== END POST ===');
}

/* ── Image helpers ── */
if (!function_exists('get_image_dimensions')) {
function get_image_dimensions($relative_path, $fallback_width = 1200, $fallback_height = 1200) {
    static $cache = [];
    $key = $relative_path . '|' . $fallback_width . 'x' . $fallback_height;
    if (isset($cache[$key])) return $cache[$key];
    $full = __DIR__ . '/' . ltrim($relative_path, '/');
    if (is_file($full)) {
        $size = @getimagesize($full);
        if ($size && isset($size[0], $size[1])) { $cache[$key] = ['width' => (int)$size[0], 'height' => (int)$size[1]]; return $cache[$key]; }
    }
    $cache[$key] = ['width' => (int)$fallback_width, 'height' => (int)$fallback_height];
    return $cache[$key];
}
}

function resolve_webp_src($photo) {
    if ($photo === '') return '';
    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $photo);
    if ($webp && is_file(__DIR__ . '/assets/uploads/' . $webp)) return 'assets/uploads/' . $webp;
    return '';
}

if (!function_exists('build_webp_srcset')) {
function build_webp_srcset($photo, $widths = [480, 768, 1024, 1280]) {
    if ($photo === '') return '';
    $base = preg_replace('/\.(jpe?g|png)$/i', '', $photo);
    if ($base === $photo) $base = pathinfo($photo, PATHINFO_FILENAME);
    $srcset = [];
    foreach ($widths as $w) {
        $candidate = __DIR__ . '/assets/uploads/' . $base . '-w' . $w . '.webp';
        if (is_file($candidate)) $srcset[] = 'assets/uploads/' . $base . '-w' . $w . '.webp ' . $w . 'w';
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

function build_external_image_srcset($photo, $widths = [360, 480, 640, 768, 960], $quality = 58) {
    $photo = trim((string)$photo);
    if ($photo === '' || !function_exists('get_front_optimized_image_url')) return '';
    $srcset = [];
    foreach ($widths as $w) {
        $c = get_front_optimized_image_url($photo, (int)$w, (int)$quality);
        if ($c !== '') $srcset[] = $c . ' ' . (int)$w . 'w';
    }
    return empty($srcset) ? '' : implode(', ', array_unique($srcset));
}

/* ── Color maps ── */
$color_name_ar = [
    'red' => 'أحمر', 'blue' => 'أزرق', 'green' => 'أخضر', 'black' => 'أسود', 'white' => 'أبيض',
    'yellow' => 'أصفر', 'orange' => 'برتقالي', 'purple' => 'بنفسجي', 'pink' => 'وردي', 'gray' => 'رمادي',
    'grey' => 'رمادي', 'brown' => 'بني', 'beige' => 'بيج', 'gold' => 'ذهبي', 'silver' => 'فضي',
    'lightblue' => 'أزرق فاتح', 'light blue' => 'أزرق فاتح', 'violet' => 'بنفسجي',
    'light purple' => 'بنفسجي فاتح', 'lightpurple' => 'بنفسجي فاتح', 'salmon' => 'سلمون',
    'mixed' => 'ألوان مختلطة', 'ash' => 'رمادي فاتح', 'dark clay' => 'طين غامق', 'cognac' => 'كونياك',
    'coffee' => 'بني غامق', 'charcoal' => 'فحمي', 'fuchsia' => 'فوشيا', 'burgundy' => 'نبيتي',
    'midnight blue' => 'أزرق غامق', 'no color' => 'بدون لون'
];

$color_name_css = [
    'ألوان مختلطة' => '#bdbdbd', 'أزرق فاتح' => 'lightblue', 'بنفسجي فاتح' => 'plum',
    'سلمون' => 'salmon', 'رمادي فاتح' => '#b2beb5', 'بني غامق' => '#6f4e37',
    'كونياك' => '#9b4f41', 'فحمي' => '#36454f', 'فوشيا' => 'fuchsia',
    'نبيتي' => '#800020', 'أزرق غامق' => 'midnightblue', 'بدون لون' => '#f2f2f2'
];
