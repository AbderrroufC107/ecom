<?php require_once('header.php'); ?>

<?php
try {
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS facebook_pixel_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS tiktok_pixel_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS snapchat_pixel_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS google_analytics_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_bot_token varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_chat_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_orders_enabled TINYINT(1) NOT NULL DEFAULT 1");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_incomplete_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_incomplete_chat_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_incomplete_bot_token varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_order_status_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_order_status_chat_id varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS telegram_order_status_bot_token varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_url TEXT NULL");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_method varchar(10) NOT NULL DEFAULT 'POST'");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_sender varchar(120) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_token varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_headers TEXT NULL");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_body_template TEXT NULL");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS sms_gateway_success_keyword varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS ecotrack_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS ecotrack_api_token TEXT NULL");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS ecotrack_base_url varchar(255) NOT NULL DEFAULT ''");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS zrexpress_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS zrexpress_token TEXT NULL");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS zrexpress_key TEXT NULL");
    $dbRepo->executeCommand("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS zrexpress_base_url varchar(255) NOT NULL DEFAULT ''");
} catch (PDOException $e) {
    // ignore if columns already exist
}

admin_ensure_sms_template_table($pdo);
admin_ensure_sms_automation_table($pdo);
admin_ensure_ecotrack_setting_columns($pdo);
if (function_exists('admin_ensure_zrexpress_setting_columns')) {
    admin_ensure_zrexpress_setting_columns($pdo);
}
if (function_exists('admin_ensure_telegram_order_status_columns')) {
    admin_ensure_telegram_order_status_columns($pdo);
}
?>

<?php
if (!function_exists('update_settings_image_by_url')) {
    function update_settings_image_by_url($pdo, $column, $url, &$error_message)
    { global $dbRepo;
    global $dbRepo;

        $url = store_external_image_url($url, $error_message);
        if ($url === '') {
            return false;
        }

        if (!is_valid_image_url($url) && !is_external_image_url($url)) {
            $error_message .= 'Image URL is not valid.<br>';
            return false;
        }

        $statement = $dbRepo->prepare("SELECT $column FROM tbl_settings WHERE id=1");
        $statement->execute();
        $current_value = $statement->fetchColumn();
        delete_local_image_file($current_value, '../assets/uploads');

        $statement = $dbRepo->prepare("UPDATE tbl_settings SET $column=? WHERE id=1");
        $statement->execute(array($url));

        return true;
    }
}

if (!function_exists('clear_settings_image_column')) {
    function clear_settings_image_column($pdo, $column)
    { global $dbRepo;
    global $dbRepo;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$column)) {
            return false;
        }

        $statement = $dbRepo->prepare("SELECT $column FROM tbl_settings WHERE id=1");
        $statement->execute();
        $current_value = $statement->fetchColumn();
        delete_local_image_file($current_value, '../assets/uploads');

        $statement = $dbRepo->prepare("UPDATE tbl_settings SET $column='' WHERE id=1");
        $statement->execute();
        return true;
    }
}

if (!function_exists('store_settings_uploaded_photo')) {
    function store_settings_uploaded_photo($path, $path_tmp, $target_base, &$error_message, $allowed_ext = null)
    { global $dbRepo;
    global $dbRepo;

        if ($allowed_ext === null) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        }
        return store_uploaded_image_file($path_tmp, $path, $target_base, '../assets/uploads', $error_message, $allowed_ext);
    }
}

if(isset($_POST['form1']) && !empty($_POST['remove_photo_logo'])) {
    if (clear_settings_image_column($pdo, 'logo')) {
        $success_message = 'Logo is deleted successfully.';
    }
    unset($_POST['form1']);
}

if(isset($_POST['form2']) && !empty($_POST['remove_photo_favicon'])) {
    if (clear_settings_image_column($pdo, 'favicon')) {
        $success_message = 'Favicon is deleted successfully.';
    }
    unset($_POST['form2']);
}

if(isset($_POST['form6_7']) && !empty($_POST['remove_cta_photo'])) {
    $valid = 1;
    if(empty($_POST['cta_title'])) {
        $valid = 0;
        $error_message .= 'Call to Action Title can not be empty<br>';
    }
    if(empty($_POST['cta_content'])) {
        $valid = 0;
        $error_message .= 'Call to Action Content can not be empty<br>';
    }
    if(empty($_POST['cta_read_more_text'])) {
        $valid = 0;
        $error_message .= 'Call to Action Read More Text can not be empty<br>';
    }
    if(empty($_POST['cta_read_more_url'])) {
        $valid = 0;
        $error_message .= 'Call to Action Read More URL can not be empty<br>';
    }
    if ($valid == 1 && clear_settings_image_column($pdo, 'cta_photo')) {
        $statement = $dbRepo->prepare("UPDATE tbl_settings SET cta_title=?,cta_content=?,cta_read_more_text=?,cta_read_more_url=? WHERE id=1");
        $statement->execute(array($_POST['cta_title'],$_POST['cta_content'],$_POST['cta_read_more_text'],$_POST['cta_read_more_url']));
        $success_message = 'Call to Action Data is updated successfully.';
    }
    unset($_POST['form6_7']);
}

if(isset($_POST['form1']) && !empty($_POST['photo_logo_url'])) {
    if (update_settings_image_by_url($pdo, 'logo', $_POST['photo_logo_url'], $error_message)) {
        $success_message = 'Logo is updated successfully.';
    }
    unset($_POST['form1']);
}

if(isset($_POST['form2']) && !empty($_POST['photo_favicon_url'])) {
    if (update_settings_image_by_url($pdo, 'favicon', $_POST['photo_favicon_url'], $error_message)) {
        $success_message = 'Favicon is updated successfully.';
    }
    unset($_POST['form2']);
}

if(isset($_POST['form6_7']) && !empty($_POST['cta_photo_url'])) {
    $valid = 1;
    if(empty($_POST['cta_title'])) {
        $valid = 0;
        $error_message .= 'Call to Action Title can not be empty<br>';
    }
    if(empty($_POST['cta_content'])) {
        $valid = 0;
        $error_message .= 'Call to Action Content can not be empty<br>';
    }
    if(empty($_POST['cta_read_more_text'])) {
        $valid = 0;
        $error_message .= 'Call to Action Read More Text can not be empty<br>';
    }
    if(empty($_POST['cta_read_more_url'])) {
        $valid = 0;
        $error_message .= 'Call to Action Read More URL can not be empty<br>';
    }

    $cta_url = store_external_image_url($_POST['cta_photo_url'] ?? '', $error_message);
    if ($valid == 1 && (!is_valid_image_url($cta_url) && !is_external_image_url($cta_url))) {
        $valid = 0;
        $error_message .= 'CTA image URL is not valid.<br>';
    }

    if ($valid == 1) {
        $statement = $dbRepo->prepare("SELECT cta_photo FROM tbl_settings WHERE id=1");
        $statement->execute();
        $current_cta = $statement->fetchColumn();
        delete_local_image_file($current_cta, '../assets/uploads');

        $statement = $dbRepo->prepare("UPDATE tbl_settings SET cta_title=?,cta_content=?,cta_read_more_text=?,cta_read_more_url=?,cta_photo=? WHERE id=1");
        $statement->execute(array($_POST['cta_title'],$_POST['cta_content'],$_POST['cta_read_more_text'],$_POST['cta_read_more_url'],$cta_url));
        if($valid == 1) {
            $success_message = 'Call to Action Data is updated successfully.';
        }
    }

    unset($_POST['form6_7']);
}

$banner_form_map = [
    'form7_1' => ['column' => 'banner_login', 'message' => 'Login Page Banner is updated successfully.'],
    'form7_2' => ['column' => 'banner_registration', 'message' => 'Registration Page Banner is updated successfully.'],
    'form7_3' => ['column' => 'banner_forget_password', 'message' => 'Forget Password Page Banner is updated successfully.'],
    'form7_4' => ['column' => 'banner_reset_password', 'message' => 'Reset Password Page Banner is updated successfully.'],
    'form7_6' => ['column' => 'banner_search', 'message' => 'Search Page Banner is updated successfully.'],
    'form7_7' => ['column' => 'banner_cart', 'message' => 'Cart Page Banner is updated successfully.'],
    'form7_8' => ['column' => 'banner_checkout', 'message' => 'Checkout Page Banner is updated successfully.'],
    'form7_9' => ['column' => 'banner_product_category', 'message' => 'Product Category Page Banner is updated successfully.']
];

$banner_delete_map = [
    'form7_1' => ['column' => 'banner_login', 'message' => 'Login Page Banner is deleted successfully.'],
    'form7_2' => ['column' => 'banner_registration', 'message' => 'Registration Page Banner is deleted successfully.'],
    'form7_3' => ['column' => 'banner_forget_password', 'message' => 'Forget Password Page Banner is deleted successfully.'],
    'form7_4' => ['column' => 'banner_reset_password', 'message' => 'Reset Password Page Banner is deleted successfully.'],
    'form7_6' => ['column' => 'banner_search', 'message' => 'Search Page Banner is deleted successfully.'],
    'form7_7' => ['column' => 'banner_cart', 'message' => 'Cart Page Banner is deleted successfully.'],
    'form7_8' => ['column' => 'banner_checkout', 'message' => 'Checkout Page Banner is deleted successfully.'],
    'form7_9' => ['column' => 'banner_product_category', 'message' => 'Product Category Page Banner is deleted successfully.']
];

foreach ($banner_delete_map as $form_key => $banner_config) {
    if(isset($_POST[$form_key]) && !empty($_POST['remove_photo'])) {
        if (clear_settings_image_column($pdo, $banner_config['column'])) {
            $success_message = $banner_config['message'];
        }
        unset($_POST[$form_key]);
    }
}

foreach ($banner_form_map as $form_key => $banner_config) {
    if(isset($_POST[$form_key]) && !empty($_POST['photo_url'])) {
        if (update_settings_image_by_url($pdo, $banner_config['column'], $_POST['photo_url'], $error_message)) {
            $success_message = $banner_config['message'];
        }
        unset($_POST[$form_key]);
    }
}

//Change Logo
if(isset($_POST['form1'])) {
    $valid = 1;

    $path = $_FILES['photo_logo']['name'];
    $path_tmp = $_FILES['photo_logo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $logo = $row['logo'];
            delete_local_image_file($logo, '../assets/uploads');
        }

        // updating the data
        $final_name = 'logo'.'.'.$ext;
        list($upload_ok, $stored_logo) = store_settings_uploaded_photo($path, $path_tmp, 'logo', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_logo === '') {
            $valid = 0;
        } else {
            $final_name = $stored_logo;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET logo=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        $success_message = 'Logo is updated successfully.';
        
    }
}
// Change Favicon
if(isset($_POST['form2'])) {
    $valid = 1;

    $path = $_FILES['photo_favicon']['name'];
    $path_tmp = $_FILES['photo_favicon']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $favicon = $row['favicon'];
            delete_local_image_file($favicon, '../assets/uploads');
        }

        // updating the data
        $final_name = 'favicon'.'.'.$ext;
        list($upload_ok, $stored_favicon) = store_settings_uploaded_photo($path, $path_tmp, 'favicon', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_favicon === '') {
            $valid = 0;
        } else {
            $final_name = $stored_favicon;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET favicon=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        $success_message = 'Favicon is updated successfully.';
        
    }
}
//Footer & Contact us page
if(isset($_POST['form3'])) {
    
    // updating the database
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET newsletter_on_off=?, footer_copyright=?, contact_address=?, contact_email=?, contact_phone=?, contact_map_iframe=? WHERE id=1");
    $statement->execute(array($_POST['newsletter_on_off'],$_POST['footer_copyright'],$_POST['contact_address'],$_POST['contact_email'],$_POST['contact_phone'],$_POST['contact_map_iframe']));

    $success_message = 'General content settings is updated successfully.';
    
}
//Email Settings
if(isset($_POST['form4'])) {
    // updating the database
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET receive_email=?, receive_email_subject=?,receive_email_thank_you_message=?, forget_password_message=? WHERE id=1");
    $statement->execute(array($_POST['receive_email'],$_POST['receive_email_subject'],$_POST['receive_email_thank_you_message'],$_POST['forget_password_message']));

    $success_message = 'Contact form settings information is updated successfully.';
}


// Pixel Settings
if(isset($_POST['form_pixels'])) {
    $facebook_pixel_id = trim($_POST['facebook_pixel_id'] ?? '');
    $tiktok_pixel_id = trim($_POST['tiktok_pixel_id'] ?? '');
    $snapchat_pixel_id = trim($_POST['snapchat_pixel_id'] ?? '');
    $google_analytics_id = trim($_POST['google_analytics_id'] ?? '');
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET facebook_pixel_id=?, tiktok_pixel_id=?, snapchat_pixel_id=?, google_analytics_id=? WHERE id=1");
    $statement->execute(array($facebook_pixel_id, $tiktok_pixel_id, $snapchat_pixel_id, $google_analytics_id));

    $success_message = 'Pixel settings updated successfully.';
}

// Telegram Settings
if(isset($_POST['form_telegram'])) {
    $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
    $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
    $telegram_incomplete_chat_id = trim($_POST['telegram_incomplete_chat_id'] ?? '');
    $telegram_incomplete_bot_token = trim($_POST['telegram_incomplete_bot_token'] ?? '');
    $telegram_order_status_chat_id = trim($_POST['telegram_order_status_chat_id'] ?? '');
    $telegram_order_status_bot_token = trim($_POST['telegram_order_status_bot_token'] ?? '');
    $telegram_orders_enabled = isset($_POST['telegram_orders_enabled']) ? 1 : 0;
    $telegram_incomplete_enabled = isset($_POST['telegram_incomplete_enabled']) ? 1 : 0;
    $telegram_order_status_enabled = isset($_POST['telegram_order_status_enabled']) ? 1 : 0;

    $statement = $dbRepo->prepare("UPDATE tbl_settings SET telegram_bot_token=?, telegram_chat_id=?, telegram_orders_enabled=?, telegram_incomplete_enabled=?, telegram_incomplete_chat_id=?, telegram_incomplete_bot_token=?, telegram_order_status_enabled=?, telegram_order_status_chat_id=?, telegram_order_status_bot_token=? WHERE id=1");
    $statement->execute(array($telegram_bot_token, $telegram_chat_id, $telegram_orders_enabled, $telegram_incomplete_enabled, $telegram_incomplete_chat_id, $telegram_incomplete_bot_token, $telegram_order_status_enabled, $telegram_order_status_chat_id, $telegram_order_status_bot_token));

    $success_message = 'Telegram settings updated successfully.';
}

if(isset($_POST['form_sms_gateway'])) {
    $sms_gateway_enabled = isset($_POST['sms_gateway_enabled']) ? 1 : 0;
    $sms_gateway_url = trim((string) ($_POST['sms_gateway_url'] ?? ''));
    $sms_gateway_method = strtoupper(trim((string) ($_POST['sms_gateway_method'] ?? 'POST')));
    if (!in_array($sms_gateway_method, ['GET', 'POST'], true)) {
        $sms_gateway_method = 'POST';
    }
    $sms_gateway_sender = trim((string) ($_POST['sms_gateway_sender'] ?? ''));
    $sms_gateway_token = trim((string) ($_POST['sms_gateway_token'] ?? ''));
    $sms_gateway_headers = '';
    $sms_gateway_body_template = '';
    $sms_gateway_success_keyword = trim((string) ($_POST['sms_gateway_success_keyword'] ?? ''));

    $statement = $dbRepo->prepare("UPDATE tbl_settings SET sms_gateway_enabled=?, sms_gateway_url=?, sms_gateway_method=?, sms_gateway_sender=?, sms_gateway_token=?, sms_gateway_headers=?, sms_gateway_body_template=?, sms_gateway_success_keyword=? WHERE id=1");
    $statement->execute([
        $sms_gateway_enabled,
        $sms_gateway_url,
        $sms_gateway_method,
        $sms_gateway_sender,
        $sms_gateway_token,
        $sms_gateway_headers,
        $sms_gateway_body_template,
        $sms_gateway_success_keyword
    ]);

    $success_message = 'SMS gateway settings updated successfully.';
}

if(isset($_POST['form_ecotrack'])) {
    $ecotrack_enabled = isset($_POST['ecotrack_enabled']) ? 1 : 0;
    $ecotrack_api_token = trim((string) ($_POST['ecotrack_api_token'] ?? ''));
    $ecotrack_base_url = function_exists('ecotrack_normalize_base_url_value')
        ? ecotrack_normalize_base_url_value($_POST['ecotrack_base_url'] ?? '')
        : rtrim(trim((string) ($_POST['ecotrack_base_url'] ?? '')), '/');

    $statement = $dbRepo->prepare("UPDATE tbl_settings SET ecotrack_enabled=?, ecotrack_api_token=?, ecotrack_base_url=? WHERE id=1");
    $statement->execute([
        $ecotrack_enabled,
        $ecotrack_api_token,
        $ecotrack_base_url
    ]);

    $success_message = 'ECOTRACK settings updated successfully.';
}

if(isset($_POST['form_zrexpress'])) {
    $zrexpress_enabled = isset($_POST['zrexpress_enabled']) ? 1 : 0;
    $zrexpress_token = trim((string) ($_POST['zrexpress_token'] ?? ''));
    $zrexpress_key = trim((string) ($_POST['zrexpress_key'] ?? ''));
    $zrexpress_base_url = trim((string) ($_POST['zrexpress_base_url'] ?? ''));
    if ($zrexpress_base_url === '') {
        $zrexpress_base_url = 'https://procolis.com/api_v1';
    }
    $zrexpress_base_url = rtrim($zrexpress_base_url, '/');

    $statement = $dbRepo->prepare("UPDATE tbl_settings SET zrexpress_enabled=?, zrexpress_token=?, zrexpress_key=?, zrexpress_base_url=? WHERE id=1");
    $statement->execute([
        $zrexpress_enabled,
        $zrexpress_token,
        $zrexpress_key,
        $zrexpress_base_url
    ]);

    $success_message = 'ZRexpress settings updated successfully.';
}

if(isset($_POST['form_sms_template_add'])) {
    $template_name = trim((string) ($_POST['template_name'] ?? ''));
    $template_body = trim((string) ($_POST['template_body'] ?? ''));
    $sort_order = (int) ($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($template_name === '' || $template_body === '') {
        $error_message .= 'Template name and message body are required.<br>';
    } else {
        $statement = $dbRepo->prepare("INSERT INTO tbl_sms_template (template_name, template_body, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
        $statement->execute([$template_name, $template_body, $sort_order, $is_active]);
        $success_message = 'SMS template added successfully.';
    }
}

if(isset($_POST['form_sms_template_update'])) {
    $template_id = (int) ($_POST['template_id'] ?? 0);
    $template_name = trim((string) ($_POST['template_name'] ?? ''));
    $template_body = trim((string) ($_POST['template_body'] ?? ''));
    $sort_order = (int) ($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($template_id <= 0 || $template_name === '' || $template_body === '') {
        $error_message .= 'Template data is incomplete.<br>';
    } else {
        $statement = $dbRepo->prepare("UPDATE tbl_sms_template SET template_name=?, template_body=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?");
        $statement->execute([$template_name, $template_body, $sort_order, $is_active, $template_id]);
        $success_message = 'SMS template updated successfully.';
    }
}

if(isset($_POST['form_sms_template_delete'])) {
    $template_id = (int) ($_POST['template_id'] ?? 0);
    if ($template_id > 0) {
        $statement = $dbRepo->prepare("DELETE FROM tbl_sms_template WHERE id=?");
        $statement->execute([$template_id]);
        $success_message = 'SMS template deleted successfully.';
    }
}

if(isset($_POST['form_sms_automation_update'])) {
    $sms_automation_rows = isset($_POST['sms_auto']) && is_array($_POST['sms_auto']) ? $_POST['sms_auto'] : [];
    admin_save_sms_automation_templates($pdo, $sms_automation_rows);
    $success_message = 'Automatic SMS templates updated successfully.';
}



//Can not finish this section, leave it
if(isset($_POST['form5'])) {
    // updating the database
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET total_featured_product_home=?, total_latest_product_home=?, total_popular_product_home=? WHERE id=1");
    $statement->execute(array($_POST['total_featured_product_home'],$_POST['total_latest_product_home'],$_POST['total_popular_product_home']));

    $success_message = 'Sidebar settings is updated successfully.';
}


if(isset($_POST['form6_0'])) {
    // updating the database
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET home_service_on_off=?, home_welcome_on_off=?, home_featured_product_on_off=?, home_latest_product_on_off=?, home_popular_product_on_off=? WHERE id=1");
    $statement->execute(array($_POST['home_service_on_off'],$_POST['home_welcome_on_off'],$_POST['home_featured_product_on_off'],$_POST['home_latest_product_on_off'],$_POST['home_popular_product_on_off']));

    $success_message = 'Section On-Off Settings is updated successfully.';
}


if(isset($_POST['form6'])) {
    // updating the database
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET meta_title_home=?, meta_keyword_home=?, meta_description_home=? WHERE id=1");
    $statement->execute(array($_POST['meta_title_home'],$_POST['meta_keyword_home'],$_POST['meta_description_home']));

    $success_message = 'Home Meta settings is updated successfully.';
}

if(isset($_POST['form6_7'])) {

    $valid = 1;

    if(empty($_POST['cta_title'])) {
        $valid = 0;
        $error_message .= 'Call to Action Title can not be empty<br>';
    }

    if(empty($_POST['cta_content'])) {
        $valid = 0;
        $error_message .= 'Call to Action Content can not be empty<br>';
    }

    if(empty($_POST['cta_read_more_text'])) {
        $valid = 0;
        $error_message .= 'Call to Action Read More Text can not be empty<br>';
    }

    if(empty($_POST['cta_read_more_url'])) {
        $valid = 0;
        $error_message .= 'Call to Action Read More URL can not be empty<br>';
    }

    $path = $_FILES['cta_photo']['name'];
    $path_tmp = $_FILES['cta_photo']['tmp_name'];

    if($path != '') {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {

        if($path != '') {
            // removing the existing photo
            $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
            foreach ($result as $row) {
                $cta_photo = $row['cta_photo'];
                delete_local_image_file($cta_photo, '../assets/uploads');
            }

            // updating the data
            $final_name = 'cta'.'.'.$ext;
            list($upload_ok, $stored_cta) = store_settings_uploaded_photo($path, $path_tmp, 'cta', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
            if (!$upload_ok || $stored_cta === '') {
                $valid = 0;
            } else {
                $final_name = $stored_cta;
            }

            // updating the database
            if($valid == 1) {
                $statement = $dbRepo->prepare("UPDATE tbl_settings SET cta_title=?,cta_content=?,cta_read_more_text=?,cta_read_more_url=?,cta_photo=? WHERE id=1");
                $statement->execute(array($_POST['cta_title'],$_POST['cta_content'],$_POST['cta_read_more_text'],$_POST['cta_read_more_url'],$final_name));
            }
        } else {
            // updating the database
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET cta_title=?,cta_content=?,cta_read_more_text=?,cta_read_more_url=? WHERE id=1");
            $statement->execute(array($_POST['cta_title'],$_POST['cta_content'],$_POST['cta_read_more_text'],$_POST['cta_read_more_url']));
        }

        $success_message = 'Call to Action Data is updated successfully.';
        
    }
}

if(isset($_POST['form6_4'])) {

    $valid = 1;

    if(empty($_POST['featured_product_title'])) {
        $valid = 0;
        $error_message .= 'عنوان المنتجات المميزة can not be empty<br>';
    }

    if(empty($_POST['featured_product_subtitle'])) {
        $valid = 0;
        $error_message .= 'وصف المنتجات المميزة can not be empty<br>';
    }

    if($valid == 1) {

        // updating the database
        $statement = $dbRepo->prepare("UPDATE tbl_settings SET featured_product_title=?,featured_product_subtitle=? WHERE id=1");
        $statement->execute(array($_POST['featured_product_title'],$_POST['featured_product_subtitle']));

        $success_message = 'Featured Product Data is updated successfully.';
        
    }
}

if(isset($_POST['form6_5'])) {

    $valid = 1;

    if(empty($_POST['latest_product_title'])) {
        $valid = 0;
        $error_message .= 'عنوان أحدث المنتجات can not be empty<br>';
    }

    if(empty($_POST['latest_product_subtitle'])) {
        $valid = 0;
        $error_message .= 'وصف أحدث المنتجات can not be empty<br>';
    }

    if($valid == 1) {

        // updating the database
        $statement = $dbRepo->prepare("UPDATE tbl_settings SET latest_product_title=?,latest_product_subtitle=? WHERE id=1");
        $statement->execute(array($_POST['latest_product_title'],$_POST['latest_product_subtitle']));

        $success_message = 'Latest Product Data is updated successfully.';
        
    }
}

if(isset($_POST['form6_6'])) {

    $valid = 1;

    if(empty($_POST['popular_product_title'])) {
        $valid = 0;
        $error_message .= 'عنوان المنتجات الشائعة can not be empty<br>';
    }

    if(empty($_POST['popular_product_subtitle'])) {
        $valid = 0;
        $error_message .= 'وصف المنتجات الشائعة can not be empty<br>';
    }

    if($valid == 1) {

        // updating the database
        $statement = $dbRepo->prepare("UPDATE tbl_settings SET popular_product_title=?,popular_product_subtitle=? WHERE id=1");
        $statement->execute(array($_POST['popular_product_title'],$_POST['popular_product_subtitle']));

        $success_message = 'Popular Product Data is updated successfully.';
        
    }
}


if(isset($_POST['form6_3'])) {

        // updating the database
        $statement = $dbRepo->prepare("UPDATE tbl_settings SET newsletter_text=? WHERE id=1");
        $statement->execute(array($_POST['newsletter_text']));
        
        $success_message = 'Newsletter Text is updated successfully.';
 
}

if(isset($_POST['form7_1'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_login = $row['banner_login'];
            delete_local_image_file($banner_login, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_login'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_login', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_login=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Login Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_2'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_registration = $row['banner_registration'];
            delete_local_image_file($banner_registration, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_registration'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_registration', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_registration=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Registration Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_3'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_forget_password = $row['banner_forget_password'];
            delete_local_image_file($banner_forget_password, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_forget_password'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_forget_password', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_forget_password=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Forget Password Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_4'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_reset_password = $row['banner_reset_password'];
            delete_local_image_file($banner_reset_password, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_reset_password'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_reset_password', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_reset_password=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Reset Password Page Banner is updated successfully.';
        }
        
    }
}


if(isset($_POST['form7_6'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_search = $row['banner_search'];
            delete_local_image_file($banner_search, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_search'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_search', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_search=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Search Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_7'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_cart = $row['banner_cart'];
            delete_local_image_file($banner_cart, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_cart'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_cart', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_cart=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Cart Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_8'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_checkout = $row['banner_checkout'];
            delete_local_image_file($banner_checkout, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_checkout'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_checkout', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_checkout=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Checkout Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_9'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

    if($valid == 1) {
        // removing the existing photo
        $statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
        foreach ($result as $row) {
            $banner_product_category = $row['banner_product_category'];
            delete_local_image_file($banner_product_category, '../assets/uploads');
        }

        // updating the data
        $final_name = 'banner_product_category'.'.'.$ext;
        list($upload_ok, $stored_banner) = store_settings_uploaded_photo($path, $path_tmp, 'banner_product_category', $error_message, ['jpg', 'jpeg', 'png', 'gif']);
        if (!$upload_ok || $stored_banner === '') {
            $valid = 0;
        } else {
            $final_name = $stored_banner;
        }

        // updating the database
        if($valid == 1) {
            $statement = $dbRepo->prepare("UPDATE tbl_settings SET banner_product_category=? WHERE id=1");
            $statement->execute(array($final_name));
        }

        if($valid == 1) {
            $success_message = 'Product Category Page Banner is updated successfully.';
        }
        
    }
}

if(isset($_POST['form7_10'])) {
    $valid = 1;

    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];

    if($path == '') {
        $valid = 0;
        $error_message .= 'You must have to select a photo<br>';
    } else {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, png or webp file<br>';
        }
    }

}



if(isset($_POST['form10'])) {
    // updating the database
    $statement = $dbRepo->prepare("UPDATE tbl_settings SET before_head=?, after_body=?, before_body=? WHERE id=1");
    $statement->execute(array($_POST['before_head'],$_POST['after_body'],$_POST['before_body'] ?? ''));

    $success_message = 'Head and Body Script is updated successfully.';
}


?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إعدادات المتجر</h1>
    </div>
</section>

<?php
$statement = $dbRepo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
foreach ($result as $row) {
    $logo                            = $row['logo'];
    $favicon                         = $row['favicon'];
    $footer_about                    = $row['footer_about'];
    $footer_copyright                = $row['footer_copyright'];
    $contact_address                 = $row['contact_address'];
    $contact_email                   = $row['contact_email'];
    $contact_phone                   = $row['contact_phone'];

    $contact_map_iframe              = $row['contact_map_iframe'];
    $receive_email                   = $row['receive_email'];
    $receive_email_subject           = $row['receive_email_subject'];
    $receive_email_thank_you_message = $row['receive_email_thank_you_message'];
    $forget_password_message         = $row['forget_password_message'];
 
    $total_featured_product_home     = $row['total_featured_product_home'];
    $total_latest_product_home       = $row['total_latest_product_home'];
    $total_popular_product_home      = $row['total_popular_product_home'];
    $meta_title_home                 = $row['meta_title_home'];
    $meta_keyword_home               = $row['meta_keyword_home'];
    $meta_description_home           = $row['meta_description_home'];
    $banner_login                    = $row['banner_login'];
    $banner_registration             = $row['banner_registration'];
    $banner_forget_password          = $row['banner_forget_password'];
    $banner_reset_password           = $row['banner_reset_password'];
    $banner_search                   = $row['banner_search'];
    $banner_cart                     = $row['banner_cart'];
    $banner_checkout                 = $row['banner_checkout'];
    $banner_product_category         = $row['banner_product_category'];

    $featured_product_title          = $row['featured_product_title'];
    $featured_product_subtitle       = $row['featured_product_subtitle'];
    $latest_product_title            = $row['latest_product_title'];
    $latest_product_subtitle         = $row['latest_product_subtitle'];
    $popular_product_title           = $row['popular_product_title'];
    $popular_product_subtitle        = $row['popular_product_subtitle'];
    $cta_title                       = $row['cta_title'];
    $cta_content                     = $row['cta_content'];
    $cta_read_more_text              = $row['cta_read_more_text'];
    $cta_read_more_url               = $row['cta_read_more_url'];
    $cta_photo                       = $row['cta_photo'];

    $newsletter_text                 = $row['newsletter_text'];

    $before_head                     = $row['before_head'] ?? '';
    $after_body                      = $row['after_body'] ?? '';
    $facebook_pixel_id               = $row['facebook_pixel_id'] ?? '';
    $tiktok_pixel_id                 = $row['tiktok_pixel_id'] ?? '';
    $snapchat_pixel_id               = $row['snapchat_pixel_id'] ?? '';
    $google_analytics_id             = $row['google_analytics_id'] ?? '';
    $telegram_bot_token              = $row['telegram_bot_token'] ?? '';
    $telegram_chat_id                = $row['telegram_chat_id'] ?? '';
    $telegram_orders_enabled         = isset($row['telegram_orders_enabled']) ? $row['telegram_orders_enabled'] : 0;
    $telegram_incomplete_enabled     = isset($row['telegram_incomplete_enabled']) ? $row['telegram_incomplete_enabled'] : 0;
    $telegram_incomplete_chat_id     = $row['telegram_incomplete_chat_id'] ?? '';
    $telegram_incomplete_bot_token  = $row['telegram_incomplete_bot_token'] ?? '';
    $telegram_order_status_enabled   = isset($row['telegram_order_status_enabled']) ? $row['telegram_order_status_enabled'] : 0;
    $telegram_order_status_chat_id   = $row['telegram_order_status_chat_id'] ?? '';
    $telegram_order_status_bot_token = $row['telegram_order_status_bot_token'] ?? '';
    $sms_gateway_enabled             = isset($row['sms_gateway_enabled']) ? $row['sms_gateway_enabled'] : 0;
    $sms_gateway_url                 = $row['sms_gateway_url'] ?? '';
    $sms_gateway_method              = $row['sms_gateway_method'] ?? 'POST';
    $sms_gateway_sender              = $row['sms_gateway_sender'] ?? '';
    $sms_gateway_token               = $row['sms_gateway_token'] ?? '';
    $sms_gateway_headers             = $row['sms_gateway_headers'] ?? '';
    $sms_gateway_body_template       = $row['sms_gateway_body_template'] ?? '';
    $sms_gateway_success_keyword     = $row['sms_gateway_success_keyword'] ?? '';
    $ecotrack_enabled                = isset($row['ecotrack_enabled']) ? $row['ecotrack_enabled'] : 0;
    $ecotrack_api_token              = $row['ecotrack_api_token'] ?? '';
    $ecotrack_base_url               = $row['ecotrack_base_url'] ?? '';
    $zrexpress_enabled               = isset($row['zrexpress_enabled']) ? $row['zrexpress_enabled'] : 0;
    $zrexpress_token                 = $row['zrexpress_token'] ?? '';
    $zrexpress_key                   = $row['zrexpress_key'] ?? '';
    $zrexpress_base_url              = $row['zrexpress_base_url'] ?? '';
    $home_service_on_off             = $row['home_service_on_off'];
    $home_welcome_on_off             = $row['home_welcome_on_off'];
    $home_featured_product_on_off    = $row['home_featured_product_on_off'];
    $home_latest_product_on_off      = $row['home_latest_product_on_off'];
    $home_popular_product_on_off     = $row['home_popular_product_on_off'];

    $newsletter_on_off               = $row['newsletter_on_off'];

}

$sms_templates = admin_get_sms_templates($pdo, false);
$sms_automation_templates = admin_get_sms_automation_templates($pdo);
?>


<section class="content" style="min-height:auto;margin-bottom: -30px;">
    <div class="row">
        <div class="col-md-12">
            <?php if($error_message): ?>
            <div class="callout callout-danger">
            
            <p>
            <?php echo $error_message; ?>
            </p>
            </div>
            <?php endif; ?>

            <?php if($success_message): ?>
            <div class="callout callout-success">
            
            <p><?php echo $success_message; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
                            
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">الشعار</a></li>
                        <li><a href="#tab_2" data-toggle="tab">أيقونة الموقع</a></li>
                        <li><a href="#tab_3" data-toggle="tab">الفوتر والاتصال</a></li>
                        <li><a href="#tab_4" data-toggle="tab">إعدادات الرسائل</a></li>
                        <li><a href="#tab_5" data-toggle="tab">المنتجات</a></li>
                        <li><a href="#tab_6" data-toggle="tab">إعدادات الرئيسية</a></li>
                        <li><a href="#tab_7" data-toggle="tab">إعدادات البانر</a></li>
                        <li><a href="#tab_pixels" data-toggle="tab">بيكسل (Pixels)</a></li>
                        <li><a href="#tab_telegram" data-toggle="tab">تلغرام</a></li>
                        <li><a href="#tab_ecotrack" data-toggle="tab">ECOTRACK</a></li>
                        <li><a href="#tab_zrexpress" data-toggle="tab">ZRexpress</a></li>
                        <li><a href="#tab_10" data-toggle="tab">أكواد Head & Body</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">


                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">الصورة الحالية</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($logo), ENT_QUOTES); ?>" class="existing-photo" style="height:80px;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">صورة جديدة</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <input type="file" name="photo_logo">
                                            <br><br>
                                            <input type="text" class="form-control" name="photo_logo_url" placeholder="Or paste logo URL (https://...)" value="<?php echo is_external_image_url($logo) ? htmlspecialchars($logo, ENT_QUOTES) : ''; ?>">
                                            <br>
                                            <label style="font-weight:normal;">
                                                <input type="checkbox" name="remove_photo_logo" value="1"> حذف الشعار الحالي
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form1">تحديث الشعار</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>

                            


                        </div>
                        <div class="tab-pane" id="tab_2">

                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">الصورة الحالية</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($favicon), ENT_QUOTES); ?>" class="existing-photo" style="height:40px;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">صورة جديدة</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <input type="file" name="photo_favicon">
                                            <br><br>
                                            <input type="text" class="form-control" name="photo_favicon_url" placeholder="Or paste favicon URL (https://...)" value="<?php echo is_external_image_url($favicon) ? htmlspecialchars($favicon, ENT_QUOTES) : ''; ?>">
                                            <br>
                                            <label style="font-weight:normal;">
                                                <input type="checkbox" name="remove_photo_favicon" value="1"> حذف الأيقونة الحالية
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form2">تحديث الأيقونة</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                        </div>
                        <div class="tab-pane" id="tab_3">

                            <form class="form-horizontal" action="" method="post">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">قسم القائمة البريدية </label>
                                        <div class="col-sm-3">
                                            <select name="newsletter_on_off" class="form-control" style="width:auto;">
                                                <option value="1" <?php if($newsletter_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($newsletter_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">الفوتر - حقوق النشر </label>
                                        <div class="col-sm-9">
                                            <input class="form-control" type="text" name="footer_copyright" value="<?php echo $footer_copyright; ?>">
                                        </div>
                                    </div>                              
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">عنوان الاتصال </label>
                                        <div class="col-sm-6">
                                            <textarea class="form-control" name="contact_address" style="height:140px;"><?php echo $contact_address; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">البريد الإلكتروني</label>
                                        <div class="col-sm-6">
                                            <input type="text" class="form-control" name="contact_email" value="<?php echo $contact_email; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">رقم الهاتف </label>
                                        <div class="col-sm-6">
                                            <input type="text" class="form-control" name="contact_phone" value="<?php echo $contact_phone; ?>">
                                        </div>
                                    </div>
                 
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label">خريطة الموقع (iFrame) </label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="contact_map_iframe" style="height:200px;"><?php echo $contact_map_iframe; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-2 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form3">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                        </div>

                        <div class="tab-pane" id="tab_4">

                            <form class="form-horizontal" action="" method="post">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">بريد الاتصال</label>
                                        <div class="col-sm-4">
                                            <input type="text" class="form-control" name="receive_email" value="<?php echo $receive_email; ?>">
                                        </div>
                                    </div>                                  
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">عنوان رسالة الاتصال</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="receive_email_subject" value="<?php echo $receive_email_subject; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">رسالة الشكر (اتصل بنا)</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="receive_email_thank_you_message"><?php echo $receive_email_thank_you_message; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">رسالة نسيت كلمة المرور</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="forget_password_message"><?php echo $forget_password_message; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-5">
                                            <button type="submit" class="btn btn-success pull-left" name="form4">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                        </div>

                        <div class="tab-pane" id="tab_5">

                            <form class="form-horizontal" action="" method="post">
                            <div class="box box-info">
                                <div class="box-body">
                       
                                    <div class="form-group">
                                        <label for="" class="col-sm-4 control-label">الرئيسية (عدد المنتجات المميزة)<span>*</span></label>
                                        <div class="col-sm-2">
                                            <input type="text" class="form-control" name="total_featured_product_home" value="<?php echo $total_featured_product_home; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-4 control-label">الرئيسية (عدد أحدث المنتجات)<span>*</span></label>
                                        <div class="col-sm-2">
                                            <input type="text" class="form-control" name="total_latest_product_home" value="<?php echo $total_latest_product_home; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-4 control-label">الرئيسية (عدد المنتجات الشائعة)<span>*</span></label>
                                        <div class="col-sm-2">
                                            <input type="text" class="form-control" name="total_popular_product_home" value="<?php echo $total_popular_product_home; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-4 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form5">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                        </div>




                        <div class="tab-pane" id="tab_6">


                        	<h3>Sections On and Off</h3>
                            <form class="form-horizontal" action="" method="post">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">قسم الخدمات </label>
                                        <div class="col-sm-4">
                                            <select name="home_service_on_off" class="form-control" style="width:auto;">
                                            	<option value="1" <?php if($home_service_on_off == 1) {echo 'selected';} ?>>On</option>
                                            	<option value="0" <?php if($home_service_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>      
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">قسم الترحيب </label>
                                        <div class="col-sm-4">
                                            <select name="home_welcome_on_off" class="form-control" style="width:auto;">
                                            	<option value="1" <?php if($home_welcome_on_off == 1) {echo 'selected';} ?>>On</option>
                                            	<option value="0" <?php if($home_welcome_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">قسم المنتجات المميزة </label>
                                        <div class="col-sm-4">
                                            <select name="home_featured_product_on_off" class="form-control" style="width:auto;">
                                            	<option value="1" <?php if($home_featured_product_on_off == 1) {echo 'selected';} ?>>On</option>
                                            	<option value="0" <?php if($home_featured_product_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">قسم أحدث المنتجات </label>
                                        <div class="col-sm-4">
                                            <select name="home_latest_product_on_off" class="form-control" style="width:auto;">
                                            	<option value="1" <?php if($home_latest_product_on_off == 1) {echo 'selected';} ?>>On</option>
                                            	<option value="0" <?php if($home_latest_product_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">قسم المنتجات الشائعة </label>
                                        <div class="col-sm-4">
                                            <select name="home_popular_product_on_off" class="form-control" style="width:auto;">
                                            	<option value="1" <?php if($home_popular_product_on_off == 1) {echo 'selected';} ?>>On</option>
                                            	<option value="0" <?php if($home_popular_product_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                               
                                    
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6_0">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>

                            
                            <h3>Meta Section</h3>
                            <form class="form-horizontal" action="" method="post">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">عنوان الميتا (Meta Title)</label>
                                        <div class="col-sm-8">
                                            <input type="text" name="meta_title_home" class="form-control" value="<?php echo $meta_title_home ?>">
                                        </div>
                                    </div>      
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">كلمات الميتا (Meta Keyword)</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="meta_keyword_home" style="height:100px;"><?php echo $meta_keyword_home ?></textarea>
                                        </div>
                                    </div>  
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">وصف الميتا (Meta Description)</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="meta_description_home" style="height:200px;"><?php echo $meta_description_home ?></textarea>
                                        </div>
                                    </div>  
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>

                            <h3>Call to Action Section</h3>
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">عنوان CTA<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="cta_title" value="<?php echo $cta_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">محتوى CTA<span>*</span></label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="cta_content" style="height:140px;"><?php echo $cta_content; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">نص زر CTA<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="cta_read_more_text" value="<?php echo $cta_read_more_text; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">رابط زر CTA<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="cta_read_more_url" value="<?php echo $cta_read_more_url; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">صورة CTA الحالية</label>
                                        <div class="col-sm-8" style="padding-top:6px;">
                                            <?php if(!empty($cta_photo)): ?>
                                                <img src="<?php echo htmlspecialchars(get_admin_image_url($cta_photo), ENT_QUOTES); ?>" class="existing-photo" style="height:80px;">
                                            <?php else: ?>
                                                <span>No photo uploaded</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">صورة CTA جديدة</label>
                                        <div class="col-sm-8" style="padding-top:6px;">
                                            <input type="file" name="cta_photo">
                                            <br><br>
                                            <input type="text" class="form-control" name="cta_photo_url" placeholder="Or paste CTA image URL (https://...)" value="<?php echo is_external_image_url($cta_photo) ? htmlspecialchars($cta_photo, ENT_QUOTES) : ''; ?>">
                                            <br>
                                            <label style="font-weight:normal;">
                                                <input type="checkbox" name="remove_cta_photo" value="1"> Delete existing CTA photo
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6_7">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>



                    





                            <h3>قسم المنتجات المميزة</h3>
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">                                          
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">عنوان المنتجات المميزة<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="featured_product_title" value="<?php echo $featured_product_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">وصف المنتجات المميزة<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="featured_product_subtitle" value="<?php echo $featured_product_subtitle; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6_4">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                            <h3>قسم أحدث المنتجات</h3>
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">                                          
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">عنوان أحدث المنتجات<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="latest_product_title" value="<?php echo $latest_product_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">وصف أحدث المنتجات<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="latest_product_subtitle" value="<?php echo $latest_product_subtitle; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6_5">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                            <h3>قسم المنتجات الشائعة</h3>
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">                                          
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">عنوان المنتجات الشائعة<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="popular_product_title" value="<?php echo $popular_product_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">وصف المنتجات الشائعة<span>*</span></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="popular_product_subtitle" value="<?php echo $popular_product_subtitle; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6_6">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                            

                            <h3>قسم القائمة البريدية</h3>
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">                                          
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Newsletter Text</label>
                                        <div class="col-sm-8">
                                            <textarea name="newsletter_text" class="form-control" cols="30" rows="10" style="height: 120px;"><?php echo $newsletter_text; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form6_3">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>


                        </div>



                        <div class="tab-pane" id="tab_7">

                            <table class="table table-bordered">
                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Login Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_login), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;"> 
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Login Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_1">
                                    </td>
                                    </form>
                                </tr>
                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Registration Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_registration), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">  
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Registration Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_2">
                                    </td>
                                    </form>
                                </tr>
                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Forget Password Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_forget_password), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">   
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Forget Password Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_3">
                                    </td>
                                    </form>
                                </tr>
                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Reset Password Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_reset_password), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">   
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Reset Password Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_4">
                                    </td>
                                    </form>
                                </tr>
                                
                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Search Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_search), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">  
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Search Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_6">
                                    </td>
                                    </form>
                                </tr>


                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Cart Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_cart), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">  
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Cart Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_7">
                                    </td>
                                    </form>
                                </tr>


                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Checkout Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_checkout), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">  
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Checkout Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_8">
                                    </td>
                                    </form>
                                </tr>

                                <tr>
                                    <form action="" method="post" enctype="multipart/form-data">
                                    <td style="width:50%">
                                        <h4>Existing Product Category Page Banner</h4>
                                        <p>
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($banner_product_category), ENT_QUOTES); ?>" alt="" style="width: 100%;height:auto;">  
                                        </p>                                        
                                    </td>
                                    <td style="width:50%">
                                        <h4>Change Product Category Page Banner</h4>
                                        Select Photo<input type="file" name="photo">
                                        <br><br>
                                        <input type="text" class="form-control" name="photo_url" placeholder="Or paste banner URL (https://...)">
                                        <label style="display:block;margin-top:8px;font-weight:normal;">
                                            <input type="checkbox" name="remove_photo" value="1"> Delete existing banner
                                        </label>
                                        <input type="submit" class="btn btn-primary btn-xs" value="Change" style="margin-top:10px;" name="form7_9">
                                    </td>
                                    </form>
                                </tr>

                             
                            </table>

                        </div>



                    
                    

                    


                        <div class="tab-pane" id="tab_pixels">
                            <h3>Pixel Settings</h3>
                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <?php if (empty($facebook_pixel_id) || empty($tiktok_pixel_id) || empty($snapchat_pixel_id) || empty($google_analytics_id)): ?>
                                        <div class="alert alert-warning">
                                            <?php if (empty($facebook_pixel_id)): ?>
                                                <div>Facebook Pixel غير مفعّل حالياً لأن المعرف غير مُدخل.</div>
                                            <?php endif; ?>
                                            <?php if (empty($tiktok_pixel_id)): ?>
                                                <div>TikTok Pixel غير مفعّل حالياً لأن المعرف غير مُدخل.</div>
                                            <?php endif; ?>
                                            <?php if (empty($snapchat_pixel_id)): ?>
                                                <div>Snapchat Pixel غير مفعّل حالياً لأن المعرف غير مُدخل.</div>
                                            <?php endif; ?>
                                            <?php if (empty($google_analytics_id)): ?>
                                                <div>Google Analytics غير مفعّل حالياً لأن المعرف غير مُدخل.</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Facebook Pixel ID</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="facebook_pixel_id" value="<?php echo htmlspecialchars($facebook_pixel_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Leave empty to disable tracking</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">TikTok Pixel ID</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="tiktok_pixel_id" value="<?php echo htmlspecialchars($tiktok_pixel_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Leave empty to disable tracking</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Snapchat Pixel ID</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="snapchat_pixel_id" value="<?php echo htmlspecialchars($snapchat_pixel_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Snapchat Snap Pixel SDK — Leave empty to disable</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Google Analytics ID</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="google_analytics_id" value="<?php echo htmlspecialchars($google_analytics_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">GA4 Measurement ID (G-XXXXXXXXXX) — Leave empty to disable</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" name="form_pixels" class="btn btn-primary">
                                                    <i class="fa fa-save"></i> Save Pixel Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div>

                        <div class="tab-pane" id="tab_telegram">
                            <h3>Telegram Settings</h3>
                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Enable Order Notifications</label>
                                            <div class="col-sm-4">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="telegram_orders_enabled" value="1" <?php echo !empty($telegram_orders_enabled) ? 'checked' : ''; ?>>
                                                        Send completed orders to Telegram
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Enable Incomplete Order Alerts</label>
                                            <div class="col-sm-4">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="telegram_incomplete_enabled" value="1" <?php echo !empty($telegram_incomplete_enabled) ? 'checked' : ''; ?>>
                                                        Send incomplete orders to Telegram
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Enable Order Status Alerts</label>
                                            <div class="col-sm-4">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="telegram_order_status_enabled" value="1" <?php echo !empty($telegram_order_status_enabled) ? 'checked' : ''; ?>>
                                                        Send parcel status changes to Telegram
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Bot Token</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="telegram_bot_token" value="<?php echo htmlspecialchars($telegram_bot_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Bot token for completed orders</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Bot Token for incomplete orders</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="telegram_incomplete_bot_token" value="<?php echo htmlspecialchars($telegram_incomplete_bot_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Use a different bot if you want separation</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Bot Token for order status</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="telegram_order_status_bot_token" value="<?php echo htmlspecialchars($telegram_order_status_bot_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Leave empty to use the main bot token</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Chat ID for completed orders</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="telegram_chat_id" value="<?php echo htmlspecialchars($telegram_chat_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Chat ID for incomplete orders</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="telegram_incomplete_chat_id" value="<?php echo htmlspecialchars($telegram_incomplete_chat_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Leave empty to use the same Chat ID</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Chat ID for order status</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="telegram_order_status_chat_id" value="<?php echo htmlspecialchars($telegram_order_status_chat_id ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Use this for your Order Status Telegram channel/group</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" name="form_telegram" class="btn btn-primary">
                                                    <i class="fa fa-save"></i> Save Telegram Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div>

                        <div class="tab-pane" id="tab_ecotrack">
                            <h3>ECOTRACK Settings</h3>
                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <?php if (!empty($ecotrack_enabled) && trim((string) $ecotrack_api_token) === ''): ?>
                                        <div class="alert alert-warning">
                                            ECOTRACK is enabled but the API token is still empty.
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($ecotrack_enabled) && trim((string) $ecotrack_api_token) !== '' && trim((string) $ecotrack_base_url) === ''): ?>
                                        <div class="alert alert-info">
                                            Auto-discovery of the ECOTRACK host may fail depending on your account. If that happens, paste the exact <code>{{url}}</code> value from your ECOTRACK API documentation in the Base URL field below.
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Enable ECOTRACK</label>
                                            <div class="col-sm-4">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="ecotrack_enabled" value="1" <?php echo !empty($ecotrack_enabled) ? 'checked' : ''; ?>>
                                                        Activate ECOTRACK integration in the backend
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">API Token</label>
                                            <div class="col-sm-8">
                                                <textarea name="ecotrack_api_token" class="form-control" rows="3" placeholder="Paste your ECOTRACK token here"><?php echo htmlspecialchars((string) $ecotrack_api_token, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                <small class="text-muted">Paste the token normally. The backend will build the Bearer authorization header automatically when ECOTRACK is used later.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Base URL</label>
                                            <div class="col-sm-8">
                                                <input type="text" name="ecotrack_base_url" class="form-control" value="<?php echo htmlspecialchars((string) $ecotrack_base_url, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://your-ecotrack-host.tld">
                                                <small class="text-muted">Optional but recommended. Paste the exact value that replaces <code>{{url}}</code> in the ECOTRACK documentation. If you paste a full endpoint like <code>https://host/api/v1/get/orders</code>, the backend will reduce it automatically to the host root.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Integration Mode</label>
                                            <div class="col-sm-8" style="padding-top:7px;">
                                                <span class="label label-info">Token + optional Base URL</span>
                                                <span class="text-muted" style="margin-left:8px;">If auto-discovery fails, save the Base URL manually. The token alone is not always enough to guess the correct ECOTRACK host.</span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" name="form_ecotrack" class="btn btn-primary">
                                                    <i class="fa fa-save"></i> Save ECOTRACK Settings
                                                </button>
                                                <a href="ecotrack-diagnostics.php" class="btn btn-default" style="margin-left:8px;">
                                                    <i class="fa fa-stethoscope"></i> Open ECOTRACK Diagnostics
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>

                        <div class="tab-pane" id="tab_zrexpress">
                            <h3>ZRexpress Settings</h3>
                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <?php if (!empty($zrexpress_enabled) && (trim((string) $zrexpress_token) === '' || trim((string) $zrexpress_key) === '')): ?>
                                        <div class="alert alert-warning">
                                            ZRexpress is enabled but the API Token or API Key is still empty.
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Enable ZRexpress</label>
                                            <div class="col-sm-4">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="zrexpress_enabled" value="1" <?php echo !empty($zrexpress_enabled) ? 'checked' : ''; ?>>
                                                        Activate ZRexpress integration in the backend
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">API Token</label>
                                            <div class="col-sm-8">
                                                <textarea name="zrexpress_token" class="form-control" rows="2" placeholder="Paste your ZRexpress token here"><?php echo htmlspecialchars((string) $zrexpress_token, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                <small class="text-muted">Paste the token provided by ZRexpress.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">API Key</label>
                                            <div class="col-sm-8">
                                                <input type="text" name="zrexpress_key" class="form-control" value="<?php echo htmlspecialchars((string) $zrexpress_key, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Paste your ZRexpress key here">
                                                <small class="text-muted">Paste the API Key (Clé) provided by ZRexpress.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Base URL</label>
                                            <div class="col-sm-8">
                                                <input type="text" name="zrexpress_base_url" class="form-control" value="<?php echo htmlspecialchars((string) $zrexpress_base_url, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://procolis.com/api_v1">
                                                <small class="text-muted">Default is <code>https://procolis.com/api_v1</code>. Do not change this unless instructed by the delivery provider.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" name="form_zrexpress" class="btn btn-primary">
                                                    <i class="fa fa-save"></i> Save ZRexpress Settings
                                                </button>
                                                <a href="zrexpress-diagnostics.php" class="btn btn-default" style="margin-left:8px;">
                                                    <i class="fa fa-stethoscope"></i> Open ZRexpress Diagnostics
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane" id="tab_sms">
                            <h3>SMS Gateway Settings</h3>
                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <?php if (!empty($sms_gateway_enabled) && trim((string) $sms_gateway_url) === ''): ?>
                                        <div class="alert alert-warning">
                                            SMS gateway is enabled but the endpoint URL is empty.
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Enable SMS Gateway</label>
                                            <div class="col-sm-4">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="sms_gateway_enabled" value="1" <?php echo !empty($sms_gateway_enabled) ? 'checked' : ''; ?>>
                                                        Allow manual SMS sending from order management
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Endpoint URL</label>
                                            <div class="col-sm-8">
                                                <textarea name="sms_gateway_url" class="form-control" rows="2" placeholder="https://api.example.com/send"><?php echo htmlspecialchars((string) $sms_gateway_url, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                <small class="text-muted">If you use SMS Gateway Cloud with api.smstext.app, headers and payload are handled automatically in the backend.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Method</label>
                                            <div class="col-sm-3">
                                                <select name="sms_gateway_method" class="form-control">
                                                    <option value="POST" <?php echo strtoupper((string) $sms_gateway_method) === 'POST' ? 'selected' : ''; ?>>POST</option>
                                                    <option value="GET" <?php echo strtoupper((string) $sms_gateway_method) === 'GET' ? 'selected' : ''; ?>>GET</option>
                                                </select>
                                            </div>
                                            <label class="col-sm-2 control-label">Sender</label>
                                            <div class="col-sm-3">
                                                <input type="text" class="form-control" name="sms_gateway_sender" value="<?php echo htmlspecialchars((string) $sms_gateway_sender, ENT_QUOTES, 'UTF-8'); ?>" placeholder="BoomStore">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Token / API Key</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="sms_gateway_token" value="<?php echo htmlspecialchars((string) $sms_gateway_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <small class="text-muted">Enter the raw API key normally. For api.smstext.app, the backend builds the required Basic authentication and request body automatically.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Success Keyword</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="sms_gateway_success_keyword" value="<?php echo htmlspecialchars((string) $sms_gateway_success_keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="accepted">
                                                <small class="text-muted">Optional. If filled, the response must contain this text to be considered successful.</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" name="form_sms_gateway" class="btn btn-primary">
                                                    <i class="fa fa-save"></i> Save SMS Gateway Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <h3>Manual SMS Templates</h3>
                            <div class="box box-info">
                                <div class="box-body">
                                    <p class="text-muted">اكتب الرسالة كما تريد، ثم اخترها لاحقاً عند الإرسال من الطلبات.</p>

                                    <?php if ($sms_templates): ?>
                                        <?php foreach ($sms_templates as $sms_template): ?>
                                        <form class="form-horizontal" action="" method="post" style="border:1px solid #eee;border-radius:8px;padding:15px 15px 5px;margin-bottom:15px;">
                                            <input type="hidden" name="template_id" value="<?php echo (int) $sms_template['id']; ?>">
                                            <div class="form-group">
                                                <label class="col-sm-2 control-label">Template Name</label>
                                                <div class="col-sm-4">
                                                    <input type="text" class="form-control" name="template_name" value="<?php echo htmlspecialchars((string) $sms_template['template_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                                </div>
                                                <label class="col-sm-2 control-label">Sort Order</label>
                                                <div class="col-sm-2">
                                                    <input type="number" class="form-control" name="sort_order" value="<?php echo (int) ($sms_template['sort_order'] ?? 0); ?>">
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="checkbox" style="margin-top:7px;">
                                                        <label>
                                                            <input type="checkbox" name="is_active" value="1" <?php echo !empty($sms_template['is_active']) ? 'checked' : ''; ?>>
                                                            Active
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label class="col-sm-2 control-label">Message Body</label>
                                                <div class="col-sm-10">
                                                    <textarea name="template_body" class="form-control" rows="4" required><?php echo htmlspecialchars((string) $sms_template['template_body'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-sm-offset-2 col-sm-10">
                                                    <button type="submit" name="form_sms_template_update" class="btn btn-primary">
                                                        <i class="fa fa-save"></i> Update Template
                                                    </button>
                                                    <button type="submit" name="form_sms_template_delete" class="btn btn-danger" onclick="return confirm('Delete this SMS template?');">
                                                        <i class="fa fa-trash"></i> Delete Template
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">لا توجد رسائل ثابتة حتى الآن.</div>
                                    <?php endif; ?>

                                    <form class="form-horizontal" action="" method="post" style="border-top:1px solid #eee;padding-top:20px;">
                                        <div class="form-group">
                                            <label class="col-sm-2 control-label">New Template</label>
                                            <div class="col-sm-4">
                                                <input type="text" class="form-control" name="template_name" placeholder="رسالة تأكيد" required>
                                            </div>
                                            <label class="col-sm-2 control-label">Sort Order</label>
                                            <div class="col-sm-2">
                                                <input type="number" class="form-control" name="sort_order" value="0">
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="checkbox" style="margin-top:7px;">
                                                    <label>
                                                        <input type="checkbox" name="is_active" value="1" checked>
                                                        Active
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-2 control-label">Message Body</label>
                                            <div class="col-sm-10">
                                                <textarea name="template_body" class="form-control" rows="4" placeholder="تم تأكيد طلبك، وسنتواصل معك قريباً." required></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-2 col-sm-10">
                                                <button type="submit" name="form_sms_template_add" class="btn btn-success">
                                                    <i class="fa fa-plus"></i> Add Template
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <h3>Automatic SMS Messages</h3>
                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <p class="text-muted">اكتب الرسالة التي تريد إرسالها تلقائياً عند كل حالة، والنظام يتولى تجهيز الإرسال في الخلفية.</p>

                                        <?php foreach ($sms_automation_templates as $event_key => $automation_template): ?>
                                        <div style="border:1px solid #eee;border-radius:8px;padding:15px 15px 5px;margin-bottom:15px;">
                                            <div class="form-group">
                                                <label class="col-sm-3 control-label"><?php echo htmlspecialchars((string) ($automation_template['label'] ?? $event_key), ENT_QUOTES, 'UTF-8'); ?></label>
                                                <div class="col-sm-7">
                                                    <textarea name="sms_auto[<?php echo htmlspecialchars($event_key, ENT_QUOTES, 'UTF-8'); ?>][body]" class="form-control" rows="4" placeholder="اكتب الرسالة التي تريد إرسالها تلقائياً عند هذه الحالة."><?php echo htmlspecialchars((string) ($automation_template['template_body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    <small class="text-muted"><?php echo htmlspecialchars((string) ($automation_template['hint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="checkbox" style="margin-top:7px;">
                                                        <label>
                                                            <input type="checkbox" name="sms_auto[<?php echo htmlspecialchars($event_key, ENT_QUOTES, 'UTF-8'); ?>][enabled]" value="1" <?php echo !empty($automation_template['is_enabled']) ? 'checked' : ''; ?>>
                                                            Enabled
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>

                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" name="form_sms_automation_update" class="btn btn-primary">
                                                    <i class="fa fa-save"></i> Save Automatic SMS Messages
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane" id="tab_10">

                            <form class="form-horizontal" action="" method="post">
                                <div class="box box-info">
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label for="" class="col-sm-2 control-label">Code before &lt;/head&gt; tag </label>
                                            <div class="col-sm-8">
                                                <textarea name="before_head" class="form-control" cols="30" rows="10"><?php echo $before_head; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="" class="col-sm-2 control-label">Code after &lt;body&gt; tag </label>
                                            <div class="col-sm-8">
                                                <textarea name="after_body" class="form-control" cols="30" rows="10"><?php echo $after_body; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="" class="col-sm-2 control-label"></label>
                                            <div class="col-sm-6">
                                                <button type="submit" class="btn btn-success pull-left" name="form10">Update</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>




                    </div>
                </div>

                

            </form>
        </div>
    </div>

</section>

<script>
window.addEventListener('load', function() {
    if (typeof jQuery === 'undefined') {
        return;
    }

    (function($) {
        var hash = window.location.hash || '';
        if (hash && $('.nav-tabs a[href="' + hash + '"]').length) {
            $('.nav-tabs a[href="' + hash + '"]').tab('show');
        }

        $('.nav-tabs a[data-toggle="tab"]').on('shown.bs.tab', function() {
            var href = $(this).attr('href') || '';
            if (href === '') {
                return;
            }
            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', href);
            } else {
                window.location.hash = href;
            }
        });
    })(jQuery);
});
</script>

<?php require_once('footer.php'); ?>









