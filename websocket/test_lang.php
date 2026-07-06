<?php
echo "==============================================\n";
echo "  Admin RTL & Language Switcher Test\n";
echo "==============================================\n\n";

$pass = 0;
$fail = 0;

function check($label, $found, $detail = '') {
    global $pass, $fail;
    if ($found) { $pass++; echo "[PASS] {$label}" . ($detail ? " - {$detail}" : "") . "\n"; }
    else { $fail++; echo "[FAIL] {$label}" . ($detail ? " - {$detail}" : "") . "\n"; }
}

// Test 1: header.php syntax
echo "--- Test 1: PHP Syntax ---\n";
exec('"C:\\xampp\\php\\php.exe" -l "C:\\xampp\\htdocs\\ecom\\admin\\header.php" 2>&1', $output, $rc);
check('header.php syntax', $rc === 0);

// Test 2: Read header.php
echo "\n--- Test 2: Language Handling Code ---\n";
$header = file_get_contents(__DIR__ . '/../admin/header.php');
check('Session language handling', strpos($header, 'admin_lang') !== false);
check('Lang GET parameter', strpos($header, "'lang'") !== false);
check('Direction variable', strpos($header, 'lang_dir') !== false);
check('Arabic flag', strpos($header, '🇩🇿') !== false);
check('French flag', strpos($header, '🇫🇷') !== false);
check('English flag', strpos($header, '🇺🇸') !== false);

// Test 3: HTML lang/dir
echo "\n--- Test 3: HTML lang/dir ---\n";
check('Dynamic lang attribute', strpos($header, 'lang="<?php echo $current_lang; ?>"') !== false);
check('Dynamic dir attribute', strpos($header, 'dir="<?php echo $lang_dir; ?>"') !== false);

// Test 4: Language switcher button
echo "\n--- Test 4: Language Switcher ---\n";
check('Switcher button', strpos($header, 'lang-switcher-toggle') !== false);
check('Arabic link', strpos($header, '?lang=ar') !== false);
check('French link', strpos($header, '?lang=fr') !== false);
check('English link', strpos($header, '?lang=en') !== false);
check('Close dropdown JS', strpos($header, 'lang-switcher-dropdown') !== false);

// Test 5: CSS
echo "\n--- Test 5: CSS RTL ---\n";
$css = file_get_contents(__DIR__ . '/../admin/style.css');
check('Content-header RTL', strpos($css, '.admin-ltr-layout .content-header') !== false);
check('Box-title RTL', strpos($css, '.admin-ltr-layout .content .box-title') !== false);
check('Table RTL', strpos($css, '.admin-ltr-layout .content .table th') !== false);
check('Lang switcher CSS', strpos($css, '.lang-switcher') !== false);
check('Lang dropdown CSS', strpos($css, '.lang-switcher-dropdown') !== false);

// Test 6: Body class preserved
echo "\n--- Test 6: Body Class ---\n";
check('admin-ltr-layout preserved', strpos($header, 'admin-ltr-layout') !== false);
check('sidebar-mini preserved', strpos($header, 'sidebar-mini') !== false);

echo "\n==============================================\n";
echo "  RESULTS: {$pass} passed, {$fail} failed\n";
echo "==============================================\n";

exit($fail > 0 ? 1 : 0);
