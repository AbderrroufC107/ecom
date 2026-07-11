<?php
// Debug helper (server): show fatal errors on screen when requested.
// IMPORTANT: this must run before header.php include.
// Use: product-add.php?debug=1
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
?>
<?php

// حماية للاستضافة: إذا كان inc/functions.php قديم/غير مكتمل سيحدث Fatal Error وتظهر صفحة فارغة.
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
            <h4 style="margin:0 0 8px; font-weight:800;">تعطل صفحة إضافة المنتج على الاستضافة</h4>
            <div style="color:#111827; line-height:1.9;">
                السبب الأغلب: ملف <code>admin/inc/functions.php</code> على السيرفر قديم أو ناقص مقارنة باللوكال.
                <br>
                <strong>الدوال الناقصة:</strong>
                <code><?php echo htmlspecialchars(implode(', ', $missing_functions), ENT_QUOTES, 'UTF-8'); ?></code>
                <br>
                <strong>الحل:</strong> ارفع/استبدل ملف <code>admin/inc/functions.php</code> من نسختك المحلية إلى الاستضافة، ثم أعد تحميل الصفحة.
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
            // Ignore if column exists.
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

if (!function_exists('product_add_ensure_column')) {
    function product_add_ensure_column(PDO $pdo, $table, $column, $definition)
    { global $dbRepo;

        try {
            $statement = $dbRepo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column));
            if (!$statement->fetch(PDO::FETCH_ASSOC)) {
                $dbRepo->executeCommand("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (PDOException $e) {
            error_log('Failed to ensure ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
    }
}

if (!function_exists('product_add_ensure_database_schema')) {
    function product_add_ensure_database_schema(PDO $pdo)
    { global $dbRepo;

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
            product_add_ensure_column($pdo, 'tbl_product', $column, $definition);
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
            product_add_ensure_column($pdo, 'tbl_product_offer', $column, $definition);
        }
    }
}

$valid = 1;
$error_message = '';
$success_message = '';

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
product_add_ensure_database_schema($pdo);
$delivery_companies = get_delivery_company_options($pdo);
$default_product_delivery_company_id = resolve_product_delivery_company_id($pdo, 0);
$selected_product_delivery_company_id = resolve_product_delivery_company_id(
    $pdo,
    (int)($_POST['p_delivery_company_id'] ?? $default_product_delivery_company_id)
);

ensure_product_offer_table($pdo);
$special_offer_slots = [1, 2, 3];

if (isset($_POST['form1'])) {
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

    if (empty($_POST['p_name'])) {
        $valid = 0;
        $error_message .= 'اسم المنتج لا يمكن أن يكون فارغًا.<br>';
    }
    if (empty($_POST['p_current_price'])) {
        $valid = 0;
        $error_message .= 'سعر المنتج لا يمكن أن يكون فارغًا.<br>';
    }
    if (empty($_POST['p_qty'])) {
        $valid = 0;
        $error_message .= 'الكمية لا يمكن أن تكون فارغة.<br>';
    }
    $product_ecat_id = (int)($_POST['ecat_id'] ?? 0);
    $product_template = $_POST['product_template'] ?? 'landing_page.php';
    $is_landing_template = in_array($product_template, ['landing_page.php', 'landing_page_2.php'], true);
    $product_delivery_mode = normalize_product_delivery_mode($_POST['p_delivery_mode'] ?? 'home_office');
    $product_delivery_company_id = resolve_product_delivery_company_id(
        $pdo,
        (int)($_POST['p_delivery_company_id'] ?? $default_product_delivery_company_id)
    );

    $featured_photo_url = $normalize_url($_POST['p_featured_photo_url'] ?? '');
    $landing_photo_1_url = $normalize_url($_POST['landing_photo_1_url'] ?? '');
    $landing_photo_2_url = $normalize_url($_POST['landing_photo_2_url'] ?? '');
    $landing_photo_3_url = $normalize_url($_POST['landing_photo_3_url'] ?? '');
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
            'has_input' => $has_input,
            'most_popular' => ($most_popular_key === ('special:' . $slot))
        ];
        if ($has_input) {
            $has_special_offer_input = true;
        }
    }

    if ($is_landing_template && $featured_photo_url !== '' && $landing_photo_1_url === '') {
        $landing_photo_1_url = $featured_photo_url;
    }
    if ($is_landing_template && empty($_FILES['landing_photo_1']['name']) && !empty($_FILES['p_featured_photo']['name'])) {
        $_FILES['landing_photo_1'] = $_FILES['p_featured_photo'];
    }

    $image_urls_to_validate = [$featured_photo_url, $landing_photo_1_url, $landing_photo_2_url, $landing_photo_3_url];
    foreach ($special_offer_inputs as $special_offer_input) {
        $image_urls_to_validate[] = $special_offer_input['photo_url'];
    }
    foreach ($image_urls_to_validate as $url_value) {
        if ($url_value !== '' && !is_valid_image_url($url_value) && !is_external_image_url($url_value)) {
            $valid = 0;
            $error_message .= 'يوجد رابط صورة غير صالح.<br>';
        }
    }

    if ($has_quantity_offer && $has_special_offer_input) {
        $valid = 0;
        $error_message .= 'لا يمكن الجمع بين عروض الكمية والعرض الخاص لنفس المنتج.<br>';
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
            if ($special_offer_input['photo_url'] === '' && empty($_FILES['special_offer_photo_' . $slot]['name'])) {
                $valid = 0;
                $error_message .= 'صورة العرض ' . $slot . ' مطلوبة.<br>';
            }
        }
    }

    if (!$is_landing_template && $featured_photo_url === '' && empty($_FILES['p_featured_photo']['name'])) {
        $valid = 0;
        $error_message .= 'الصورة الرئيسية مطلوبة (ملف أو رابط).<br>';
    }

    if ($valid == 1) {
        $seed = time() . '-' . mt_rand(100, 999);
        $final_name_featured = '';
        $landing_photo_1 = '';
        $landing_photo_2 = '';
        $landing_photo_3 = '';
        $special_offers_to_save = [];

        if ($is_landing_template) {
            if ($landing_photo_1_url !== '') {
                $landing_photo_1 = $landing_photo_1_url;
            } else {
                list($ok_l1, $landing_photo_1) = store_image_input('landing_photo_1', 'landing_photo_1_url', 'landing-1-' . $seed, '../assets/uploads', $error_message, false);
                if (!$ok_l1) {
                    $valid = 0;
                }
            }

            if ($landing_photo_2_url !== '') {
                $landing_photo_2 = $landing_photo_2_url;
            } else {
                list($ok_l2, $landing_photo_2) = store_image_input('landing_photo_2', 'landing_photo_2_url', 'landing-2-' . $seed, '../assets/uploads', $error_message, false);
                if (!$ok_l2) {
                    $valid = 0;
                }
            }

            if ($landing_photo_3_url !== '') {
                $landing_photo_3 = $landing_photo_3_url;
            } else {
                list($ok_l3, $landing_photo_3) = store_image_input('landing_photo_3', 'landing_photo_3_url', 'landing-3-' . $seed, '../assets/uploads', $error_message, false);
                if (!$ok_l3) {
                    $valid = 0;
                }
            }

            $final_name_featured = $landing_photo_1;
        } else {
            if ($featured_photo_url !== '') {
                $final_name_featured = $featured_photo_url;
            } else {
                list($ok_featured, $final_name_featured) = store_image_input('p_featured_photo', 'p_featured_photo_url', 'product-featured-' . $seed, '../assets/uploads', $error_message, true);
                if (!$ok_featured || $final_name_featured === '') {
                    $valid = 0;
                }
            }
        }

        if ($valid == 1 && $has_special_offer_input) {
            foreach ($special_offer_slots as $slot) {
                $special_offer_input = $special_offer_inputs[$slot];
                if (!$special_offer_input['has_input']) {
                    continue;
                }

                $special_offer_photo = '';
                if ($special_offer_input['photo_url'] !== '') {
                    $special_offer_photo = $special_offer_input['photo_url'];
                } else {
                    list($ok_special_offer_photo, $special_offer_photo) = store_image_input(
                        'special_offer_photo_' . $slot,
                        'special_offer_photo_url_' . $slot,
                        'offer-special-' . $slot . '-' . $seed,
                        '../assets/uploads',
                        $error_message,
                        true
                    );
                    if (!$ok_special_offer_photo || $special_offer_photo === '') {
                        $valid = 0;
                        continue;
                    }
                }

                $special_offers_to_save[$slot] = [
                    'price' => floatval(str_replace(',', '.', $special_offer_input['price_raw'])),
                    'description' => $special_offer_input['description'],
                    'photo' => $special_offer_photo
                ];
            }
        }

        if ($valid == 1) {
            $statement = $dbRepo->prepare("INSERT INTO tbl_product (
                p_name,
                p_old_price,
                p_current_price,
                purchase_price,
                p_qty,
                p_featured_photo,
                p_description,
                more_description,
                p_announcement,
                p_is_featured,
                p_is_active,
                ecat_id,
                product_template,
                p_delivery_mode,
                p_delivery_company_id,
                landing_photo_1,
                landing_photo_2,
                landing_photo_3
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $statement->execute([
                $_POST['p_name'],
                $_POST['p_old_price'],
                $_POST['p_current_price'],
                $_POST['purchase_price'] ?? 0.00,
                $_POST['p_qty'],
                $final_name_featured,
                $_POST['p_description'],
                $_POST['more_description'],
                $_POST['p_announcement'] ?? '',
                $_POST['p_is_featured'],
                $_POST['p_is_active'],
                $product_ecat_id,
                $product_template,
                $product_delivery_mode,
                $product_delivery_company_id > 0 ? $product_delivery_company_id : null,
                $landing_photo_1,
                $landing_photo_2,
                $landing_photo_3
            ]);
            $p_id = $dbRepo->lastInsertId();

            if ($has_quantity_offer) {
                foreach ($offer_inputs as $qty => $price_raw) {
                    $price_raw = trim((string)$price_raw);
                    if ($price_raw === '') {
                        continue;
                    }
                    $price_value = floatval(str_replace(',', '.', $price_raw));
                    if ($price_value <= 0) {
                        continue;
                    }
                    $is_most_popular = ($most_popular_key === ('quantity:' . (int)$qty)) ? 1 : 0;
                    $dbRepo->prepare("INSERT INTO tbl_product_offer (p_id, offer_qty, offer_unit_price, offer_type, is_most_popular, is_active, sort_order) VALUES (?, ?, ?, 'quantity', ?, 1, ?)")
                        ->execute([$p_id, (int)$qty, $price_value, $is_most_popular, (int)$qty]);
                }
            } elseif ($has_special_offer_input) {
                foreach ($special_offers_to_save as $slot => $special_offer) {
                    $is_most_popular = ($most_popular_key === ('special:' . (int)$slot)) ? 1 : 0;
                    $dbRepo->prepare("INSERT INTO tbl_product_offer (p_id, offer_qty, offer_unit_price, offer_type, offer_description, offer_photo, is_most_popular, is_active, sort_order) VALUES (?, 1, ?, 'special', ?, ?, ?, 1, ?)")
                        ->execute([$p_id, $special_offer['price'], $special_offer['description'], $special_offer['photo'], $is_most_popular, (int)$slot]);
                }
            }

            if (isset($_POST['size']) && is_array($_POST['size'])) {
                foreach ($_POST['size'] as $value) {
                    $dbRepo->prepare("INSERT INTO tbl_product_size (size_id, p_id) VALUES (?, ?)")
                        ->execute([$value, $p_id]);
                }
            }

            if (isset($_POST['pixel']) && is_array($_POST['pixel'])) {
                foreach ($_POST['pixel'] as $value) {
                    $dbRepo->prepare("INSERT INTO tbl_product_pixel (pixel_id, product_id) VALUES (?, ?)")
                        ->execute([$value, $p_id]);
                }
            }

            $selected_colors = [];
            if (isset($_POST['color']) && is_array($_POST['color'])) {
                $selected_colors = array_values(array_unique(array_map('intval', $_POST['color'])));
                foreach ($selected_colors as $color_id_upload) {
                    if ($color_id_upload <= 0) {
                        continue;
                    }
                    $dbRepo->prepare("INSERT INTO tbl_product_color (color_id, p_id) SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tbl_product_color WHERE color_id = ? AND p_id = ?)")
                        ->execute([$color_id_upload, $p_id, $color_id_upload, $p_id]);
                }
            }

            $color_photo_urls = $_POST['color_photo_urls'] ?? [];
            foreach ($selected_colors as $color_id_upload) {
                $url_value = $normalize_url($color_photo_urls[$color_id_upload] ?? '');
                if ($url_value === '') {
                    continue;
                }
                if (!is_valid_image_url($url_value) && !is_external_image_url($url_value)) {
                    $error_message .= "رابط صورة اللون غير صالح.<br>";
                    continue;
                }
                $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, color_id) VALUES (?, ?, ?)")
                    ->execute([$url_value, $p_id, (int)$color_id_upload]);
            }

            if (isset($_FILES['color_photos']['name']) && is_array($_FILES['color_photos']['name'])) {
                foreach ($_FILES['color_photos']['name'] as $color_id_upload => $photo_name) {
                    $existing_color_url = $normalize_url($color_photo_urls[$color_id_upload] ?? '');
                    if ($existing_color_url !== '') {
                        continue;
                    }
                    if (!empty($photo_name)) {
                        $upload_error = $_FILES['color_photos']['error'][$color_id_upload] ?? UPLOAD_ERR_OK;
                        if ($upload_error !== UPLOAD_ERR_OK) {
                            $error_message .= "فشل رفع صورة اللون (خطأ {$upload_error}).<br>";
                            continue;
                        }
                        $ext = pathinfo($photo_name, PATHINFO_EXTENSION);
                        $tmp_name = $_FILES['color_photos']['tmp_name'][$color_id_upload];
                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $target_base = 'product-color-' . $p_id . '-color-' . (int)$color_id_upload . '-' . mt_rand(10, 99);
                            list($upload_ok, $stored_color_photo) = store_uploaded_image_file($tmp_name, $photo_name, $target_base, '../assets/uploads', $error_message, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            if ($upload_ok && $stored_color_photo !== '') {
                                $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, color_id) VALUES (?, ?, ?)")
                                    ->execute([$stored_color_photo, $p_id, (int)$color_id_upload]);
                            } else {
                                $error_message .= "فشل رفع صورة اللون (خطأ النقل).<br>";
                            }
                        } else {
                            $error_message .= "امتداد صورة اللون غير مدعوم. استخدم JPG/PNG/GIF/WEBP.<br>";
                        }
                    }
                }
            }

            $additional_photo_urls_raw = trim((string)($_POST['additional_photo_urls'] ?? ''));
            if ($additional_photo_urls_raw !== '') {
                $parts = preg_split('/[\r\n,]+/', $additional_photo_urls_raw);
                foreach ($parts as $url_part) {
                    $url_part = $normalize_url($url_part);
                    if ($url_part === '') {
                        continue;
                    }
                    if (!is_valid_image_url($url_part) && !is_external_image_url($url_part)) {
                        $error_message .= "رابط صورة إضافية غير صالح.<br>";
                        continue;
                    }
                    $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, is_additional) VALUES (?, ?, 1)")
                        ->execute([$url_part, $p_id]);
                }
            }

            if (isset($_FILES['additional_photos']) && is_array($_FILES['additional_photos']['name'])) {
                foreach ($_FILES['additional_photos']['name'] as $key => $photo_name) {
                    if (!empty($photo_name)) {
                        $upload_error = $_FILES['additional_photos']['error'][$key] ?? UPLOAD_ERR_OK;
                        if ($upload_error !== UPLOAD_ERR_OK) {
                            $error_message .= "فشل رفع الصورة الإضافية (رمز الخطأ " . $upload_error . ").<br>";
                            continue;
                        }

                        $ext = pathinfo($photo_name, PATHINFO_EXTENSION);
                        $tmp_name = $_FILES['additional_photos']['tmp_name'][$key];
                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $target_base = "product-additional-" . $p_id . "-" . time() . "-" . $key;
                            list($upload_ok, $stored_additional_photo) = store_uploaded_image_file($tmp_name, $photo_name, $target_base, '../assets/uploads', $error_message, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            if ($upload_ok && $stored_additional_photo !== '') {
                                $dbRepo->prepare("INSERT INTO tbl_product_photo (photo, p_id, is_additional) VALUES (?, ?, 1)")
                                    ->execute([$stored_additional_photo, $p_id]);
                            } else {
                                $error_message .= "فشل حفظ الصورة الإضافية بعد الرفع.<br>";
                            }
                        } else {
                            $error_message .= "نوع الصورة الإضافية غير مدعوم. استخدم JPG أو PNG أو GIF أو WEBP.<br>";
                        }
                    }
                }
            }

            require_once('inc/stock_functions.php');
            stock_sync_variants($pdo, $p_id);

            require_once('inc/employee_functions.php');
            if (function_exists('employee_save_product_assignment')) {
                $exc_enabled = (int)($_POST['exc_is_enabled'] ?? 0);
                $exc_emp_id = (int)($_POST['exc_employee_id'] ?? 0);
                $exc_mode = trim((string)($_POST['exc_delivery_mode'] ?? 'queue'));
                employee_save_product_assignment($pdo, (int)$p_id, $exc_enabled, $exc_emp_id, $exc_mode);
            }

            $success_message = 'تم إضافة المنتج بنجاح';
        }
    }
}

require_once('inc/employee_functions.php');
$active_employees_for_assign = function_exists('employee_get_all') ? employee_get_all($pdo, true) : [];

$product_template = $_POST['product_template'] ?? 'landing_page.php';
$posted_delivery_mode = normalize_product_delivery_mode($_POST['p_delivery_mode'] ?? 'home_office');
$featured_preview_photo = '';
$landing_photo_1 = '';
$landing_photo_2 = '';
$landing_photo_3 = '';
$featured_url_value = $_POST['p_featured_photo_url'] ?? '';
$landing_1_url_value = $_POST['landing_photo_1_url'] ?? '';
$landing_2_url_value = $_POST['landing_photo_2_url'] ?? '';
$landing_3_url_value = $_POST['landing_photo_3_url'] ?? '';
$offer_prices = [
    1 => $_POST['offer_price_1'] ?? '',
    2 => $_POST['offer_price_2'] ?? '',
    3 => $_POST['offer_price_3'] ?? ''
];
$most_popular_offer = $_POST['most_popular_offer'] ?? '';
$special_offers_for_form = [];
foreach ($special_offer_slots as $slot) {
    $special_offers_for_form[$slot] = [
        'price' => $_POST['special_offer_price_' . $slot] ?? '',
        'description' => $_POST['special_offer_description_' . $slot] ?? '',
        'photo' => '',
        'photo_url' => $_POST['special_offer_photo_url_' . $slot] ?? ''
    ];
}
$selected_sizes_for_form = array_map('intval', (array)($_POST['size'] ?? []));
$selected_colors_for_form = array_map('intval', (array)($_POST['color'] ?? []));
$posted_color_urls = $_POST['color_photo_urls'] ?? [];
$color_photo_map = [];
$additional_photos = [];
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إضافة منتج</h1>
    </div>
    <div class="content-header-right">
        <button type="button" class="btn btn-info" id="previewProductBtnTop"><i class="fa fa-eye"></i> معاينة</button>
        <a href="product.php" class="btn btn-primary btn-sm">كل المنتجات</a>
    </div>
</section>

<section class="content admin-product-page product-add-page">
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

            <form class="form-horizontal admin-product-form" method="post" enctype="multipart/form-data">
                <!-- Stepper -->
                <div class="product-stepper">
                    <div class="step active" data-step="1"><span class="step-num">1</span> الأساسيات</div>
                    <div class="step" data-step="2"><span class="step-num">2</span> العروض</div>
                    <div class="step" data-step="3"><span class="step-num">3</span> الوصف والصور</div>
                    <div class="step" data-step="4"><span class="step-num">4</span> الخيارات والحالة</div>
                </div>

                <!-- Step 1: Basic Info -->
                <div class="step-content" data-step="1">
                    <div class="box box-info admin-product-card">
                        <div class="box-body">
                            <div class="form-group">
                                <label class="col-sm-3 control-label">القالب</label>
                                <div class="col-sm-5">
                                    <select name="product_template" id="product_template" class="form-control">
                                        <option value="landing_page.php" <?= ($product_template === 'landing_page.php') ? 'selected' : '' ?>>صفحة هبوط</option>
                                        <option value="landing_page_2.php" <?= ($product_template === 'landing_page_2.php') ? 'selected' : '' ?>>صفحة هبوط 2</option>
                                        <option value="buy-now.php" <?= ($product_template === 'buy-now.php') ? 'selected' : '' ?>>عادي</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">نوع التوصيل</label>
                                <div class="col-sm-5">
                                    <select name="p_delivery_mode" class="form-control">
                                        <option value="free" <?= $posted_delivery_mode === 'free' ? 'selected' : '' ?>>توصيل مجاني</option>
                                        <option value="home_only" <?= $posted_delivery_mode === 'home_only' ? 'selected' : '' ?>>المنزل فقط</option>
                                        <option value="home_office" <?= $posted_delivery_mode === 'home_office' ? 'selected' : '' ?>>المنزل + المكتب</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">شركة التوصيل</label>
                                <div class="col-sm-5">
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

                            <input type="hidden" name="ecat_id" value="0">

                            <div class="form-group">
                                <label class="col-sm-3 control-label">اسم المنتج</label>
                                <div class="col-sm-5"><input type="text" name="p_name" class="form-control" value="<?= htmlspecialchars($_POST['p_name'] ?? '') ?>"></div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">إعلان مختصر</label>
                                <div class="col-sm-5"><input type="text" name="p_announcement" class="form-control" value="<?= htmlspecialchars($_POST['p_announcement'] ?? '') ?>"></div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">السعر القديم</label>
                                <div class="col-sm-5"><input type="text" name="p_old_price" class="form-control" value="<?= htmlspecialchars($_POST['p_old_price'] ?? '') ?>"></div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">السعر الحالي</label>
                                <div class="col-sm-5"><input type="text" name="p_current_price" class="form-control" value="<?= htmlspecialchars($_POST['p_current_price'] ?? '') ?>"></div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">سعر الشراء</label>
                                <div class="col-sm-5"><input type="text" name="purchase_price" class="form-control" value="<?= htmlspecialchars($_POST['purchase_price'] ?? '0.00') ?>"></div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">الكمية</label>
                                <div class="col-sm-5"><input type="text" name="p_qty" class="form-control" value="<?= htmlspecialchars($_POST['p_qty'] ?? '') ?>"></div>
                            </div>
                        </div>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-primary step-next">التالي</button>
                    </div>
                </div>

                <!-- Step 2: Offers -->
                <div class="step-content" data-step="2" style="display:none;">
                    <div class="box box-info admin-product-card">
                        <div class="box-body">
                            <div class="form-group">
                                <label class="col-sm-3 control-label">أسعار عروض الكمية</label>
                                <div class="col-sm-5">
                                    <div style="display:flex;gap:10px;align-items:center;">
                                        <input type="radio" name="most_popular_offer" value="quantity:1" <?= ((string)$most_popular_offer === 'quantity:1') ? 'checked' : '' ?> title="الأكثر طلباً">
                                        <input type="number" name="offer_price_1" class="form-control" step="0.01" min="0" placeholder="سعر عرض 1" value="<?= htmlspecialchars((string)$offer_prices[1]) ?>">
                                    </div>
                                    <br>
                                    <div style="display:flex;gap:10px;align-items:center;">
                                        <input type="radio" name="most_popular_offer" value="quantity:2" <?= ((string)$most_popular_offer === 'quantity:2') ? 'checked' : '' ?> title="الأكثر طلباً">
                                        <input type="number" name="offer_price_2" class="form-control" step="0.01" min="0" placeholder="سعر عرض 2" value="<?= htmlspecialchars((string)$offer_prices[2]) ?>">
                                    </div>
                                    <br>
                                    <div style="display:flex;gap:10px;align-items:center;">
                                        <input type="radio" name="most_popular_offer" value="quantity:3" <?= ((string)$most_popular_offer === 'quantity:3') ? 'checked' : '' ?> title="الأكثر طلباً">
                                        <input type="number" name="offer_price_3" class="form-control" step="0.01" min="0" placeholder="سعر عرض 3" value="<?= htmlspecialchars((string)$offer_prices[3]) ?>">
                                    </div>
                                    <span class="help-block">ضع علامة "الأكثر طلباً" على عرض واحد (اختياري) ليظهر افتراضياً في صفحة الهبوط.</span>
                                    <span class="help-block">إذا استخدمت عروض الكمية، اترك العرض الخاص فارغًا.</span>
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
                                                    <input type="radio" name="most_popular_offer" value="special:<?= (int)$slot ?>" <?= ((string)$most_popular_offer === ('special:' . (int)$slot)) ? 'checked' : '' ?>>
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
                                    <span class="help-block">العرض الخاص له صورة ووصف وسعر، ولا يمكن جمعه مع عروض الكمية.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-default step-prev">السابق</button>
                        <button type="button" class="btn btn-primary step-next">التالي</button>
                    </div>
                </div>

                <!-- Step 3: Description & Images -->
                <div class="step-content" data-step="3" style="display:none;">
                    <div class="box box-info admin-product-card">
                        <div class="box-body">
                            <div class="form-group" id="description-section">
                                <label class="col-sm-3 control-label">الوصف</label>
                                <div class="col-sm-5"><textarea name="p_description" class="form-control" rows="4"><?= htmlspecialchars($_POST['p_description'] ?? '') ?></textarea></div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">وصف إضافي</label>
                                <div class="col-sm-5"><textarea name="more_description" class="form-control" rows="4"><?= htmlspecialchars($_POST['more_description'] ?? '') ?></textarea></div>
                            </div>

                            <div class="form-group" id="featured-photo-section">
                                <label class="col-sm-3 control-label">الصورة الرئيسية</label>
                                <div class="col-sm-5">
                                    <?php if (!empty($featured_preview_photo)): ?>
                                        <div class="admin-thumb-wrap"><img src="<?= htmlspecialchars(get_admin_image_url($featured_preview_photo), ENT_QUOTES, 'UTF-8') ?>" class="admin-thumb admin-thumb--lg"></div>
                                    <?php endif; ?>
                                    <input type="file" name="p_featured_photo" class="form-control">
                                    <br>
                                    <input type="text" name="p_featured_photo_url" class="form-control js-url-input" placeholder="أو رابط صورة" value="<?= htmlspecialchars($featured_url_value) ?>">
                                    <div class="admin-thumb-wrap js-url-preview-box" style="display:none;">
                                        <img src="" class="admin-thumb admin-thumb--sm js-url-preview-img" alt="معاينة الرابط">
                                    </div>
                                </div>
                            </div>

                            <div id="landing-photos-section">
                                <h4>صور صفحة الهبوط</h4>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">صورة صفحة الهبوط 1</label>
                                    <div class="col-sm-5">
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
                                    <div class="col-sm-5">
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
                                    <div class="col-sm-5">
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
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">إضافة صور إضافية جديدة</label>
                                <div class="col-sm-5">
                                    <input type="file" name="additional_photos[]" class="form-control" multiple accept="image/*">
                                    <br>
                                    <textarea name="additional_photo_urls" class="form-control" rows="4" placeholder="روابط الصور الإضافية، كل رابط في سطر"><?= htmlspecialchars($_POST['additional_photo_urls'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-default step-prev">السابق</button>
                        <button type="button" class="btn btn-primary step-next">التالي</button>
                    </div>
                </div>

                <!-- Step 4: Options & Status -->
                <div class="step-content" data-step="4" style="display:none;">
                    <div class="box box-info admin-product-card">
                        <div class="box-body">
                            <div class="form-group">
                                <label class="col-sm-3 control-label">المقاسات</label>
                                <div class="col-sm-5">
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
                                <div class="col-sm-5">
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
                                <div class="col-sm-5">
                                    <?php
                                    // Checkbox list instead of a native <select multiple>: select2 isn't
                                    // initialised on this field, so a native multi-select forced Ctrl+click
                                    // and felt like "only one pixel". Checkboxes make picking several obvious.
                                    $stmt = $dbRepo->prepare("SELECT * FROM tbl_pixel ORDER BY pixel_name ASC");
                                    $stmt->execute();
                                    $__all_pixels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $__selected_pixels = array_map('intval', (array)($_POST['pixel'] ?? []));
                                    if (empty($__all_pixels)):
                                    ?>
                                        <p class="text-muted" style="margin:6px 0;">لا توجد بكسلات بعد. أضِفها من <a href="pixel-add.php" target="_blank">صفحة البكسلات</a>.</p>
                                    <?php else: ?>
                                        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:10px;max-height:220px;overflow:auto;">
                                        <?php foreach ($__all_pixels as $row):
                                            $checked = in_array((int)$row['id'], $__selected_pixels, true) ? 'checked' : ''; ?>
                                            <label style="display:block;padding:5px 4px;cursor:pointer;font-weight:normal;">
                                                <input type="checkbox" name="pixel[]" value="<?= (int)$row['id']; ?>" <?= $checked; ?> style="margin-left:6px;">
                                                <?= htmlspecialchars($row['pixel_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                <small style="color:#6b7280;">(<?= htmlspecialchars($row['pixel_network'], ENT_QUOTES, 'UTF-8'); ?><?= $row['pixel_id'] !== '' ? ' — ' . htmlspecialchars($row['pixel_id'], ENT_QUOTES, 'UTF-8') : ''; ?>)</small>
                                            </label>
                                        <?php endforeach; ?>
                                        </div>
                                        <small style="color:#6b7280;margin-top:4px;display:block;">اختر بكسلًا واحدًا أو أكثر لهذا المنتج.</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">منتج مميز؟</label>
                                <div class="col-sm-5">
                                    <select name="p_is_featured" class="form-control">
                                        <option value="0" <?= ((string)($_POST['p_is_featured'] ?? '0') === '0') ? 'selected' : '' ?>>لا</option>
                                        <option value="1" <?= ((string)($_POST['p_is_featured'] ?? '') === '1') ? 'selected' : '' ?>>نعم</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">نشط؟</label>
                                <div class="col-sm-5">
                                    <select name="p_is_active" class="form-control">
                                        <option value="1" <?= ((string)($_POST['p_is_active'] ?? '1') === '1') ? 'selected' : '' ?>>نعم</option>
                                        <option value="0" <?= ((string)($_POST['p_is_active'] ?? '') === '0') ? 'selected' : '' ?>>لا</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box box-info admin-product-card" id="exclusive-assignment-section" style="border-top: 3px solid #00c0ef;">
                        <div class="box-header with-border">
                            <h3 class="box-title" style="font-weight: 700; color: #00c0ef;"><i class="fa fa-user-secret"></i> التخصيص الحصري للمنتج (Exclusive Assignment)</h3>
                            <p class="help-block" style="margin-bottom:0;">عند تفعيل التخصيص الحصري، سيتم إسناد جميع طلبات هذا المنتج حصرياً لموظف محدد، ولن تدخل في طابور التوزيع العام (WRR).</p>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label class="col-sm-3 control-label">تفعيل التخصيص الحصري</label>
                                <div class="col-sm-5">
                                    <select name="exc_is_enabled" id="exc_is_enabled" class="form-control">
                                        <option value="0" <?= ((string)($_POST['exc_is_enabled'] ?? '0') === '0') ? 'selected' : '' ?>>معطل (OFF - الافتراضي)</option>
                                        <option value="1" <?= ((string)($_POST['exc_is_enabled'] ?? '') === '1') ? 'selected' : '' ?>>مفعل (ON)</option>
                                    </select>
                                </div>
                            </div>
                            <div id="exc_fields_wrapper" style="<?= ((string)($_POST['exc_is_enabled'] ?? '0') === '1') ? '' : 'display: none;' ?>">
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">الموظف المخصص (Assigned Employee)</label>
                                    <div class="col-sm-5">
                                        <select name="exc_employee_id" class="form-control select2" style="width:100%;">
                                            <option value="0">-- اختر الموظف المخصص --</option>
                                            <?php foreach ($active_employees_for_assign as $emp_assign): ?>
                                                <?php $selected_emp = ((int)($emp_assign['id']) === (int)($_POST['exc_employee_id'] ?? 0)) ? 'selected' : ''; ?>
                                                <option value="<?= (int)$emp_assign['id'] ?>" <?= $selected_emp ?>><?= htmlspecialchars($emp_assign['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">طريقة وصول الطلبات (Delivery Mode)</label>
                                    <div class="col-sm-5">
                                        <div class="radio" style="margin-bottom: 10px;">
                                            <label style="font-weight: 600;">
                                                <input type="radio" name="exc_delivery_mode" value="queue" <?= ((string)($_POST['exc_delivery_mode'] ?? 'queue') === 'queue') ? 'checked' : '' ?>>
                                                <span class="label label-warning" style="padding: 4px 8px; margin-left: 5px;">Employee Queue</span>
                                                دخول الطلبات إلى طابور الانتظار الخاص بالموظف (بحالة Waiting) ليقوم بقبولها بنفسه.
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label style="font-weight: 600;">
                                                <input type="radio" name="exc_delivery_mode" value="direct" <?= ((string)($_POST['exc_delivery_mode'] ?? '') === 'direct') ? 'checked' : '' ?>>
                                                <span class="label label-success" style="padding: 4px 8px; margin-left: 5px;">Direct Assignment</span>
                                                إسناد الطلبات مباشرة إلى مساحة العمل الخاصة بالموظف (بحالة Active).
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-default step-prev">السابق</button>
                        <button type="button" class="btn btn-info" id="previewProductBtn"><i class="fa fa-eye"></i> معاينة</button>
                        <button type="submit" class="btn btn-success" name="form1">إضافة المنتج</button>
                    </div>
                </div>
            </form>
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
            wrapper.append('<div class="form-group"><label class="col-sm-3 control-label">صورة اللون: ' + colorName + '</label><div class="col-sm-5">' + existingImg + '<input type="file" name="color_photos[' + colorId + ']" class="form-control"><br><input type="text" name="color_photo_urls[' + colorId + ']" class="form-control js-url-input" placeholder="أو رابط صورة" value="' + safeAttr(defaultUrl) + '">' + previewBox + '</div></div>');
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currentStep = 1;
    var totalSteps = 4;

    function applyTemplateFields() {        var template = document.getElementById('product_template');
        if (!template) return;
        var isLanding = template.value === 'landing_page.php' || template.value === 'landing_page_2.php';
        toggleEl('landing-photos-section', isLanding);
        toggleEl('description-section', !isLanding);
        toggleEl('featured-photo-section', true);
        toggleEl('additional-photos-section', true);
    }

    function toggleEl(id, show) { global $dbRepo;
    global $dbRepo;

        var el = document.getElementById(id);
        if (el) el.style.display = show ? '' : 'none';
    }

    function showStep(step) { global $dbRepo;
    global $dbRepo;

        document.querySelectorAll('.step-content').forEach(function(el) {
            el.style.display = 'none';
        });
        var content = document.querySelector('.step-content[data-step="' + step + '"]');
        if (content) content.style.display = '';
        document.querySelectorAll('.product-stepper .step').forEach(function(el) {
            var s = parseInt(el.getAttribute('data-step'));
            el.classList.toggle('active', s === step);
            el.classList.toggle('done', s < step);
        });
        currentStep = step;
        if (step === 3) applyTemplateFields();
        if (step === 4 && typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            try { document.querySelectorAll('.step-content[data-step="4"] .select2').forEach(function(el) { jQuery(el).select2(); }); } catch(e) {}
        }
        window.scrollTo(0, 0);
    }

    document.querySelector('.product-stepper').addEventListener('click', function(e) {
        var stepEl = e.target.closest('.step');
        if (!stepEl || !stepEl.classList.contains('done')) return;
        var step = parseInt(stepEl.getAttribute('data-step'));
        if (step > 0) showStep(step);
    });

    document.querySelectorAll('.step-next').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (currentStep < totalSteps) showStep(currentStep + 1);
        });
    });

    document.querySelectorAll('.step-prev').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (currentStep > 1) showStep(currentStep - 1);
        });
    });

    document.getElementById('product_template').addEventListener('change', applyTemplateFields);

    function openPreviewModal() {        var name = document.querySelector('input[name="p_name"]').value || 'اسم المنتج';
        var price = document.querySelector('input[name="p_current_price"]').value || '0';
        var oldPrice = document.querySelector('input[name="p_old_price"]').value || '0';
        var announcement = document.querySelector('input[name="p_announcement"]').value || '';
        var template = document.querySelector('select[name="product_template"]').value || 'buy-now.php';
        var photoInput = document.querySelector('input[name="p_featured_photo"]');
        var photoUrl = '';
        if (photoInput && photoInput.files && photoInput.files[0]) {
            photoUrl = URL.createObjectURL(photoInput.files[0]);
        }
        var templateLabel = {'landing_page.php': 'صفحة هبوط', 'landing_page_2.php': 'صفحة هبوط 2', 'buy-now.php': 'عادي'}[template] || template;
        var saving = 0;
        if (parseFloat(oldPrice) > parseFloat(price) && parseFloat(price) > 0) {
            saving = Math.round(((parseFloat(oldPrice) - parseFloat(price)) / parseFloat(oldPrice)) * 100);
        }

        var modal = document.createElement('div');
        modal.id = 'previewModal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;direction:rtl;font-family:Cairo,Tajawal,sans-serif;';
        modal.innerHTML = '<div style="background:#fff;border-radius:16px;max-width:420px;width:90%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;">' +
            '<button onclick="this.closest(\'#previewModal\').remove()" style="position:absolute;top:12px;left:12px;z-index:2;background:#f1f5f9;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:18px;display:grid;place-items:center;">&times;</button>' +
            '<div style="background:#f8fafc;padding:10px 16px;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;">معاينة المنتج — ' + templateLabel + '</div>' +
            '<div style="padding:20px;text-align:center;">' +
                (photoUrl ? '<img src="' + photoUrl + '" style="width:100%;max-height:240px;object-fit:contain;border-radius:12px;background:#f1f5f9;margin-bottom:16px;">' : '<div style="width:100%;height:160px;background:#f1f5f9;border-radius:12px;display:grid;place-items:center;color:#94a3b8;margin-bottom:16px;font-size:14px;">لا توجد صورة</div>') +
                (saving > 0 ? '<span style="display:inline-block;background:#ef4444;color:#fff;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;margin-bottom:10px;">خصم ' + saving + '%</span>' : '') +
                '<h3 style="margin:0 0 8px;font-size:17px;font-weight:800;color:#1e293b;">' + name + '</h3>' +
                (announcement ? '<p style="margin:0 0 12px;font-size:13px;color:#64748b;">' + announcement + '</p>' : '') +
                '<div style="margin:12px 0;">' +
                    '<strong style="font-size:20px;color:#0f9488;">' + Number(price).toLocaleString('fr-DZ') + ' دج</strong>' +
                    (parseFloat(oldPrice) > parseFloat(price) ? '<del style="margin-right:8px;color:#94a3b8;font-size:14px;">' + Number(oldPrice).toLocaleString('fr-DZ') + ' دج</del>' : '') +
                '</div>' +
                '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px;margin-top:12px;font-size:13px;color:#166534;">' +
                    '<i class="fa fa-check-circle"></i> المنتج جاهز للنشر — اضغط "إضافة المنتج" للحفظ' +
                '</div>' +
            '</div>' +
        '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
    }

    document.getElementById('previewProductBtn').addEventListener('click', openPreviewModal);
    document.getElementById('previewProductBtnTop').addEventListener('click', openPreviewModal);
});
</script>

<?php require_once('footer.php'); ?>
