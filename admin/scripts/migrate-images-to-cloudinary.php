<?php
/**
 * Usage:
 *   php admin/scripts/migrate-images-to-cloudinary.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['PHP_SELF'] = $_SERVER['PHP_SELF'] ?? '/ecom/index.php';

require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/functions.php';

if (!cloudinary_is_enabled()) {
    echo "Cloudinary is not enabled. Set CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET (or UPLOAD_PRESET) first.\n";
    exit(1);
}

$targets = [
    ['table' => 'tbl_product', 'pk' => 'p_id', 'columns' => ['p_featured_photo', 'landing_photo_1', 'landing_photo_2', 'landing_photo_3']],
    ['table' => 'tbl_product_photo', 'pk' => 'pp_id', 'columns' => ['photo']],
    ['table' => 'tbl_settings', 'pk' => 'id', 'columns' => ['logo', 'favicon', 'cta_photo', 'banner_login', 'banner_registration', 'banner_forget_password', 'banner_reset_password', 'banner_search', 'banner_cart', 'banner_checkout', 'banner_product_category']]
];

$checked = 0;
$updated = 0;
$skipped = 0;
$failed = 0;
$errors = [];

foreach ($targets as $target) {
    $table = $target['table'];
    $pk = $target['pk'];
    $columns = $target['columns'];

    $column_sql = implode(', ', array_map(static function ($c) {
        return "`$c`";
    }, $columns));

    $stmt = $dbRepo->query("SELECT `$pk`, $column_sql FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        continue;
    }

    foreach ($rows as $row) {
        $pk_value = $row[$pk];
        foreach ($columns as $column) {
            $current = trim((string)($row[$column] ?? ''));
            if ($current === '') {
                continue;
            }

            $checked++;

            if (is_cloudinary_url($current)) {
                $skipped++;
                continue;
            }

            $new_value = '';
            $error_message = '';

            if (is_external_image_url($current)) {
                $new_value = store_external_image_url($current, $error_message);
            } else {
                $local_path = get_local_upload_path($current, __DIR__ . '/../../assets/uploads');
                if ($local_path !== '' && is_file($local_path)) {
                    $public_id = 'migration-' . $table . '-' . $pk_value . '-' . $column;
                    list($ok_upload, $cloud_url, $cloud_error) = cloudinary_upload_local_image($local_path, basename($local_path), $public_id);
                    if ($ok_upload && $cloud_url !== '') {
                        $new_value = $cloud_url;
                    } else {
                        $error_message = $cloud_error;
                    }
                } else {
                    $error_message = 'Local file not found: ' . $current;
                }
            }

            if ($new_value === '' || !is_cloudinary_url($new_value)) {
                $failed++;
                if ($error_message !== '') {
                    $errors[] = $table . '.' . $column . ' #' . $pk_value . ' -> ' . $error_message;
                }
                continue;
            }

            if ($new_value === $current) {
                $skipped++;
                continue;
            }

            $update = $dbRepo->prepare("UPDATE `$table` SET `$column` = ? WHERE `$pk` = ?");
            $update->execute([$new_value, $pk_value]);
            $updated++;
            echo $table . '.' . $column . ' #' . $pk_value . "\n";
            echo '  OLD: ' . $current . "\n";
            echo '  NEW: ' . $new_value . "\n";
        }
    }
}

echo "\nDone.\n";
echo 'Checked: ' . $checked . "\n";
echo 'Updated: ' . $updated . "\n";
echo 'Skipped: ' . $skipped . "\n";
echo 'Failed: ' . $failed . "\n";

if (!empty($errors)) {
    echo "\nErrors (first 20):\n";
    foreach (array_slice($errors, 0, 20) as $line) {
        echo '- ' . $line . "\n";
    }
}

