<?php
/**
 * Apply Security Fixes:
 * 1. XSS in login.php and registration.php
 * 2. File Upload MIME validation
 * 3. CSRF token functions
 */

// ═══ FIX 1: XSS ══════════════════════════════════════════
$xss_files = [
    'C:/xampp/htdocs/ecom/login.php',
    'C:/xampp/htdocs/ecom/registration.php',
];

foreach ($xss_files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Fix: echo $_POST['key'] → echo htmlspecialchars($_POST['key'], ENT_QUOTES, 'UTF-8')
    $before = $content;
    $content = preg_replace_callback(
        '/if\s*\(isset\(\$_POST\[\'([^\']+)\'\]\)\)\s*echo\s+\$_POST\[\'[^\']+\'\]/',
        function($m) {
            $key = $m[1];
            return "if(isset(\$_POST['" . $key . "'])) echo htmlspecialchars(\$_POST['" . $key . "'], ENT_QUOTES, 'UTF-8')";
        },
        $content
    );
    
    file_put_contents($file, $content);
    $changed = ($content !== $before) ? 'FIXED' : 'NO CHANGE';
    echo "XSS fix for " . basename($file) . ": $changed\n";
}

// ═══ FIX 2: File Upload MIME Validation ══════════════════

$exchange_file = 'C:/xampp/htdocs/ecom/api/exchange-request.php';
if (file_exists($exchange_file)) {
    $content = file_get_contents($exchange_file);
    if (strpos($content, 'mime_content_type') === false) {
        $mime_check = "\n    // Security: MIME type validation\n" .
                      "    \$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];\n" .
                      "    if (!in_array(mime_content_type(\$tmp), \$allowed_types)) {\n" .
                      "        echo json_encode(['success' => false, 'error' => 'نوع الملف غير مسموح.']);\n" .
                      "        exit;\n" .
                      "    }\n";
        $content = str_replace('if (!move_uploaded_file($tmp', $mime_check . '    if (!move_uploaded_file($tmp', $content);
        file_put_contents($exchange_file, $content);
        echo "File upload MIME validation added to exchange-request.php\n";
    } else {
        echo "MIME validation already exists in exchange-request.php\n";
    }
}

// ═══ FIX 3: CSRF Token Functions ══════════════════════════

$config_file = 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$config_content = file_get_contents($config_file);

if (strpos($config_content, 'csrf_generate_token') === false) {
    $csrf_code  = "\n\n// ─── CSRF Token Helpers ────────────────────────────────────────\n";
    $csrf_code .= "if (!function_exists('csrf_generate_token')) {\n";
    $csrf_code .= "    function csrf_generate_token(): string {\n";
    $csrf_code .= "        if (session_status() === PHP_SESSION_NONE) session_start();\n";
    $csrf_code .= "        if (empty(\$_SESSION['csrf_token'])) {\n";
    $csrf_code .= "            \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n";
    $csrf_code .= "        }\n";
    $csrf_code .= "        return \$_SESSION['csrf_token'];\n";
    $csrf_code .= "    }\n";
    $csrf_code .= "}\n\n";
    $csrf_code .= "if (!function_exists('csrf_validate_token')) {\n";
    $csrf_code .= "    function csrf_validate_token(string \$token): bool {\n";
    $csrf_code .= "        if (session_status() === PHP_SESSION_NONE) session_start();\n";
    $csrf_code .= "        \$valid = isset(\$_SESSION['csrf_token']) && hash_equals(\$_SESSION['csrf_token'], \$token);\n";
    $csrf_code .= "        if (\$valid) { \$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }\n";
    $csrf_code .= "        return \$valid;\n";
    $csrf_code .= "    }\n";
    $csrf_code .= "}\n\n";
    $csrf_code .= "if (!function_exists('csrf_field')) {\n";
    $csrf_code .= "    function csrf_field(): string {\n";
    $csrf_code .= "        return '<input type=\"hidden\" name=\"csrf_token\" value=\"' . htmlspecialchars(csrf_generate_token(), ENT_QUOTES) . '\">';\n";
    $csrf_code .= "    }\n";
    $csrf_code .= "}\n";

    $config_content .= $csrf_code;
    file_put_contents($config_file, $config_content);
    echo "CSRF token functions added to config.php\n";
} else {
    echo "CSRF functions already exist in config.php\n";
}

echo "\nAll security fixes applied!\n";
