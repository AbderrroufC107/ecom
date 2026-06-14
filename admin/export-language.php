<?php
// UTF-8 CSV export for language table
session_start();
if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
    header('location: login.php');
    exit;
}
require_once(__DIR__ . '/inc/config.php');

// Prevent output buffering issues
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="language_export_'.date('Ymd_His').'.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM to help Excel detect encoding
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Column headers
fputcsv($out, ['lang_id', 'lang_value']);

$stmt = $pdo->prepare('SELECT lang_id, lang_value FROM tbl_language ORDER BY lang_id ASC');
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Ensure values are strings and UTF-8 safe
    $id = (string)$row['lang_id'];
    $val = (string)$row['lang_value'];
    // Normalize to UTF-8 if needed
    if (!mb_detect_encoding($val, 'UTF-8', true)) {
        $converted = @mb_convert_encoding($val, 'UTF-8', 'auto');
        if ($converted !== false) { $val = $converted; }
    }
    fputcsv($out, [$id, $val]);
}

fclose($out);
exit;
?>


