<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug helper (server): show fatal errors on screen when requested.
// IMPORTANT: this must run before header.php include.
// Use: product-edit.php?id=143&debug=1
$debug_enabled = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug_enabled) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);

    register_shutdown_function(function () {
        $err = error_get_last();
        if (!$err) {
            return;
        }
        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatal_types, true)) {
            return;
        }
        if (headers_sent() === false) {
            http_response_code(500);
        }
        echo '<pre dir="rtl" style="text-align:right;white-space:pre-wrap;margin:16px;background:#fff;border:1px solid #fecaca;border-radius:12px;padding:14px;color:#111827;">'
            . "خطأ قاتل (Fatal Error)\n"
            . "الرسالة: " . ($err['message'] ?? '') . "\n"
            . "الملف: " . ($err['file'] ?? '') . "\n"
            . "السطر: " . ($err['line'] ?? 0) . "\n"
            . '</pre>';
    });
}

require_once('header.php');

// حماية للاستضافة: إذا كان inc/functions.php قديم/غير مكتمل سيحدث Fatal Error وتظهر صفحة فارغة.
// هنا نعرض رسالة واضحة بدل الفراغ لتعرف ما الذي ينقص على السيرفر.
$required_functions = [
    'normalize_image_value',
    'is_external_image_url',
    'store_external_image_url',
    'delete_local_image_file',
    'is_valid_image_url',
    'store_uploaded_image_file',
    'store_image_input',
    'get_admin_image_url',
    'ensure_product_offer_table',
    'ensure_product_delivery_company_column',
    'resolve_product_delivery_company_id',
    'get_delivery_company_options'
];
$missing_functions = [];
foreach ($required_functions as $fn) {
    if (!function_exists($fn)) {
        $missing_functions[] = $fn;
    }
}
if (!empty($missing_functions)) {
    ?>
    <section class="content" style="direction: rtl; text-align: right;">
        <div class="callout callout-danger" style="border-radius: 10px;">
            <h4 style="margin:0 0 8px; font-weight:800;">تعطل صفحة تعديل المنتج على الاستضافة</h4>
            <div style="color:#111827; line-height:1.9;">
                السبب الأغلب: ملف <code>admin/inc/functions.php</code> على السيرفر قديم أو ناقص مقارنة باللوكال، فدوال مطلوبة غير موجودة مما يسبب صفحة فارغة.
                <br>
                <strong>الدوال الناقصة:</strong>
                <code><?php echo htmlspecialchars(implode(', ', $missing_functions), ENT_QUOTES, 'UTF-8'); ?></code>
                <br>
                <strong>الحل:</strong> ارفع/استبدل ملف <code>admin/inc/functions.php</code> من نسختك المحلية إلى الاستضافة (نفس الملف بالكامل)، ثم أعد تحميل الصفحة.
            </div>
        </div>
    </section>
    <?php
    require_once('footer.php');
    exit;
}

if (!function_exists('normalize_product_delivery_mode')) {
    function normalize_product_delivery_mode($value)
    { global $dbRepo;
    global $dbRepo;

        $value = strtolower(trim((string) $value));
        if (in_array($value, ['free', 'home_only', 'home_office'], true)) {
            return $value;
        }
        return 'home_office';
    }
}

if (!function_exists('ensure_product_delivery_company_column')) {
    function ensure_product_delivery_company_column(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        try {
            $dbRepo->executeCommand("ALTER TABLE tbl_product ADD COLUMN p_delivery_company_id INT NULL DEFAULT NULL");
        } catch (Exception $e) {
            // Ignore if column already exists or DB user has limited alter permissions.
        }
    }
}

if (!function_exists('resolve_product_delivery_company_id')) {
    function resolve_product_delivery_company_id(PDO $pdo, $preferredId = 0)
    { global $dbRepo;
    global $dbRepo;

        $preferredId = (int) $preferredId;
        if ($preferredId > 0) {
            return $preferredId;
        }
        return 0;
    }
}

if (!function_exists('get_delivery_company_options')) {
    function get_delivery_company_options(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        try {
            $statement = $dbRepo->query("SELECT id, name, active FROM tbl_delivery_company ORDER BY active DESC, name ASC, id ASC");
            return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('ensure_product_offer_table')) {
    function ensure_product_offer_table(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        try {
            $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_product_offer (
                id INT AUTO_INCREMENT PRIMARY KEY,
                p_id INT NOT NULL,
                offer_type VARCHAR(20) NOT NULL DEFAULT 'quantity',
                offer_qty INT NOT NULL DEFAULT 1,
                offer_unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                offer_description TEXT NULL,
                offer_photo VARCHAR(255) NULL,
                is_most_popular TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
            // Keep page functional even if this table cannot be created automatically.
        }
    }
}

if (!function_exists('product_edit_ensure_column')) {
    function product_edit_ensure_column(PDO $pdo, $table, $column, $definition, array &$schema_errors)
    { global $dbRepo;
        try {
            $check = $dbRepo->query("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'");
            if ($check->rowCount() === 0) {
                $dbRepo->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (PDOException $e) {
            $schema_errors[] = $table . '.' . $column . ': ' . $e->getMessage();
            error_log('Failed to ensure ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
    }
}

if (!function_exists('product_edit_ensure_database_schema')) {
    function product_edit_ensure_database_schema(PDO $pdo)
    { global $dbRepo;

        $schema_errors = [];

        $product_columns = [
            'purchase_price' => "DECIMAL(10,2) DEFAULT 0.00",
            'more_description' => "TEXT NULL",
            'product_template' => "VARCHAR(50) NOT NULL DEFAULT 'buy-now.php'",
            'landing_photo_1' => "VARCHAR(255) DEFAULT ''",
            'landing_photo_2' => "VARCHAR(255) DEFAULT ''",
            'landing_photo_3' => "VARCHAR(255) DEFAULT ''",
            'p_announcement' => "TEXT NULL",
            'p_delivery_mode' => "VARCHAR(20) NOT NULL DEFAULT 'home_office'",
            'p_delivery_company_id' => "INT NULL DEFAULT NULL"
        ];
        foreach ($product_columns as $column => $definition) {
            product_edit_ensure_column($pdo, 'tbl_product', $column, $definition, $schema_errors);
        }

        try {
            $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_pixel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pixel_name VARCHAR(255) NOT NULL,
                pixel_network VARCHAR(100) NOT NULL,
                pixel_id VARCHAR(255) NOT NULL,
                pixel_script TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {
            $schema_errors[] = 'tbl_pixel: ' . $e->getMessage();
            error_log('Failed to ensure tbl_pixel table: ' . $e->getMessage());
        }

        try {
            $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_product_pixel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                pixel_id INT NOT NULL,
                UNIQUE KEY uniq_product_pixel (product_id, pixel_id),
                KEY idx_product_pixel_product (product_id),
                KEY idx_product_pixel_pixel (pixel_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (PDOException $e) {
            $schema_errors[] = 'tbl_product_pixel: ' . $e->getMessage();
            error_log('Failed to ensure tbl_product_pixel table: ' . $e->getMessage());
        }

        $offer_columns = [
            'offer_type' => "VARCHAR(20) NOT NULL DEFAULT 'quantity'",
            'offer_description' => "TEXT NULL",
            'offer_photo' => "VARCHAR(255) NULL DEFAULT NULL",
            'is_most_popular' => "TINYINT(1) NOT NULL DEFAULT 0",
            'is_active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'sort_order' => "INT NOT NULL DEFAULT 0"
        ];
        foreach ($offer_columns as $column => $definition) {
            product_edit_ensure_column($pdo, 'tbl_product_offer', $column, $definition, $schema_errors);
        }

        product_edit_ensure_column($pdo, 'tbl_product_photo', 'color_id', "INT NULL DEFAULT NULL", $schema_errors);
        product_edit_ensure_column($pdo, 'tbl_product_photo', 'is_additional', "TINYINT(1) DEFAULT 0", $schema_errors);

        return $schema_errors;
    }
}

try {
    $column_check = $dbRepo->query("SHOW COLUMNS FROM tbl_product LIKE 'p_announcement'");
    if ($column_check->rowCount() === 0) {
        $dbRepo->executeCommand("ALTER TABLE tbl_product ADD COLUMN p_announcement TEXT NULL");
    }
} catch (PDOException $e) {
    error_log('Failed to ensure p_announcement column: ' . $e->getMessage());
}

try {
    $column_check = $dbRepo->query("SHOW COLUMNS FROM tbl_product LIKE 'p_delivery_mode'");
    if ($column_check->rowCount() === 0) {
        $dbRepo->executeCommand("ALTER TABLE tbl_product ADD COLUMN p_delivery_mode VARCHAR(20) NOT NULL DEFAULT 'home_office'");
    }
} catch (PDOException $e) {
    error_log('Failed to ensure p_delivery_mode column: ' . $e->getMessage());
}

ensure_product_delivery_company_column($pdo);

ensure_product_offer_table($pdo);
$schema_errors = product_edit_ensure_database_schema($pdo);
if (!empty($schema_errors)) {
    ?>
    <section class="content" style="direction: rtl; text-align: right;">
        <div class="callout callout-danger" style="border-radius: 10px;">
            <h4 style="margin:0 0 8px; font-weight:800;">تعذر تجهيز قاعدة البيانات لصفحة تعديل المنتج</h4>
            <div style="color:#111827; line-height:1.9;">
                الصفحة تحتاج أعمدة/جداول ناقصة في قاعدة البيانات، ولم يستطع المستخدم الحالي تعديلها تلقائيا.
                افتح صلاحيات ALTER/CREATE لمستخدم قاعدة البيانات أو نفذ رسائل الخطأ التالية من phpMyAdmin ثم أعد تحميل الصفحة:
                <pre style="direction:ltr;text-align:left;white-space:pre-wrap;margin-top:10px;"><?php echo htmlspecialchars(implode("\n", $schema_errors), ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
        </div>
    </section>
    <?php
    require_once('footer.php');
    exit;
}
$special_offer_slots = [1, 2, 3];

$id = (int)($_REQUEST['id'] ?? ($_REQUEST['pid'] ?? 0));
if ($id <= 0) {
    header('Location: logout.php');
    exit;
}

$statement = $dbRepo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
$statement->execute([$id]);
$p_data = $statement->fetch(PDO::FETCH_ASSOC);
if (!$p_data) {
    header('Location: logout.php');
    exit;
}

$error_message = '';
$success_message = '';

$normalize_url = function ($url) use (&$error_message) {
    $url = normalize_image_value($url);
    if ($url === '') {
        return '';
    }
    if (is_external_image_url($url)) {
        return store_external_image_url($url, $error_message);
    }
    return $url;
};

if (isset($_POST['remove_featured_photo'])) {
    $current_featured = trim((string)($p_data['p_featured_photo'] ?? ''));
    $current_landing_1 = trim((string)($p_data['landing_photo_1'] ?? ''));
    $current_template = (string)($p_data['product_template'] ?? '');

    $new_featured = $current_featured;
    $new_landing_1 = $current_landing_1;

    if ($new_featured !== '') {
        delete_local_image_file($new_featured, '../assets/uploads');
        $new_featured = '';
    } elseif (in_array($current_template, ['landing_page.php', 'landing_page_2.php'], true) && $new_landing_1 !== '') {
        // For landing template, main preview can come from landing_photo_1 when featured is empty.
        delete_local_image_file($new_landing_1, '../assets/uploads');
        $new_landing_1 = '';
        if ($current_featured === $current_landing_1) {
            $new_featured = '';
        }
    }

    if ($new_featured !== $current_featured || $new_landing_1 !== $current_landing_1) {
        $dbRepo->prepare("UPDATE tbl_product SET p_featured_photo=?, landing_photo_1=? WHERE p_id=?")
            ->execute([$new_featured, $new_landing_1, $id]);
    }

    header('Location: product-edit.php?id=' . $id . '&success=featured_deleted');
    exit;
}

$delivery_companies = get_delivery_company_options($pdo);
$default_product_delivery_company_id = resolve_product_delivery_company_id($pdo, (int)($p_data['p_delivery_company_id'] ?? 0));
$existing_special_offer_photos_db = [];
$stmt_existing_special_offer_photos = $dbRepo->prepare("SELECT offer_photo FROM tbl_product_offer WHERE p_id = ? AND offer_type = 'special'");
$stmt_existing_special_offer_photos->execute([$id]);
foreach ($stmt_existing_special_offer_photos->fetchAll(PDO::FETCH_ASSOC) as $special_offer_row) {
    $photo_value = trim((string)($special_offer_row['offer_photo'] ?? ''));
    if ($photo_value !== '' && !in_array($photo_value, $existing_special_offer_photos_db, true)) {
        $existing_special_offer_photos_db[] = $photo_value;
    }
}

if (isset($_POST['form1'])) {
    $valid = 1;
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (empty($_POST['p_name'])) {
        $valid = 0;
        $error_message .= 'اسم المنتج مطلوب.<br>';
    }
    if (empty($_POST['p_current_price'])) {
        $valid = 0;
        $error_message .= 'السعر الحالي مطلوب.<br>';
    }
    if (empty($_POST['p_qty'])) {
        $valid = 0;
        $error_message .= 'الكمية مطلوبة.<br>';
    }
    $product_ecat_id = (int)($_POST['ecat_id'] ?? ($p_data['ecat_id'] ?? 0));
    $product_template = $_POST['product_template'] ?? 'buy-now.php';
    $is_landing_template = in_array($product_template, ['landing_page.php', 'landing_page_2.php'], true);
    $product_delivery_mode = normalize_product_delivery_mode($_POST['p_delivery_mode'] ?? ($p_data['p_delivery_mode'] ?? 'home_office'));
    $product_delivery_company_id = resolve_product_delivery_company_id(
        $pdo,
        (int)($_POST['p_delivery_company_id'] ?? $default_product_delivery_company_id)
    );

    $featured_url = $normalize_url($_POST['p_featured_photo_url'] ?? '');
    $landing_url_1 = $normalize_url($_POST['landing_photo_1_url'] ?? '');
    $landing_url_2 = $normalize_url($_POST['landing_photo_2_url'] ?? '');
    $landing_url_3 = $normalize_url($_POST['landing_photo_3_url'] ?? '');
    $offer_inputs = [
        1 => $_POST['offer_price_1'] ?? '',
        2 => $_POST['offer_price_2'] ?? '',
        3 => $_POST['offer_price_3'] ?? ''
    ];
    $most_popular_key = trim((string)($_POST['most_popular_offer'] ?? ''));
    $has_quantity_offer = false;
    foreach ($offer_inputs as $price_raw) {
        if (trim((string)$price_raw) !== '') {
            $has_quantity_offer = true;
            break;
        }
    }

    $special_offer_inputs = [];
    $has_special_offer_input = false;
    foreach ($special_offer_slots as $slot) {
        $price_raw = trim((string)($_POST['special_offer_price_' . $slot] ?? ''));
        $description = trim((string)($_POST['special_offer_description_' . $slot] ?? ''));
        $photo_url = $normalize_url($_POST['special_offer_photo_url_' . $slot] ?? '');
        $current_photo = trim((string)($_POST['current_special_offer_photo_' . $slot] ?? ''));
        $has_input = (
            $price_raw !== ''
            || $description !== ''
            || $photo_url !== ''
            || !empty($_FILES['special_offer_photo_' . $slot]['name'])
        );
        $special_offer_inputs[$slot] = [
            'price_raw' => $price_raw,
            'description' => $description,
            'photo_url' => $photo_url,
            'current_photo' => $current_photo,
            'has_input' => $has_input,
            'most_popular' => ($most_popular_key === ('special:' . $slot))
        ];
        if ($has_input) {
            $has_special_offer_input = true;
        }
    }

    // Landing template keeps the effective main image in landing_photo_1.
    // If admin fills featured URL only, mirror it to landing_photo_1 so save works as expected.
    if ($is_landing_template && $featured_url !== '' && $landing_url_1 === '') {
        $landing_url_1 = $featured_url;
    }

    $image_urls_to_validate = [$featured_url, $landing_url_1, $landing_url_2, $landing_url_3];
    foreach ($special_offer_inputs as $special_offer_input) {
        $image_urls_to_validate[] = $special_offer_input['photo_url'];
    }
    foreach ($image_urls_to_validate as $u) {
        if ($u !== '' && !is_valid_image_url($u) && !is_external_image_url($u)) {
            $valid = 0;
            $error_message .= 'يوجد رابط صورة غير صالح.<br>';
        }
    }

    if ($has_quantity_offer && $has_special_offer_input) {
        $valid = 0;
        $error_message .= 'لا يمكن الجمع بين عروض الكمية والعروض الخاصة لنفس المنتج.<br>';
    }

    if ($has_special_offer_input) {
        foreach ($special_offer_slots as $slot) {
            $special_offer_input = $special_offer_inputs[$slot];
            if (!$special_offer_input['has_input']) {
                continue;
            }
            $special_offer_price = floatval(str_replace(',', '.', $special_offer_input['price_raw']));
            if ($special_offer_input['price_raw'] === '' || $special_offer_price <= 0) {
                $valid = 0;
                $error_message .= 'سعر العرض ' . $slot . ' مطلوب ويجب أن يكون أكبر من صفر.<br>';
            }
            if ($special_offer_input['description'] === '') {
                $valid = 0;
                $error_message .= 'وصف العرض ' . $slot . ' مطلوب.<br>';
            }
            if ($special_offer_input['photo_url'] === '' && empty($_FILES['special_offer_photo_' . $slot]['name']) && $special_offer_input['current_photo'] === '') {
                $valid = 0;
                $error_message .= 'صورة العرض ' . $slot . ' مطلوبة.<br>';
            }
        }
    }

    $current_featured = trim((string)($_POST['current_photo'] ?? ''));
    $current_l1 = trim((string)($_POST['current_landing_photo_1'] ?? ''));
    $current_l2 = trim((string)($_POST['current_landing_photo_2'] ?? ''));
    $current_l3 = trim((string)($_POST['current_landing_photo_3'] ?? ''));

    $final_featured = $current_featured;
    $landing_1 = $current_l1;
    $landing_2 = $current_l2;
    $landing_3 = $current_l3;
    $special_offers_to_save = [];

    if ($valid === 1) {
        if (!$is_landing_template) {
            if ($featured_url !== '') {
                if ($current_featured !== '' && $current_featured !== $featured_url) {
                    delete_local_image_file($current_featured, '../assets/uploads');
                }
                $final_featured = $featured_url;
            } elseif (!empty($_FILES['p_featured_photo']['name'])) {
                $name = (string)$_FILES['p_featured_photo']['name'];
                $tmp = (string)$_FILES['p_featured_photo']['tmp_name'];
                $err = (int)($_FILES['p_featured_photo']['error'] ?? UPLOAD_ERR_NO_FILE);
                $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true) || $err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                    $valid = 0;
                    $error_message .= 'فشل رفع الصورة الرئيسية.<br>';
                } else {
                    $target_base = 'product-featured-' . $id . '-' . time();
                    list($upload_ok, $stored_featured) = store_uploaded_image_file($tmp, $name, $target_base, '../assets/uploads', $error_message, $allowed_ext);
                    if (!$upload_ok || $stored_featured === '') {
                        $valid = 0;
                        $error_message .= 'فشل حفظ الصورة الرئيسية.<br>';
                    } else {
                        delete_local_image_file($current_featured, '../assets/uploads');
                        $final_featured = $stored_featured;
                    }
                }
            }
        }

        $landing_fields = [
            ['field' => 'landing_photo_1', 'current' => $current_l1, 'url' => $landing_url_1, 'prefix' => 'landing-1-' . $id],
            ['field' => 'landing_photo_2', 'current' => $current_l2, 'url' => $landing_url_2, 'prefix' => 'landing-2-' . $id],
            ['field' => 'landing_photo_3', 'current' => $current_l3, 'url' => $landing_url_3, 'prefix' => 'landing-3-' . $id]
        ];
        $landing_targets = [&$landing_1, &$landing_2, &$landing_3];

        if ($is_landing_template) {
            foreach ($landing_fields as $i => $cfg) {
                $value = $cfg['current'];
                if ($cfg['url'] !== '') {
                    if ($cfg['current'] !== '' && $cfg['current'] !== $cfg['url']) {
                        delete_local_image_file($cfg['current'], '../assets/uploads');
                    }
                    $value = $cfg['url'];
                } elseif (!empty($_FILES[$cfg['field']]['name'])) {
                    $name = (string)$_FILES[$cfg['field']]['name'];
                    $tmp = (string)$_FILES[$cfg['field']]['tmp_name'];
                    $err = (int)($_FILES[$cfg['field']]['error'] ?? UPLOAD_ERR_NO_FILE);
                    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed_ext, true) && $err === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                        $target_base = $cfg['prefix'] . '-' . time();
                        list($upload_ok, $stored_landing) = store_uploaded_image_file($tmp, $name, $target_base, '../assets/uploads', $error_message, $allowed_ext);
                        if ($upload_ok && $stored_landing !== '') {
                            delete_local_image_file($cfg['current'], '../assets/uploads');
                            $value = $stored_landing;
                        }
                    }
                }
                $landing_targets[$i] = $value;
            }
            if ($landing_1 !== '') {
                $final_featured = $landing_1;
            }
        }

        if ($has_special_offer_input) {
            foreach ($special_offer_slots as $slot) {
                $special_offer_input = $special_offer_inputs[$slot];
                if (!$special_offer_input['has_input']) {
                    continue;
                }

                $special_offer_photo = $special_offer_input['current_photo'];
                if ($special_offer_input['photo_url'] !== '') {
                    $special_offer_photo = $special_offer_input['photo_url'];
                } elseif (!empty($_FILES['special_offer_photo_' . $slot]['name'])) {
                    $name = (string)$_FILES['special_offer_photo_' . $slot]['name'];
                    $tmp = (string)$_FILES['special_offer_photo_' . $slot]['tmp_name'];
                    $err = (int)($_FILES['special_offer_photo_' . $slot]['error'] ?? UPLOAD_ERR_NO_FILE);
                    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_ext, true) || $err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                        $valid = 0;
                        $error_message .= 'فشل رفع صورة العرض ' . $slot . '.<br>';
                        continue;
                    }

                    $target_base = 'offer-special-' . $slot . '-' . $id . '-' . time();
                    list($upload_ok, $stored_special_offer_photo) = store_uploaded_image_file($tmp, $name, $target_base, '../assets/uploads', $error_message, $allowed_ext);
                    if (!$upload_ok || $stored_special_offer_photo === '') {
                        $valid = 0;
                        $error_message .= 'فشل حفظ صورة العرض ' . $slot . '.<br>';
                        continue;
                    }
                    $special_offer_photo = $stored_special_offer_photo;
                }

                $special_offers_to_save[$slot] = [
                    'price' => floatval(str_replace(',', '.', $special_offer_input['price_raw'])),
                    'description' => $special_offer_input['description'],
                    'photo' => $special_offer_photo
                ];
            }
        }
    }

    if ($valid === 1) {
        $dbRepo->prepare("UPDATE tbl_product SET p_name=?, p_old_price=?, p_current_price=?, purchase_price=?, p_qty=?, p_featured_photo=?, p_description=?, more_description=?, p_announcement=?, p_is_featured=?, p_is_active=?, ecat_id=?, product_template=?, p_delivery_mode=?, p_delivery_company_id=?, landing_photo_1=?, landing_photo_2=?, landing_photo_3=? WHERE p_id=?")
            ->execute([
                $_POST['p_name'],
                $_POST['p_old_price'] ?? '',
                $_POST['p_current_price'],
                $_POST['purchase_price'] ?? 0.00,
                $_POST['p_qty'],
                $final_featured,
                $_POST['p_description'] ?? '',
                $_POST['more_description'] ?? '',
                $_POST['p_announcement'] ?? '',
                $_POST['p_is_featured'] ?? 0,
                $_POST['p_is_active'] ?? 1,
                $product_ecat_id,
                $product_template,
                $product_delivery_mode,
                $product_delivery_company_id > 0 ? $product_delivery_company_id : null,
                $landing_1,
                $landing_2,
                $landing_3,
                $id
            ]);

        $retained_special_offer_photos = [];
        foreach ($special_offers_to_save as $special_offer_item) {
            $photo_value = trim((string)($special_offer_item['photo'] ?? ''));
            if ($photo_value !== '' && !in_array($photo_value, $retained_special_offer_photos, true)) {
                $retained_special_offer_photos[] = $photo_value;
            }
        }
        foreach ($existing_special_offer_photos_db as $old_special_offer_photo) {
            if (!in_array($old_special_offer_photo, $retained_special_offer_photos, true)) {
                delete_local_image_file($old_special_offer_photo, '../assets/uploads');
            }
        }

        $dbRepo->prepare("DELETE FROM tbl_product_offer WHERE p_id=?")->execute([$id]);
        if ($has_quantity_offer) {
            foreach ([1, 2, 3] as $q) {
                $price = trim((string)($_POST['offer_price_' . $q] ?? ''));
                if ($price === '') {
                    continue;
                }
                $price = floatval(str_replace(',', '.', $price));
                if ($price <= 0) {
                    continue;
                }
                $is_most_popular = ($most_popular_key === ('quantity:' . (int)$q)) ? 1 : 0;
                $dbRepo->prepare("INSERT INTO tbl_product_offer (p_id, offer_qty, offer_unit_price, offer_type, is_most_popular, is_active, sort_order) VALUES (?, ?, ?, 'quantity', ?, 1, ?)")
                    ->execute([$id, $q, $price, $is_most_popular, $q]);
            }
        } elseif ($has_special_offer_input) {
            foreach ($special_offers_to_save as $slot => $special_offer_item) {
                $is_most_popular = ($most_popular_key === ('special:' . (int)$slot)) ? 1 : 0;
                $dbRepo->prepare("INSERT INTO tbl_product_offer (p_id, offer_qty, offer_unit_price, offer_type, offer_description, offer_photo, is_most_popular, is_active, sort_order) VALUES (?, 1, ?, 'special', ?, ?, ?, 1, ?)")
                    ->execute([$id, $special_offer_item['price'], $special_offer_item['description'], $special_offer_item['photo'], $is_most_popular, (int)$slot]);
            }
        }

        $dbRepo->prepare("DELETE FROM tbl_product_size WHERE p_id=?")->execute([$id]);
        $dbRepo->prepare("DELETE FROM tbl_product_pixel WHERE product_id=?")->execute([$id]);
        foreach ((array)($_POST['pixel'] ?? []) as $pixel_id_value) {
            $dbRepo->prepare("INSERT INTO tbl_product_pixel (pixel_id, product_id) VALUES (?, ?)")->execute([(int)$pixel_id_value, $id]);
        }
        foreach ((array)($_POST['size'] ?? []) as $size_id_value) {
            $dbRepo->prepare("INSERT INTO tbl_product_size (size_id, p_id) VALUES (?, ?)")->execute([(int)$size_id_value, $id]);
        }

        $old_colors = [];
        $stmt_old_colors = $dbRepo->prepare("SELECT color_id FROM tbl_product_color WHERE p_id=?");
        $stmt_old_colors->execute([$id]);
        foreach ($stmt_old_colors->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $old_colors[] = (int)$row['color_id'];
        }

        $new_colors = array_map('intval', (array)($_POST['color'] ?? []));
        $new_colors = array_values(array_unique(array_filter($new_colors, static function ($v) { return $v > 0; })));

        $removed_colors = array_diff($old_colors, $new_colors);
        if (!empty($removed_colors)) {
            $stmt_color_photo = $dbRepo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=? AND color_id=?");
            $stmt_color_photo_delete = $dbRepo->prepare("DELETE FROM tbl_product_photo WHERE p_id=? AND color_id=?");
            foreach ($removed_colors as $removed_color) {
                $stmt_color_photo->execute([$id, (int)$removed_color]);
                foreach ($stmt_color_photo->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    delete_local_image_file($row['photo'] ?? '', '../assets/uploads');
                }
                $stmt_color_photo_delete->execute([$id, (int)$removed_color]);
            }
        }

        $dbRepo->prepare("DELETE FROM tbl_product_color WHERE p_id=?")->execute([$id]);
        foreach ($new_colors as $new_color) {
            $dbRepo->prepare("INSERT INTO tbl_product_color (color_id, p_id) VALUES (?, ?)")->execute([(int)$new_color, $id]);
        }

        $color_photo_urls = $_POST['color_photo_urls'] ?? [];
        $stmt_select_color_photo = $dbRepo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=? AND color_id=?");
        $stmt_delete_color_photo = $dbRepo->prepare("DELETE FROM tbl_product_photo WHERE p_id=? AND color_id=?");

        foreach ($new_colors as $new_color) {
            $url = $normalize_url($color_photo_urls[$new_color] ?? '');
            if ($url !== '') {
                $stmt_select_color_photo->execute([$id, (int)$new_color]);
                foreach ($stmt_select_color_photo->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    delete_local_image_file($row['photo'] ?? '', '../assets/uploads');
                }
                $stmt_delete_color_photo->execute([$id, (int)$new_color]);
                $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, color_id) VALUES (?, ?, ?)")
                    ->execute([$url, $id, (int)$new_color]);
                continue;
            }

            $name = (string)($_FILES['color_photos']['name'][$new_color] ?? '');
            if ($name === '') {
                continue;
            }
            $tmp = (string)($_FILES['color_photos']['tmp_name'][$new_color] ?? '');
            $err = (int)($_FILES['color_photos']['error'][$new_color] ?? UPLOAD_ERR_NO_FILE);
            $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true) || $err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $error_message .= 'فشل رفع صورة اللون.<br>';
                continue;
            }

            $target_base = 'product-color-' . $id . '-color-' . (int)$new_color . '-' . time();
            list($upload_ok, $stored_color) = store_uploaded_image_file($tmp, $name, $target_base, '../assets/uploads', $error_message, $allowed_ext);
            if (!$upload_ok || $stored_color === '') {
                $error_message .= 'فشل حفظ صورة اللون.<br>';
                continue;
            }

            $stmt_select_color_photo->execute([$id, (int)$new_color]);
            foreach ($stmt_select_color_photo->fetchAll(PDO::FETCH_ASSOC) as $row) {
                delete_local_image_file($row['photo'] ?? '', '../assets/uploads');
            }
            $stmt_delete_color_photo->execute([$id, (int)$new_color]);
            $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, color_id) VALUES (?, ?, ?)")
                ->execute([$stored_color, $id, (int)$new_color]);
        }

        $additional_photo_urls_raw = trim((string)($_POST['additional_photo_urls'] ?? ''));
        if ($additional_photo_urls_raw !== '') {
            foreach (preg_split('/[\r\n,]+/', $additional_photo_urls_raw) as $u) {
                $u = $normalize_url($u);
                if ($u === '') {
                    continue;
                }
                if (!is_valid_image_url($u) && !is_external_image_url($u)) {
                    $error_message .= 'تم تجاهل رابط صورة إضافية غير صالح.<br>';
                    continue;
                }
                $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, is_additional) VALUES (?, ?, 1)")
                    ->execute([$u, $id]);
            }
        }

        if (isset($_FILES['additional_photos']) && is_array($_FILES['additional_photos']['name'])) {
            foreach ($_FILES['additional_photos']['name'] as $k => $name) {
                if (empty($name)) {
                    continue;
                }
                $tmp = (string)($_FILES['additional_photos']['tmp_name'][$k] ?? '');
                $err = (int)($_FILES['additional_photos']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
                $ext = strtolower((string)pathinfo((string)$name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true) || $err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                    $error_message .= 'فشل رفع الصورة الإضافية.<br>';
                    continue;
                }
                $target_base = 'product-additional-' . $id . '-' . time() . '-' . $k;
                list($upload_ok, $stored_additional) = store_uploaded_image_file($tmp, $name, $target_base, '../assets/uploads', $error_message, $allowed_ext);
                if ($upload_ok && $stored_additional !== '') {
                    $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, is_additional) VALUES (?, ?, 1)")
                        ->execute([$stored_additional, $id]);
                } else {
                    $error_message .= 'فشل حفظ الصورة الإضافية.<br>';
                }
            }
        }

        require_once('inc/stock_functions.php');
        stock_sync_variants($pdo, $id);

        require_once('inc/employee_functions.php');
        if (function_exists('employee_save_product_assignment')) {
            $exc_enabled = (int)($_POST['exc_is_enabled'] ?? 0);
            $exc_emp_id = (int)($_POST['exc_employee_id'] ?? 0);
            $exc_mode = trim((string)($_POST['exc_delivery_mode'] ?? 'queue'));
            employee_save_product_assignment($pdo, (int)$id, $exc_enabled, $exc_emp_id, $exc_mode);
        }

        header('Location: product-edit.php?id=' . $id . '&success=1');
        exit;
    }
}

if (isset($_GET['success'])) {
    if ((string)$_GET['success'] === '1') {
        $success_message = 'تم تحديث المنتج بنجاح';
    } elseif ((string)$_GET['success'] === 'featured_deleted') {
        $success_message = 'تم حذف الصورة الرئيسية بنجاح';
    }
}

$statement->execute([$id]);
$p_data = $statement->fetch(PDO::FETCH_ASSOC);

require_once('inc/employee_functions.php');
$active_employees_for_assign = function_exists('employee_get_all') ? employee_get_all($pdo, true) : [];
$exc_assign_data = function_exists('employee_get_product_assignment') ? employee_get_product_assignment($pdo, (int)$id) : null;
$exc_is_enabled_val = (int)($_POST['exc_is_enabled'] ?? ($exc_assign_data['is_enabled'] ?? 0));
$exc_emp_id_val = (int)($_POST['exc_employee_id'] ?? ($exc_assign_data['employee_id'] ?? 0));
$exc_mode_val = trim((string)($_POST['exc_delivery_mode'] ?? ($exc_assign_data['assignment_mode'] ?? 'queue')));

$p_featured_photo = $p_data['p_featured_photo'] ?? '';
$product_template = $p_data['product_template'] ?? 'buy-now.php';
$product_delivery_mode = normalize_product_delivery_mode($_POST['p_delivery_mode'] ?? ($p_data['p_delivery_mode'] ?? 'home_office'));
$selected_product_delivery_company_id = resolve_product_delivery_company_id(
    $pdo,
    (int)($_POST['p_delivery_company_id'] ?? ($p_data['p_delivery_company_id'] ?? $default_product_delivery_company_id))
);
$landing_photo_1 = $p_data['landing_photo_1'] ?? '';
$landing_photo_2 = $p_data['landing_photo_2'] ?? '';
$landing_photo_3 = $p_data['landing_photo_3'] ?? '';
$featured_preview_photo = $p_featured_photo !== '' ? $p_featured_photo : $landing_photo_1;

$offer_prices = [1 => '', 2 => '', 3 => ''];
$most_popular_offer = '';
$special_offers_for_form = [];
foreach ($special_offer_slots as $slot) {
    $special_offers_for_form[$slot] = [
        'price' => '',
        'description' => '',
        'photo' => '',
        'photo_url' => ''
    ];
}
$stmt_offers = $dbRepo->prepare("SELECT offer_qty, offer_unit_price, offer_type, offer_description, offer_photo, is_active, is_most_popular FROM tbl_product_offer WHERE p_id = ? ORDER BY sort_order ASC, offer_qty ASC");
$stmt_offers->execute([$id]);
$special_offer_slot_index = 1;
foreach ($stmt_offers->fetchAll(PDO::FETCH_ASSOC) as $offer_row) {
    $offer_type = (string)($offer_row['offer_type'] ?? 'quantity');
    $is_popular = ((int)($offer_row['is_most_popular'] ?? 0) === 1);
    if ($offer_type === 'special') {
        if ((int)$offer_row['is_active'] === 1 && isset($special_offers_for_form[$special_offer_slot_index])) {
            $special_offers_for_form[$special_offer_slot_index]['price'] = (string)($offer_row['offer_unit_price'] ?? '');
            $special_offers_for_form[$special_offer_slot_index]['description'] = (string)($offer_row['offer_description'] ?? '');
            $special_offers_for_form[$special_offer_slot_index]['photo'] = (string)($offer_row['offer_photo'] ?? '');
            if ($is_popular) {
                $most_popular_offer = 'special:' . (int)$special_offer_slot_index;
            }
            $special_offer_slot_index++;
        }
        continue;
    }
    $qty = (int)$offer_row['offer_qty'];
    if ($qty >= 1 && $qty <= 3 && (int)$offer_row['is_active'] === 1) {
        $offer_prices[$qty] = $offer_row['offer_unit_price'];
        if ($is_popular) {
            $most_popular_offer = 'quantity:' . (int)$qty;
        }
    }
}

$size_id = [];
$stmt_sizes = $dbRepo->prepare("SELECT size_id FROM tbl_product_size WHERE p_id=?");
$stmt_sizes->execute([$id]);
foreach ($stmt_sizes->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $size_id[] = (int)$row['size_id'];
}

$color_id = [];
$pixel_id = [];
$stmt_pixels = $dbRepo->prepare("SELECT pixel_id FROM tbl_product_pixel WHERE product_id=?");
$stmt_pixels->execute([$id]);
foreach ($stmt_pixels->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pixel_id[] = (int)$row['pixel_id'];
}
$stmt_colors = $dbRepo->prepare("SELECT color_id FROM tbl_product_color WHERE p_id=?");
$stmt_colors->execute([$id]);
foreach ($stmt_colors->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $color_id[] = (int)$row['color_id'];
}

$color_photo_map = [];
$stmt_color_photo_map = $dbRepo->prepare("SELECT color_id, photo FROM tbl_product_photo WHERE p_id=? AND color_id IS NOT NULL AND color_id > 0 AND (is_additional IS NULL OR is_additional = 0)");
$stmt_color_photo_map->execute([$id]);
foreach ($stmt_color_photo_map->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $color_photo_map[(int)$row['color_id']] = (string)($row['photo'] ?? '');
}

$stmt_additional = $dbRepo->prepare("SELECT * FROM tbl_product_photo WHERE p_id=? AND (is_additional=1 OR ((is_additional IS NULL OR is_additional=0) AND (color_id IS NULL OR color_id=0))) ORDER BY pp_id ASC");
$stmt_additional->execute([$id]);
$additional_photos = $stmt_additional->fetchAll(PDO::FETCH_ASSOC);

$featured_url_value = $_POST['p_featured_photo_url'] ?? '';
if ($featured_url_value === '' && is_external_image_url($p_featured_photo)) {
    $featured_url_value = $p_featured_photo;
}
$landing_1_url_value = $_POST['landing_photo_1_url'] ?? '';
if ($landing_1_url_value === '' && is_external_image_url($landing_photo_1)) {
    $landing_1_url_value = $landing_photo_1;
}
$landing_2_url_value = $_POST['landing_photo_2_url'] ?? '';
if ($landing_2_url_value === '' && is_external_image_url($landing_photo_2)) {
    $landing_2_url_value = $landing_photo_2;
}
$landing_3_url_value = $_POST['landing_photo_3_url'] ?? '';
if ($landing_3_url_value === '' && is_external_image_url($landing_photo_3)) {
    $landing_3_url_value = $landing_photo_3;
}
foreach ($special_offer_slots as $slot) {
    $special_offers_for_form[$slot]['price'] = $_POST['special_offer_price_' . $slot] ?? $special_offers_for_form[$slot]['price'];
    $special_offers_for_form[$slot]['description'] = $_POST['special_offer_description_' . $slot] ?? $special_offers_for_form[$slot]['description'];
    $special_offers_for_form[$slot]['photo_url'] = $_POST['special_offer_photo_url_' . $slot] ?? '';
    if ($special_offers_for_form[$slot]['photo_url'] === '' && is_external_image_url($special_offers_for_form[$slot]['photo'])) {
        $special_offers_for_form[$slot]['photo_url'] = $special_offers_for_form[$slot]['photo'];
    }
}

$selected_pixels_for_form = array_map('intval', (array)($_POST['pixel'] ?? $pixel_id));
$selected_sizes_for_form = array_map('intval', (array)($_POST['size'] ?? $size_id));
$selected_colors_for_form = array_map('intval', (array)($_POST['color'] ?? $color_id));
$posted_color_urls = $_POST['color_photo_urls'] ?? [];
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>تعديل المنتج</h1>
    </div>
    <div class="content-header-right">
        <a href="product.php" class="btn btn-primary btn-sm">كل المنتجات</a>
    </div>
</section>

<section class="content admin-product-page product-edit-page">
    <div class="row">
        <div class="col-md-12">
            <?php if ($error_message): ?>
                <div class="callout callout-danger"><?= $error_message ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="callout callout-success"><?= $success_message ?></div>
            <?php endif; ?>
            <?php if (function_exists('cloudinary_is_strict_mode') && function_exists('cloudinary_is_enabled') && cloudinary_is_strict_mode() && !cloudinary_is_enabled()): ?>
                <div class="callout callout-warning">وضع Cloudinary الصارم مفعّل. لا يمكن حفظ الصور حتى تضبط مفاتيح Cloudinary في ملف الإعدادات.</div>
            <?php endif; ?>

            
<div class="nav-tabs-custom" style="border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05); margin-bottom: 20px;">
    <ul class="nav nav-tabs" style="background:#fff; border-bottom:1px solid #e2e8f0; padding:10px 15px 0 15px;">
        <li class="active"><a href="#tab_1" data-toggle="tab" style="font-weight:bold;">البيانات الأساسية</a></li>
        <li><a href="#tab_ai" data-toggle="tab" style="font-weight:bold; color:#4f46e5;"><i class="fa fa-magic"></i> الذكاء الاصطناعي (AI)</a></li>
        <li><a href="#tab_marketing" data-toggle="tab" style="font-weight:bold; color:#ec4899;"><i class="fa fa-bullhorn"></i> التسويق (Marketing)</a></li>
    </ul>
    <div class="tab-content" style="background:#fff; padding: 20px;">
        <div class="tab-pane active" id="tab_1">
            <form class="form-horizontal admin-product-form" method="post" enctype="multipart/form-data">

                <input type="hidden" name="current_photo" value="<?= htmlspecialchars($p_featured_photo) ?>">
                <input type="hidden" name="current_landing_photo_1" value="<?= htmlspecialchars($landing_photo_1) ?>">
                <input type="hidden" name="current_landing_photo_2" value="<?= htmlspecialchars($landing_photo_2) ?>">
                <input type="hidden" name="current_landing_photo_3" value="<?= htmlspecialchars($landing_photo_3) ?>">
                <?php foreach ($special_offer_slots as $slot): ?>
                    <input type="hidden" name="current_special_offer_photo_<?= $slot ?>" value="<?= htmlspecialchars($special_offers_for_form[$slot]['photo']) ?>">
                <?php endforeach; ?>

                <div class="box box-info admin-product-card">
                    <div class="box-body">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">القالب</label>
                            <div class="col-sm-4">
                                <select name="product_template" id="product_template" class="form-control">
                                    <option value="landing_page.php" <?= ($product_template === 'landing_page.php') ? 'selected' : '' ?>>صفحة هبوط</option>
                                    <option value="landing_page_2.php" <?= ($product_template === 'landing_page_2.php') ? 'selected' : '' ?>>صفحة هبوط 2</option>
                                    <option value="buy-now.php" <?= ($product_template === 'buy-now.php') ? 'selected' : '' ?>>عادي</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">نوع التوصيل</label>
                            <div class="col-sm-4">
                                <select name="p_delivery_mode" class="form-control">
                                    <option value="free" <?= $product_delivery_mode === 'free' ? 'selected' : '' ?>>توصيل مجاني</option>
                                    <option value="home_only" <?= $product_delivery_mode === 'home_only' ? 'selected' : '' ?>>المنزل فقط</option>
                                    <option value="home_office" <?= $product_delivery_mode === 'home_office' ? 'selected' : '' ?>>المنزل + المكتب</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">شركة التوصيل</label>
                            <div class="col-sm-4">
                                <?php if (!empty($delivery_companies)): ?>
                                    <select name="p_delivery_company_id" class="form-control">
                                        <?php foreach ($delivery_companies as $delivery_company): ?>
                                            <?php
                                            $delivery_company_id = (int)$delivery_company['id'];
                                            $is_selected = ($delivery_company_id === (int)$selected_product_delivery_company_id);
                                            $delivery_company_label = $delivery_company['name'] . (((int)$delivery_company['active'] === 1) ? ' (النشطة)' : '');
                                            ?>
                                            <option value="<?= $delivery_company_id ?>" <?= $is_selected ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($delivery_company_label, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="help-block">القيمة الافتراضية هي الشركة النشطة، ويمكنك تغييرها لهذا المنتج.</span>
                                <?php else: ?>
                                    <input type="text" class="form-control" value="لا توجد شركات توصيل مضافة حالياً" disabled>
                                <?php endif; ?>
                            </div>
                        </div>

                        <input type="hidden" name="ecat_id" value="<?= (int)($p_data['ecat_id'] ?? 0) ?>">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">اسم المنتج</label>
                            <div class="col-sm-4"><input type="text" name="p_name" class="form-control" value="<?= htmlspecialchars($_POST['p_name'] ?? ($p_data['p_name'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">إعلان مختصر</label>
                            <div class="col-sm-4"><input type="text" name="p_announcement" class="form-control" value="<?= htmlspecialchars($_POST['p_announcement'] ?? ($p_data['p_announcement'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">السعر القديم</label>
                            <div class="col-sm-4"><input type="text" name="p_old_price" class="form-control" value="<?= htmlspecialchars($_POST['p_old_price'] ?? ($p_data['p_old_price'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">السعر الحالي</label>
                            <div class="col-sm-4"><input type="text" name="p_current_price" class="form-control" value="<?= htmlspecialchars($_POST['p_current_price'] ?? ($p_data['p_current_price'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">سعر الشراء</label>
                            <div class="col-sm-4"><input type="text" name="purchase_price" class="form-control" value="<?= htmlspecialchars($_POST['purchase_price'] ?? ($p_data['purchase_price'] ?? '0.00')) ?>"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">الكمية</label>
                            <div class="col-sm-4"><input type="text" name="p_qty" class="form-control" value="<?= htmlspecialchars($_POST['p_qty'] ?? ($p_data['p_qty'] ?? '')) ?>"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">أسعار عروض الكمية</label>
                            <div class="col-sm-4">
                                <div style="display:flex;gap:10px;align-items:center;">
                                    <input type="radio" name="most_popular_offer" value="quantity:1" <?= ((string)($_POST['most_popular_offer'] ?? $most_popular_offer) === 'quantity:1') ? 'checked' : '' ?> title="الأكثر طلباً">
                                    <input type="number" name="offer_price_1" class="form-control" step="0.01" min="0" placeholder="سعر عرض 1" value="<?= htmlspecialchars($_POST['offer_price_1'] ?? ($offer_prices[1] ?? '')) ?>">
                                </div>
                                <br>
                                <div style="display:flex;gap:10px;align-items:center;">
                                    <input type="radio" name="most_popular_offer" value="quantity:2" <?= ((string)($_POST['most_popular_offer'] ?? $most_popular_offer) === 'quantity:2') ? 'checked' : '' ?> title="الأكثر طلباً">
                                    <input type="number" name="offer_price_2" class="form-control" step="0.01" min="0" placeholder="سعر عرض 2" value="<?= htmlspecialchars($_POST['offer_price_2'] ?? ($offer_prices[2] ?? '')) ?>">
                                </div>
                                <br>
                                <div style="display:flex;gap:10px;align-items:center;">
                                    <input type="radio" name="most_popular_offer" value="quantity:3" <?= ((string)($_POST['most_popular_offer'] ?? $most_popular_offer) === 'quantity:3') ? 'checked' : '' ?> title="الأكثر طلباً">
                                    <input type="number" name="offer_price_3" class="form-control" step="0.01" min="0" placeholder="سعر عرض 3" value="<?= htmlspecialchars($_POST['offer_price_3'] ?? ($offer_prices[3] ?? '')) ?>">
                                </div>
                                <span class="help-block">ضع علامة "الأكثر طلباً" على عرض واحد (اختياري) ليظهر افتراضياً في صفحة الهبوط.</span>
                                <span class="help-block">إذا استخدمت عروض الكمية، اترك العروض الخاصة فارغة.</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">العروض الخاصة</label>
                            <div class="col-sm-4" id="special-offer-fields">
                                <?php foreach ($special_offer_slots as $slot): ?>
                                    <?php $special_offer_item = $special_offers_for_form[$slot]; ?>
                                    <div class="panel panel-default" style="margin-bottom:12px;">
                                        <div class="panel-heading" style="font-weight:700;">العرض <?= $slot ?></div>
                                        <div class="panel-body">
                                            <label style="display:flex;gap:10px;align-items:center;margin-bottom:10px;font-weight:700;">
                                                <input type="radio" name="most_popular_offer" value="special:<?= (int)$slot ?>" <?= ((string)($_POST['most_popular_offer'] ?? $most_popular_offer) === ('special:' . (int)$slot)) ? 'checked' : '' ?>>
                                                <span>الأكثر طلباً</span>
                                            </label>
                                            <input type="number" name="special_offer_price_<?= $slot ?>" class="form-control" step="0.01" min="0" placeholder="سعر العرض الخاص <?= $slot ?>" value="<?= htmlspecialchars((string)$special_offer_item['price']) ?>">
                                            <br>
                                            <textarea name="special_offer_description_<?= $slot ?>" class="form-control" rows="3" placeholder="وصف العرض الخاص <?= $slot ?>"><?= htmlspecialchars($special_offer_item['description']) ?></textarea>
                                            <br>
                                            <?php if (!empty($special_offer_item['photo'])): ?>
                                                <div class="admin-thumb-wrap"><img src="<?= htmlspecialchars(get_admin_image_url($special_offer_item['photo']), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--sm"></div>
                                            <?php endif; ?>
                                            <input type="file" name="special_offer_photo_<?= $slot ?>" class="form-control">
                                            <br>
                                            <input type="text" name="special_offer_photo_url_<?= $slot ?>" class="form-control js-url-input" placeholder="رابط صورة العرض الخاص <?= $slot ?>" value="<?= htmlspecialchars($special_offer_item['photo_url']) ?>">
                                            <div class="admin-thumb-wrap js-url-preview-box" style="display:none;">
                                                <img src="" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <span class="help-block">كل عرض خاص يحتاج صورة ووصفًا وسعرًا، ولا يمكن جمعه مع عروض الكمية.</span>
                            </div>
                        </div>

                        <div class="form-group" id="description-section">
                            <label class="col-sm-3 control-label">الوصف</label>
                            <div class="col-sm-4"><textarea name="p_description" class="form-control" rows="4"><?= htmlspecialchars($_POST['p_description'] ?? ($p_data['p_description'] ?? '')) ?></textarea></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">وصف إضافي</label>
                            <div class="col-sm-4"><textarea name="more_description" class="form-control" rows="4"><?= htmlspecialchars($_POST['more_description'] ?? ($p_data['more_description'] ?? '')) ?></textarea></div>
                        </div>

                        <div class="form-group" id="featured-photo-section">
                            <label class="col-sm-3 control-label">الصورة الرئيسية</label>
                            <div class="col-sm-4">
                                <?php if (!empty($featured_preview_photo)): ?>
                                    <div class="admin-thumb-wrap"><img src="<?= htmlspecialchars(get_admin_image_url($featured_preview_photo), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--lg"></div>
                                    <button type="submit" class="btn btn-danger btn-xs" name="remove_featured_photo" value="1" formnovalidate onclick="return confirm('هل تريد حذف الصورة الرئيسية؟');" style="margin-bottom:8px;">حذف الصورة الرئيسية</button>
                                <?php endif; ?>
                                <input type="file" name="p_featured_photo" class="form-control">
                                <br>
                                <input type="text" name="p_featured_photo_url" class="form-control js-url-input" placeholder="أو رابط صورة" value="<?= htmlspecialchars($featured_url_value) ?>">
                                <div class="admin-thumb-wrap js-url-preview-box" style="display:none;">
                                    <img src="" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">المقاسات</label>
                            <div class="col-sm-4">
                                <select name="size[]" class="form-control select2" multiple="multiple">
                                    <?php
                                    $stmt = $dbRepo->prepare("SELECT * FROM tbl_size ORDER BY size_name ASC");
                                    $stmt->execute();
                                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row):
                                        $selected = in_array((int)$row['size_id'], $selected_sizes_for_form, true) ? 'selected' : '';
                                        echo "<option value='{$row['size_id']}' {$selected}>{$row['size_name']}</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">الألوان</label>
                            <div class="col-sm-4">
                                <select name="color[]" id="colorSelect" class="form-control select2" multiple="multiple">
                                    <?php
                                    $stmt = $dbRepo->prepare("SELECT * FROM tbl_color ORDER BY color_name ASC");
                                    $stmt->execute();
                                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row):
                                        $selected = in_array((int)$row['color_id'], $selected_colors_for_form, true) ? 'selected' : '';
                                        echo "<option value='{$row['color_id']}' {$selected}>{$row['color_name']}</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div id="colorPhotoFields"></div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">بكسلات التتبع (Pixels)</label>
                            <div class="col-sm-4">
                                <select name="pixel[]" class="form-control select2" multiple="multiple">
                                    <?php
                                    $stmt = $dbRepo->prepare("SELECT * FROM tbl_pixel ORDER BY pixel_name ASC");
                                    $stmt->execute();
                                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row):
                                        $selected = in_array((int)$row['id'], $selected_pixels_for_form, true) ? 'selected' : '';
                                        echo "<option value='{$row['id']}' {$selected}>{$row['pixel_name']} ({$row['pixel_network']})</option>";
                                    endforeach;
                                    ?>
                                </select>
                                <small style="color:#6b7280;margin-top:4px;display:block;">اضغط Ctrl/Cmd لاختيار أكثر من بكسل</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">منتج مميز؟</label>
                            <div class="col-sm-4">
                                <select name="p_is_featured" class="form-control">
                                    <option value="0" <?= ((string)($_POST['p_is_featured'] ?? ($p_data['p_is_featured'] ?? 0)) === '0') ? 'selected' : '' ?>>لا</option>
                                    <option value="1" <?= ((string)($_POST['p_is_featured'] ?? ($p_data['p_is_featured'] ?? 0)) === '1') ? 'selected' : '' ?>>نعم</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">نشط؟</label>
                            <div class="col-sm-4">
                                <select name="p_is_active" class="form-control">
                                    <option value="1" <?= ((string)($_POST['p_is_active'] ?? ($p_data['p_is_active'] ?? 1)) === '1') ? 'selected' : '' ?>>نعم</option>
                                    <option value="0" <?= ((string)($_POST['p_is_active'] ?? ($p_data['p_is_active'] ?? 1)) === '0') ? 'selected' : '' ?>>لا</option>
                                </select>
                            </div>
                        </div>
                        <div class="box box-info admin-product-card" id="exclusive-assignment-section" style="border-top: 3px solid #00c0ef; margin: 20px 0;">
                            <div class="box-header with-border">
                                <h3 class="box-title" style="font-weight: 700; color: #00c0ef;"><i class="fa fa-user-secret"></i> التخصيص الحصري للمنتج (Exclusive Assignment)</h3>
                                <p class="help-block" style="margin-bottom:0;">عند تفعيل التخصيص الحصري، سيتم إسناد جميع طلبات هذا المنتج حصرياً لموظف محدد، ولن تدخل في طابور التوزيع العام (WRR).</p>
                            </div>
                            <div class="box-body">
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">تفعيل التخصيص الحصري</label>
                                    <div class="col-sm-4">
                                        <select name="exc_is_enabled" id="exc_is_enabled" class="form-control">
                                            <option value="0" <?= ($exc_is_enabled_val === 0) ? 'selected' : '' ?>>معطل (OFF - الافتراضي)</option>
                                            <option value="1" <?= ($exc_is_enabled_val === 1) ? 'selected' : '' ?>>مفعل (ON)</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="exc_fields_wrapper" style="<?= ($exc_is_enabled_val === 1) ? '' : 'display: none;' ?>">
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">الموظف المخصص (Assigned Employee)</label>
                                        <div class="col-sm-4">
                                            <select name="exc_employee_id" class="form-control select2" style="width:100%;">
                                                <option value="0">-- اختر الموظف المخصص --</option>
                                                <?php foreach ($active_employees_for_assign as $emp_assign): ?>
                                                    <?php $selected_emp = ((int)($emp_assign['id']) === $exc_emp_id_val) ? 'selected' : ''; ?>
                                                    <option value="<?= (int)$emp_assign['id'] ?>" <?= $selected_emp ?>><?= htmlspecialchars($emp_assign['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">طريقة وصول الطلبات (Delivery Mode)</label>
                                        <div class="col-sm-6">
                                            <div class="radio" style="margin-bottom: 10px;">
                                                <label style="font-weight: 600;">
                                                    <input type="radio" name="exc_delivery_mode" value="queue" <?= ($exc_mode_val === 'queue') ? 'checked' : '' ?>>
                                                    <span class="label label-warning" style="padding: 4px 8px; margin-left: 5px;">Employee Queue</span>
                                                    دخول الطلبات إلى طابور الانتظار الخاص بالموظف (بحالة Waiting) ليقوم بقبولها بنفسه.
                                                </label>
                                            </div>
                                            <div class="radio">
                                                <label style="font-weight: 600;">
                                                    <input type="radio" name="exc_delivery_mode" value="direct" <?= ($exc_mode_val === 'direct') ? 'checked' : '' ?>>
                                                    <span class="label label-success" style="padding: 4px 8px; margin-left: 5px;">Direct Assignment</span>
                                                    إسناد الطلبات مباشرة إلى مساحة العمل الخاصة بالموظف (بحالة Active).
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="landing-photos-section">
                            <h4>صور صفحة الهبوط</h4>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">صورة صفحة الهبوط 1</label>
                                <div class="col-sm-4">
                                    <?php if (!empty($landing_photo_1)): ?><div class="admin-thumb-wrap"><img src="<?= htmlspecialchars(get_admin_image_url($landing_photo_1), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--sm"></div><?php endif; ?>
                                    <input type="file" name="landing_photo_1" class="form-control">
                                    <br>
                                    <input type="text" name="landing_photo_1_url" class="form-control js-url-input" placeholder="أو رابط صورة" value="<?= htmlspecialchars($landing_1_url_value) ?>">
                                    <div class="admin-thumb-wrap js-url-preview-box" style="display:none;">
                                        <img src="" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">صورة صفحة الهبوط 2</label>
                                <div class="col-sm-4">
                                    <?php if (!empty($landing_photo_2)): ?><div class="admin-thumb-wrap"><img src="<?= htmlspecialchars(get_admin_image_url($landing_photo_2), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--sm"></div><?php endif; ?>
                                    <input type="file" name="landing_photo_2" class="form-control">
                                    <br>
                                    <input type="text" name="landing_photo_2_url" class="form-control js-url-input" placeholder="أو رابط صورة" value="<?= htmlspecialchars($landing_2_url_value) ?>">
                                    <div class="admin-thumb-wrap js-url-preview-box" style="display:none;">
                                        <img src="" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">صورة صفحة الهبوط 3</label>
                                <div class="col-sm-4">
                                    <?php if (!empty($landing_photo_3)): ?><div class="admin-thumb-wrap"><img src="<?= htmlspecialchars(get_admin_image_url($landing_photo_3), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--sm"></div><?php endif; ?>
                                    <input type="file" name="landing_photo_3" class="form-control">
                                    <br>
                                    <input type="text" name="landing_photo_3_url" class="form-control js-url-input" placeholder="أو رابط صورة" value="<?= htmlspecialchars($landing_3_url_value) ?>">
                                    <div class="admin-thumb-wrap js-url-preview-box" style="display:none;">
                                        <img src="" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box box-info admin-product-card" id="additional-photos-section">
                    <div class="box-header with-border">
                        <h3 class="box-title">الصور الإضافية</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">الصور الإضافية الحالية</label>
                            <div class="col-sm-6" style="display:flex;flex-wrap:wrap;gap:10px;">
                                <?php foreach ($additional_photos as $row): ?>
                                    <?php if (!empty($row['photo'])): ?>
                                        <div>
                                            <img src="<?= htmlspecialchars(get_admin_image_url($row['photo']), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--sm">
                                            <a
                                                class="btn btn-danger btn-xs"
                                                style="margin-top:6px;"
                                                href="product-other-photo-delete.php?id=<?= isset($row['id']) ? rawurlencode((string)$row['id']) : rawurlencode((string)$row['pp_id']) ?>&product_id=<?= (int)$id ?>&id1=<?= (int)$id ?>"
                                                onclick="return confirm('هل تريد حذف هذه الصورة؟');"
                                            >حذف</a>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">إضافة صور إضافية جديدة</label>
                            <div class="col-sm-4">
                                <input type="file" name="additional_photos[]" class="form-control" multiple accept="image/*">
                                <br>
                                <textarea name="additional_photo_urls" class="form-control" rows="4" placeholder="روابط الصور الإضافية، كل رابط في سطر"><?= htmlspecialchars($_POST['additional_photo_urls'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label"></label>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-success" name="form1">حفظ التعديلات</button>
                    </div>
                </div>
            
            </form>
        </div>
        
        <!-- AI TAB -->
        <div class="tab-pane" id="tab_ai">
            <div id="ai-card-wrapper">جاري التحميل...</div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const aiWrapper = document.getElementById('ai-card-wrapper');
                    fetch('ai-product-tab.php?id=' + encodeURIComponent('<?= $id ?>'))
                        .then(r => r.text())
                        .then(html => {
                            aiWrapper.innerHTML = html;
                            // Execute any scripts in the loaded HTML
                            const scripts = aiWrapper.querySelectorAll('script');
                            scripts.forEach(s => {
                                const newScript = document.createElement('script');
                                newScript.textContent = s.textContent;
                                document.body.appendChild(newScript).parentNode.removeChild(newScript);
                            });
                        })
                        .catch(e => aiWrapper.innerHTML = '<div class="alert alert-danger">خطأ في تحميل بيانات الذكاء الاصطناعي.</div>');
                });
            </script>
        </div>
        
        <!-- MARKETING TAB -->
        <div class="tab-pane" id="tab_marketing">
            <div id="marketing-card-wrapper">جاري التحميل...</div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const mkgWrapper = document.getElementById('marketing-card-wrapper');
                    fetch('marketing-product-tab.php?id=' + encodeURIComponent('<?= $id ?>'))
                        .then(r => r.text())
                        .then(html => {
                            mkgWrapper.innerHTML = html;
                            const scripts = mkgWrapper.querySelectorAll('script');
                            scripts.forEach(s => {
                                const newScript = document.createElement('script');
                                newScript.textContent = s.textContent;
                                document.body.appendChild(newScript).parentNode.removeChild(newScript);
                            });
                        })
                        .catch(e => mkgWrapper.innerHTML = '<div class="alert alert-danger">خطأ في تحميل بيانات التسويق.</div>');
                });
            </script>
        </div>
        
    </div> <!-- end tab-content -->
</div> <!-- end nav-tabs-custom -->


        </div>
    </div>
</section>

<style>
.admin-thumb--lg {
    width: 128px !important;
    height: 92px !important;
    max-width: 128px !important;
    max-height: 92px !important;
    object-fit: contain !important;
}

.admin-thumb--sm {
    width: 84px !important;
    height: 62px !important;
    max-width: 84px !important;
    max-height: 62px !important;
    object-fit: contain !important;
}

.admin-thumb {
    display: block !important;
    border: 1px solid #dfe7f1 !important;
    border-radius: 10px !important;
    background: #fff !important;
}

.admin-thumb-wrap {
    max-width: 140px !important;
    overflow: hidden !important;
}

.js-url-preview-box {
    margin-top: 8px !important;
}

/* Fallback constraints to ensure any thumbnail previews do not exceed reasonable sizing */
.js-url-preview-box img,
#colorPhotoFields img,
#additional-photos-section img,
.admin-thumb-wrap img {
    max-width: 130px !important;
    height: auto !important;
    object-fit: contain !important;
}
</style>
<script>
window.addEventListener('load', function() {
    const $ = window.jQuery;
    if (!$) {
        return;
    }

    const selectedColors = <?= json_encode($selected_colors_for_form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const existingColorPhotos = <?= json_encode($color_photo_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const postedColorUrls = <?= json_encode($posted_color_urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function toAdminImageUrl(value) { global $dbRepo;
    global $dbRepo;

        if (!value) return '';
        if (/^(https?:)?\/\//i.test(value)) return value;
        return '../assets/uploads/' + String(value).replace(/^[/\\]+/, '');
    }

    function safeAttr(value) { global $dbRepo;
    global $dbRepo;

        return String(value || '').replace(/"/g, '&quot;');
    }

    function updateUrlPreview(input) { global $dbRepo;
    global $dbRepo;

        const $input = $(input);
        const $box = $input.siblings('.js-url-preview-box').first();
        const $img = $box.find('.js-url-preview-img');
        if (!$box.length || !$img.length) {
            return;
        }

        const value = String($input.val() || '').trim();
        if (!value || !/^(https?:)?\/\//i.test(value)) {
            $img.attr('src', '');
            $box.hide();
            return;
        }

        $img.attr('src', toAdminImageUrl(value));
        $box.show();
    }

    function bindUrlPreviewInputs(scope) { global $dbRepo;
    global $dbRepo;

        const $scope = $(scope || document);
        $scope.find('input.js-url-input')
            .off('input.urlPreview change.urlPreview')
            .on('input.urlPreview change.urlPreview', function() {
                updateUrlPreview(this);
            });

        $scope.find('input.js-url-input').each(function() {
            updateUrlPreview(this);
        });
    }

    function updateColorPhotoFields(values) { global $dbRepo;
    global $dbRepo;

        const wrapper = $('#colorPhotoFields');
        wrapper.empty();
        if (!values || !values.length) return;

        values.forEach(function(colorId) {
            const colorName = $('#colorSelect option[value="' + colorId + '"]').text();
            const existingPhoto = existingColorPhotos[colorId] || '';
            const postedUrl = postedColorUrls[colorId] || '';
            const defaultUrl = postedUrl || (/^(https?:)?\/\//i.test(existingPhoto) ? existingPhoto : '');
            const existingImg = existingPhoto ? '<div style="margin-bottom:8px;"><img src="' + toAdminImageUrl(existingPhoto) + '" class="admin-thumb admin-thumb--sm"></div>' : '';
            const previewSrc = defaultUrl ? toAdminImageUrl(defaultUrl) : '';
            const previewBox = '<div class="admin-thumb-wrap js-url-preview-box" style="' + (previewSrc ? '' : 'display:none;') + '"><img src="' + safeAttr(previewSrc) + '" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط"></div>';
            wrapper.append('<div class="form-group"><label class="col-sm-3 control-label">صورة اللون: ' + colorName + '</label><div class="col-sm-4">' + existingImg + '<input type="file" name="color_photos[' + colorId + ']" class="form-control"><br><input type="text" name="color_photo_urls[' + colorId + ']" class="form-control js-url-input" placeholder="أو رابط صورة" value="' + safeAttr(defaultUrl) + '">' + previewBox + '</div></div>');
        });

        bindUrlPreviewInputs(wrapper);
    }

    $('#colorSelect').on('change', function() {
        updateColorPhotoFields($(this).val() || []);
    });

    updateColorPhotoFields(selectedColors);
    bindUrlPreviewInputs(document);
});
</script>
<script>
window.addEventListener('load', function() {
    const $ = window.jQuery;
    if (!$) {
        return;
    }

    function toggleFields() {        const template = $('#product_template').val();
        if (template === 'landing_page.php' || template === 'landing_page_2.php') {
            $('#landing-photos-section').show();
            $('#featured-photo-section').show();
            $('#description-section').hide();
            $('#additional-photos-section').show();
        } else {
            $('#landing-photos-section').hide();
            $('#featured-photo-section').show();
            $('#description-section').show();
            $('#additional-photos-section').show();
        }
    }

    function syncOfferModes() {        const $quantityInputs = $('input[name="offer_price_1"], input[name="offer_price_2"], input[name="offer_price_3"]');
        const $specialInputs = $('#special-offer-fields').find('input, textarea');
        const quantityHasValue = $quantityInputs.toArray().some(function(input) {
            return String(input.value || '').trim() !== '';
        });
        const specialHasValue = $specialInputs.toArray().some(function(input) {
            return String(input.value || '').trim() !== '';
        });

        if (quantityHasValue && specialHasValue) {
            $quantityInputs.prop('disabled', false);
            $specialInputs.prop('disabled', false);
            return;
        }

        $quantityInputs.prop('disabled', specialHasValue);
        $specialInputs.prop('disabled', quantityHasValue);
    }

    toggleFields();
    $('#product_template').on('change', toggleFields);
    syncOfferModes();

    $('input[name="offer_price_1"], input[name="offer_price_2"], input[name="offer_price_3"]').on('input change', syncOfferModes);
    $('#special-offer-fields').find('input, textarea').on('input change', syncOfferModes);

    $('#tcat_id').on('change', function() {
        $.ajax({
            url: 'get-mid-category.php',
            type: 'POST',
            data: { id: $(this).val() },
            success: function(response) {
                $('.mid-cat').html(response);
                $('.end-cat').html('<option value="">اختر الفئة النهائية</option>');
            }
        });
    });

    $(document).on('change', '.mid-cat', function() {
        $.ajax({
            url: 'get-end-category.php',
            type: 'POST',
            data: { id: $(this).val() },
            success: function(response) {
                $('.end-cat').html(response);
            }
        });
    });

    $('#exc_is_enabled').on('change', function() {
        if ($(this).val() === '1') {
            $('#exc_fields_wrapper').slideDown();
        } else {
            $('#exc_fields_wrapper').slideUp();
        }
    });
});
</script>

<?php require_once('footer.php'); ?>
