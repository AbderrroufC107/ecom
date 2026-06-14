<?php
$url = 'http://127.0.0.1/ecom/admin/phpinfo.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "==============================================\n";
echo "  PHP Info Page Test\n";
echo "  URL: {$url}\n";
echo "==============================================\n\n";

echo "HTTP Status: {$httpCode}\n";

if ($httpCode === 200) {
    echo "Page loaded successfully!\n";
    echo "Size: " . strlen($response) . " bytes\n\n";

    $checks = [
        ['PHP Info', 'PHP Info'],
        ['PHP Version', 'PHP Version'],
        ['Server Information', 'Server Information'],
        ['PHP Configuration', 'PHP Configuration'],
        ['Loaded Extensions', 'Loaded Extensions'],
        ['Server Variables', 'Server Variables'],
        ['Important Functions', 'Important Functions'],
        ['Environment Variables', 'Environment Variables'],
        ['openssl extension', 'openssl'],
        ['curl extension', 'curl'],
        ['mysqli extension', 'mysqli'],
        ['pdo_mysql extension', 'pdo_mysql'],
        ['sockets extension', 'sockets'],
        ['mbstring extension', 'mbstring'],
    ];

    echo "--- Content Checks ---\n";
    $pass = 0;
    $fail = 0;
    foreach ($checks as [$label, $search]) {
        if (strpos($response, $search) !== false) {
            echo "[PASS] {$label}\n";
            $pass++;
        } else {
            echo "[FAIL] {$label}\n";
            $fail++;
        }
    }

    // Check RTL support
    if (strpos($response, 'dir="rtl"') !== false) {
        echo "[PASS] RTL Direction\n";
        $pass++;
    } else {
        echo "[FAIL] RTL Direction\n";
        $fail++;
    }

    // Check Arabic content
    if (strpos($response, 'محمل') !== false || strpos($response, 'غير محمل') !== false) {
        echo "[PASS] Arabic Content\n";
        $pass++;
    } else {
        echo "[WARN] Arabic Content (may use English labels)\n";
    }

    echo "\n==============================================\n";
    echo "  RESULTS: {$pass} passed, {$fail} failed\n";
    echo "==============================================\n";
} else {
    echo "Failed to load page!\n";
    if ($error) echo "cURL Error: {$error}\n";
    if ($response) echo "Response: " . substr($response, 0, 500) . "\n";
}
