<?php
require_once __DIR__ . '/inc/next-customer-bridge.php';
next_customer_redirect('/buy-now', ['id' => $_GET['id'] ?? $_REQUEST['id'] ?? '']);

$page_meta_title = '';
$page_meta_description = '';
$page_preload_image = '';
$page_preload_srcset = '';
$page_preload_sizes = '(max-width: 768px) 94vw, 520px';

require_once('admin/inc/config.php');
require_once('admin/inc/functions.php');
require_once('inc/encryption.php');
require_once __DIR__ . '/inc/incomplete-orders.php';
require_once __DIR__ . '/inc/delivery_cache_functions.php';
require_once __DIR__ . '/inc/site-security.php';
require_once __DIR__ . '/inc/checkout-functions.php';
require_once __DIR__ . '/inc/checkout_async_tasks.php';

if (function_exists('ensure_product_delivery_company_column')) {
    ensure_product_delivery_company_column($pdo);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
$success_message = '';
$product_id = $product_name = $unit_price = null;
$product_delivery_mode = 'home_office';
$product_delivery_company_id = 0;

if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
    header('location: index.php');
    exit;
}

// فك تشفير معرف المنتج
$encrypted_id = $_REQUEST['id'];
$product_id = decrypt_product_id($encrypted_id);

if ($product_id === false) {
    // إذا فشل فك التشفير، حاول استخدام المعرف مباشرة (للتوافق مع الروابط القديمة)
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
        $product_delivery_mode = normalize_product_delivery_mode($row['p_delivery_mode'] ?? 'home_office');
        $product_delivery_company_id = (int)($row['p_delivery_company_id'] ?? 0);
    }
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// جلب المقاسات
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

// جلب الألوان
$colors = [];
$stmt_colors = $pdo->prepare("SELECT color_id FROM tbl_product_color WHERE p_id = ?");
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

// جلب صور الألوان
$color_photos = [];
$stmt_photos = $pdo->prepare("SELECT photo, color_id FROM tbl_product_photo WHERE p_id = ? AND color_id IS NOT NULL");
$stmt_photos->execute([$product_id]);
foreach ($stmt_photos->fetchAll(PDO::FETCH_ASSOC) as $row_photo) {
    $color_photos[$row_photo['color_id']] = $row_photo['photo'];
}

// جلب جميع الصور
$all_photos = [$p_featured_photo];
$stmt_all_photos = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? AND (photo IS NOT NULL AND photo != '') AND (is_additional IS NULL OR is_additional = 0)");
$stmt_all_photos->execute([$product_id]);
foreach ($stmt_all_photos->fetchAll(PDO::FETCH_ASSOC) as $row_photo) {
    if ($row_photo['photo'] !== $p_featured_photo) {
        $all_photos[] = $row_photo['photo'];
    }
}

// جلب الصور الإضافية فقط (is_additional=1)
$stmt_additional = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? AND is_additional = 1");
$stmt_additional->execute([$product_id]);
$additional_photos = $stmt_additional->fetchAll(PDO::FETCH_ASSOC);

// استخدم شركة التوصيل المختارة للمنتج أولاً، ثم fallback إلى شركة بها أسعار فعلية.
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

// ??????? ???????? ?? ???? ??????
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



if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// معالجة النموذج مع منع الإرسال المتكرر
if (isset($_POST['form1'])) {
    // التحقق من token النموذج
    if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        $error_message = "خطأ في البيانات المرسلة، يرجى إعادة المحاولة";
        error_log('تم رفض الطلب - token غير صحيح');
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
                $delivery_type = resolve_delivery_type_by_mode($_POST['delivery_type'] ?? '', $product_delivery_mode);
                $available_delivery_prices = get_available_delivery_prices_for_wilaya($shipping_data, $wilaya, $product_delivery_mode);
                if ($product_delivery_mode !== 'free' && !empty($wilaya)) {
                    $delivery_type = resolve_available_delivery_type_for_wilaya($shipping_data, $wilaya, $product_delivery_mode, $delivery_type);
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
                    error_log('Blocked order attempt before validation: ' . json_encode([
                        'action' => $security_check['action'],
                        'status' => $security_check['status'],
                        'phone' => $customer_phone_for_security,
                        'ip' => $security_check['context']['ip_address'] ?? '',
                        'device_id' => $security_check['context']['device_id'] ?? ''
                    ], JSON_UNESCAPED_UNICODE));
                }
                
                error_log('البيانات المستخرجة: ' . json_encode([
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'wilaya' => $wilaya,
                    'commune' => $commune,
                    'product_id' => $product_id,
                    'product_name' => $product_name
                ]));

                $shipping_fee = 0;
                if ($product_delivery_mode !== 'free' && $delivery_type !== '' && !empty($available_delivery_prices)) {
                    $shipping_fee = (float) ($available_delivery_prices[$delivery_type] ?? 0);
                }

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
                            $_POST['customer_phone'] = $customer_phone;
                            if ($error_message === '') {
                                $security_check = site_security_evaluate_order($pdo, [
                                    'customer_name' => $customer_name,
                                    'customer_phone' => $customer_phone,
                                    'wilaya' => $wilaya,
                                    'commune' => $commune,
                                    'address' => $_POST['address'] ?? '',
                                    'device_id' => $_POST['device_id'] ?? null
                                ]);
                                if ($security_check['action'] !== 'allow') {
                                    site_security_record_rejected_attempt($pdo, $security_check);
                                    $error_message = $security_check['message'];
                                    error_log('Blocked order attempt: ' . json_encode([
                                        'action' => $security_check['action'],
                                        'status' => $security_check['status'],
                                        'phone' => $security_check['context']['phone'] ?? $customer_phone,
                                        'ip' => $security_check['context']['ip_address'] ?? '',
                                        'device_id' => $security_check['context']['device_id'] ?? ''
                                    ], JSON_UNESCAPED_UNICODE));
                                }
                            }
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

                                if($error_message === '' && saveIncompleteOrder($pdo, $product_id, $product_name, $customer_name, $customer_phone, [
                                    'quantity' => $_POST['quantity'] ?? null,
                                    'unit_price' => $unit_price,
                                    'total_price' => $_POST['total_price'] ?? null,
                                    'selected_size' => $_POST['selected_size'] ?? '',
                                    'selected_color' => $selected_color_id,
                                    'delivery_type' => $_POST['delivery_type'] ?? '',
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
                                            'quantity' => $_POST['quantity'] ?? 1,
                                            'unit_price' => $unit_price,
                                            'total_price' => $_POST['total_price'] ?? '',
                                            'selected_size' => $_POST['selected_size'] ?? '',
                                            'selected_color' => $selected_color_name,
                                            'delivery_type' => $_POST['delivery_type'] ?? '',
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
                            
                            // Snapchat Pixel AddToCart
                            if (typeof trk !== "undefined") {
                                trk("track", "ADD_CART", {
                                    price: ' . floatval($unit_price) . ',
                                    currency: "DZD",
                                    content_type: "product",
                                    content_name: "' . addslashes($product_name) . '",
                                    number_items: 1
                                });
                            }
                            
                            // Google Analytics AddToCart
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
                    if ($error_message !== '') {
                        throw new Exception($error_message);
                    }

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
                        intval($_POST['quantity']),
                        $unit_price,
                        floatval($_POST['total_price']),
                        $_POST['customer_name'],
                        $_POST['customer_phone'],
                        $_POST['wilaya'],
                        $_POST['commune'],
                        $_POST['delivery_type'], // حفظ نوع التوصيل
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
                        'quantity' => (int) ($_POST['quantity'] ?? 0),
                        'total_price' => (float) ($_POST['total_price'] ?? 0),
                        'order_status' => 'Pending',
                        'status' => 'Pending',
                        'wilaya' => (string) ($_POST['wilaya'] ?? ''),
                        'commune' => (string) ($_POST['commune'] ?? ''),
                        'address' => (string) ($_POST['address'] ?? ''),
                        'delivery_type' => (string) ($_POST['delivery_type'] ?? ''),
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
                        'delivery_type' => $_POST['delivery_type'] ?? '',
                        'product_name' => $product_name,
                        'quantity' => $_POST['quantity'],
                        'unit_price' => $unit_price,
                        'total_price' => $_POST['total_price'],
                        'selected_size' => $_POST['selected_size'] ?? '',
                        'selected_color' => $selected_color_name
                    ];


                    $pdo->commit();

                    // Central Order Assignment
                    if (file_exists(__DIR__ . '/admin/inc/employee_functions.php')) {
                        require_once __DIR__ . '/admin/inc/employee_functions.php';
                        if (function_exists('assign_order_by_strategy')) {
                            assign_order_by_strategy($pdo, $created_order_id, 'buy_now');
                        }
                    }

                    checkout_dispatch_order_post_tasks([
                        'order_id' => $created_order_id,
                        'source' => 'buy_now',
                        'telegram_order_data' => $orderData,
                        'context' => $created_order_context
                    ]);
                    
                    // تخزين معلومات الطلب في الجلسة
                    $_SESSION['order_details'] = [
                        'product_name' => $product_name,
                        'quantity' => $_POST['quantity'],
                        'total_price' => $_POST['total_price'],
                        'customer_name' => $_POST['customer_name'],
                        'order_date' => date('Y-m-d H:i:s')
                    ];
                    $_SESSION['purchase_pixel_data'] = [
                        'value' => floatval($_POST['total_price']),
                        'currency' => 'DZD',
                        'content_type' => 'product',
                        'content_name' => $product_name,
                        'content_ids' => [(string) $product_id],
                        'quantity' => intval($_POST['quantity'])
                    ];
                    
                    // إضافة TikTok Pixel و Facebook Pixel Purchase Event وإعادة التوجيه
                    header('Location: payment-success.php');
                    exit;

                    echo '<script>
                        // إعادة التوجيه إلى صفحة النجاح بعد ثانيتين لضمان إرسال Pixel
                        setTimeout(function() {
                            window.location.href = "payment-success.php";
                        }, 2000);
                        
                        // إظهار رسالة نجاح للمستخدم
                        document.body.innerHTML = `
                            <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
                                <div style="background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px;">
                                    <h2 style="color: #155724; margin-bottom: 15px;">✅ تم إرسال طلبك بنجاح!</h2>
                                    <p style="margin-bottom: 10px;">سيتم توجيهك إلى صفحة التأكيد خلال لحظات...</p>
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
                    </script>';
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

// عرض رسالة النجاح إذا كانت موجودة في الجلسة
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // حذف الرسالة بعد عرضها
}

// Meta + preload for LCP image
if (!empty($product_name)) {
    $page_meta_title = $product_name . ' - BoomStore';
}
if (!empty($description)) {
    $meta_desc = trim(strip_tags($description));
    if (mb_strlen($meta_desc) > 160) {
        $meta_desc = mb_substr($meta_desc, 0, 160) . '...';
    }
    $page_meta_description = $meta_desc;
}

$hero_photo = $all_photos[0] ?? $p_featured_photo ?? '';
if ($hero_photo !== '') {
    if (is_external_image_url($hero_photo)) {
        $page_preload_image = $hero_photo;
        $page_preload_srcset = '';
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
                $page_preload_image = get_front_image_url($hero_photo);
            }
        }
    }
}

require_once('header.php');
?>
<link rel="stylesheet" href="assets/css/checkout.css">
<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="product-detail">
                    <div class="row">
                        <!-- عرض الصورة الرئيسية وصور مصغرة -->
                        <div class="col-md-5" id="product-image-container">
                            <div class="text-center mb-4">
                                <?php $main_photo = $all_photos[0] ?? ($p_featured_photo ?? ''); ?>
                                <img id="main-product-image" src="<?= htmlspecialchars(get_front_image_url($main_photo), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product_name) ?>" class="img-fluid">
                            </div>
                            <div class="d-flex justify-content-center gap-3">
                                <?php foreach ($all_photos as $idx => $photo): ?>
                                    <img src="<?= htmlspecialchars(get_front_image_url($photo), ENT_QUOTES, 'UTF-8') ?>" alt="thumb" class="thumb-img<?= $idx === 0 ? ' selected-thumb' : '' ?>" data-photo="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- نموذج الطلب -->
                        <div class="col-md-7">
                            <div class="card custom-buy-card shadow p-4">
                                <h2 class="product-title"><?= htmlspecialchars($product_name) ?></h2>
                                <div class="d-flex align-items-center mb-3">
                                   <div class="text-warning">★★★★★</div>
                                   <small class="text-muted me-2">(4.8)</small>
                                </div>
                                <p class="text-muted mb-4"><?= htmlspecialchars($description) ?></p>
                               
                                <div class="mb-4">
                                    <span class="product-price"><?= htmlspecialchars($unit_price) ?> دج</span>
                                    <?php if (!empty($old_price)): ?>
                                        <span class="old-price"><?= htmlspecialchars($old_price) ?> دج</span>
                                    <?php endif; ?>
                                    <span class="discount-badge">-20%</span>
                                </div>
                                
                                <!-- نص جذاب للطلب -->
                                <div class="order-now-text text-center mb-4">
                                    <h3 class="animate-text">اطلب الآن</h3>
                                    <p class="pulse-text">املأ النموذج التالي للحصول على منتجك</p>
                                </div>

                                <!-- رسائل التنبيه -->
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger" role="alert" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                                        <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success" role="alert" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                                        <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                                    </div>
                                <?php endif; ?>

                                <form action="" method="post" id="orderForm">
                                    <div class="form-row">
                                        <div class="form-group col-md-6 position-relative">
                                            <label for="customer_name">الاسم الكامل *</label>
                                            <input type="text" class="form-control" id="customer_name" name="customer_name" required value="<?= isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : '' ?>">
                                            <span class="input-icon"><i class="fa fa-user"></i></span>
                                        </div>
                                        <div class="form-group col-md-6 position-relative">
                                            <label for="customer_phone">رقم الهاتف *</label>
                                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required 
                                                   placeholder="05617355566"  
                                                   pattern="[0-9]{9,10}" 
                                                   title="يرجى إدخال رقم هاتف جزائري صحيح (9-10 أرقام)"
                                                   value="<?= isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : '' ?>">
                                            <span class="input-icon"><i class="fa fa-phone"></i></span>
                                            <div id="phone-error" class="text-danger mt-1" style="display: none; font-size: 0.9rem;"></div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="wilaya">الولاية *</label>
                                            <select class="form-control" id="wilaya" name="wilaya" required>
                                                <option value="">اختر الولاية</option>
                                                <?php foreach (array_keys($shipping_data) as $wilaya): ?>
                                                    <option value="<?= htmlspecialchars($wilaya) ?>"><?= htmlspecialchars($wilaya) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
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
                                    <!-- اللون -->
                                    <?php if (!empty($color_names)): ?>
                                        <div class="form-group">
                                            <label>اختر اللون</label>
                                            <div id="colorButtons" class="d-flex gap-2 flex-wrap">
                                                <?php foreach ($color_names as $color_id => $color_name): ?>
                                                    <button type="button" class="color-btn<?php if ($color_id === array_key_first($color_names)) echo ' selected'; ?>"
                                                        style="width:40px;height:40px;border-radius:50%;border:2px solid #eee;outline:none;cursor:pointer;background-color:<?= htmlspecialchars($color_name) ?>;"
                                                        data-color-id="<?= $color_id ?>"
                                                        data-photo="<?= htmlspecialchars($color_photos[$color_id] ?? $p_featured_photo, ENT_QUOTES, 'UTF-8') ?>"
                                                        title="<?= htmlspecialchars($color_name) ?>">
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="selected_color" id="selectedColorInput" value="<?= array_key_first($color_names) ?>">
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="selected_color" value="">
                                    <?php endif; ?>

                                    <!-- المقاس -->
                                    <?php if (!empty($size_names)): ?>
                                        <div class="form-group">
                                            <label>اختر المقاس</label>
                                            <div id="sizeButtons" class="d-flex gap-2 flex-wrap">
                                                <?php foreach ($size_names as $size_id => $size_name): ?>
                                                    <button type="button" class="size-btn<?php if ($size_id === array_key_first($size_names)) echo ' selected'; ?>" 
                                                        data-size-id="<?= $size_id ?>">
                                                        <?= htmlspecialchars($size_name) ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="selected_size" id="selectedSizeInput" value="<?= array_key_first($size_names) ?>">
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="selected_size" value="">
                                    <?php endif; ?>

                                    <!-- نوع التوصيل -->
                                    <div class="form-group">
                                        <label>نوع التوصيل *</label>
                                        <div id="deliveryTypeBtns" class="d-flex gap-2 mb-2">
                                        <?php if ($product_delivery_mode === 'free'): ?>
                                            <button type="button" class="delivery-btn selected" data-kind="free" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('free', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="delivery-type-label">توصيل مجاني</span>
                                                    <span class="delivery-price d-block">0 دج</span>
                                                </button>
                                            <?php elseif ($product_delivery_mode === 'home_only'): ?>
                                                <button type="button" class="delivery-btn selected" data-kind="home" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="delivery-type-label">توصيل للمنزل</span>
                                                    <span class="delivery-price d-block" id="homePriceBtn">0 دج</span>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="delivery-btn selected" data-kind="home" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="delivery-type-label">توصيل للمنزل</span>
                                                    <span class="delivery-price d-block" id="homePriceBtn">0 دج</span>
                                                </button>
                                                <button type="button" class="delivery-btn" data-kind="office" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('office', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="delivery-type-label">توصيل للمكتب</span>
                                                    <span class="delivery-price d-block" id="officePriceBtn">0 دج</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="delivery-options-note" id="deliveryOptionsNote" style="display: none;"></div>
                                        <input type="hidden" name="delivery_type" id="deliveryTypeInput" value="<?= htmlspecialchars(resolve_delivery_type_by_mode($_POST['delivery_type'] ?? '', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>

                                    <!-- الكمية -->
                                    <div class="form-group full-width">
                                        <label for="quantity">الكمية *</label>
                                        <div class="input-group quantity-group">
                                            <button type="button" class="quantity-btn" id="decreaseQuantity">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                            <input type="number" class="form-control text-center" id="quantity" name="quantity" value="1" min="1" required>
                                            <button type="button" class="quantity-btn" id="increaseQuantity">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- زر الشراء -->
                                <div class="d-grid mt-4">
                                    <input type="hidden" name="total_price" id="total_price_hidden" value="">
                                    <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
                                    <button type="submit" name="form1" class="buy-now-btn">
                                        <span class="total-amount" id="total_price_btn">0 دج</span>
                                        اطلب الآن
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($more_description)): ?>
                            <div class="product-more-description mt-4 mb-4 p-3" style="box-shadow:0 2px 8px rgba(124,58,237,0.07);margin-top:32px !important;">
                                <h4 style="font-size:1.3rem;color:#dc3545;font-weight:bold;margin-bottom:10px;letter-spacing:1px;">تفاصيل إضافية عن المنتج</h4>
                                <div style="font-size:1.08rem;line-height:1.8;color:#444;font-weight:bold">
                                    <?= nl2br(htmlspecialchars($more_description)) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($additional_photos)): ?>
                            <div class="product-additional-photos mt-4 mb-4">
                                <h4 style="font-size:1.05rem;color:#dc3545;font-weight:bold;margin-bottom:10px;letter-spacing:1px;">صور إضافية للمنتج</h4>
                                <div style="display:flex;flex-wrap:wrap;gap:18px;justify-content:flex-start;">
                                    <?php foreach ($additional_photos as $photo): ?>
                                        <div style="background:#fafafa;padding:6px;border-radius:10px;box-shadow:0 2px 8px rgba(220,53,69,0.07);opacity:0.97;transition:opacity 0.3s;">
                                            <img src="<?= htmlspecialchars(get_front_image_url($photo['photo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="صورة إضافية" style="width:260px;height:260px;object-fit:cover;border-radius:12px;display:block;filter:grayscale(0.05);">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- تحميل المكتبات بالترتيب الصحيح -->
<!-- أولاً jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<!-- jQuery Plugins -->
<script src="assets/js/jquery.magnific-popup.min.js"></script>
<script src="assets/js/owl.carousel.min.js"></script>
<script src="assets/js/rating.js"></script>
<script src="assets/js/bootstrap-touch-slider.js"></script>
<script src="assets/js/select2.full.min.js"></script>
<!-- Custom Scripts -->
<script src="assets/js/site-security-device.js"></script>
<script src="assets/js/custom.js"></script>
<link rel="stylesheet" href="assets/css/checkout-polish.css?v=<?= filemtime(__DIR__ . '/assets/css/checkout-polish.css') ?>">
<script>
const checkoutConfig = {
  deliveryCacheData: <?= json_encode($delivery_cache_data) ?>,
  shippingFees: <?= json_encode($shipping_data) ?>,
  productDeliveryMode: <?= json_encode($product_delivery_mode) ?>,
  basePrice: <?= json_encode(floatval($unit_price)) ?>,
  checkoutProductId: <?= json_encode($product_id) ?>,
  checkoutProductName: <?= json_encode($product_name) ?>
};
window.deliveryCacheData = checkoutConfig.deliveryCacheData;
window.shippingFees = checkoutConfig.shippingFees;
window.productDeliveryMode = checkoutConfig.productDeliveryMode;
window.basePrice = checkoutConfig.basePrice;
window.checkoutProductId = checkoutConfig.checkoutProductId;
window.checkoutProductName = checkoutConfig.checkoutProductName;
</script>
<script src="assets/js/checkout.js?v=<?= filemtime(__DIR__ . '/assets/js/checkout.js') ?>"></script>



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
