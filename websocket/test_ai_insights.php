<?php
/**
 * Test AI Insights Page
 */

echo "==============================================\n";
echo "  AI Insights Page Test\n";
echo "==============================================\n\n";

// Test 1: PHP Syntax
echo "--- Test 1: PHP Syntax ---\n";
$files = [
    'ai-insights.php',
    'inc/ai_functions.php',
    'inc/performance_functions.php',
];
$allPassed = true;
foreach ($files as $f) {
    $path = __DIR__ . '/../admin/' . $f;
    $output = [];
    $returnCode = 0;
    exec('"C:\\xampp\\php\\php.exe" -l "' . $path . '" 2>&1', $output, $returnCode);
    $passed = $returnCode === 0 && strpos(implode($output), 'No syntax errors') !== false;
    echo ($passed ? "[PASS]" : "[FAIL]") . " {$f}\n";
    if (!$passed) {
        $allPassed = false;
        echo "  Output: " . implode($output) . "\n";
    }
}

// Test 2: Database Connection
echo "\n--- Test 2: Database Connection ---\n";
require_once __DIR__ . '/../admin/inc/config.php';
try {
    $pdo = new PDO("mysql:host={$dbhost};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[PASS] Database connected\n";
} catch (PDOException $e) {
    echo "[FAIL] Database connection: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 3: Required Tables
echo "\n--- Test 3: Required Tables ---\n";
if (isset($pdo)) {
    $requiredTables = [
        'tbl_ai_reports',
        'tbl_order',
        'tbl_employee',
        'tbl_order_assignment',
        'tbl_order_cancellation_reason',
        'tbl_telegram_action_log',
    ];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->fetch() !== false;
        echo ($exists ? "[PASS]" : "[WARN]") . " Table: {$table}\n";
        if (!$exists) $allPassed = false;
    }
}

// Test 4: AI Functions
echo "\n--- Test 4: AI Functions ---\n";
if (isset($pdo)) {
    require_once __DIR__ . '/../admin/inc/ai_functions.php';
    require_once __DIR__ . '/../admin/inc/performance_functions.php';
    
    $functions = [
        'ai_ensure_tables',
        'ai_save_report',
        'ai_get_last_report',
        'ai_analyze_cancellations',
        'ai_analyze_product_risk',
        'ai_analyze_employee_performance',
        'ai_analyze_wilayas',
        'ai_analyze_offers',
        'ai_analyze_response_time',
        'ai_forecast_revenue',
        'ai_predict_returns',
        'ai_build_morning_report',
        'ai_send_morning_report',
        'performance_ensure_tables',
        'performance_calculate_score',
        'performance_get_ranking',
    ];
    
    foreach ($functions as $fn) {
        $exists = function_exists($fn);
        echo ($exists ? "[PASS]" : "[FAIL]") . " Function: {$fn}\n";
        if (!$exists) $allPassed = false;
    }
}

// Test 5: Execute AI Tables Creation
echo "\n--- Test 5: Create AI Tables ---\n";
if (isset($pdo)) {
    try {
        ai_ensure_tables($pdo);
        performance_ensure_tables($pdo);
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_ai_reports'");
        $exists = $stmt->fetch() !== false;
        echo ($exists ? "[PASS]" : "[FAIL]") . " tbl_ai_reports created\n";
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_employee_commission'");
        $exists = $stmt->fetch() !== false;
        echo ($exists ? "[PASS]" : "[FAIL]") . " tbl_employee_commission created\n";
    } catch (Exception $e) {
        echo "[FAIL] " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

// Test 6: Run Analysis (sample)
echo "\n--- Test 6: Run Sample Analysis ---\n";
if (isset($pdo)) {
    try {
        $result = ai_analyze_cancellations($pdo, 30);
        $passed = is_array($result) && isset($result['total_cancellations']);
        echo ($passed ? "[PASS]" : "[FAIL]") . " ai_analyze_cancellations\n";
        if ($passed) echo "  Total cancellations: {$result['total_cancellations']}\n";
        
        $result = ai_analyze_product_risk($pdo, 30);
        $passed = is_array($result) && isset($result['products']);
        echo ($passed ? "[PASS]" : "[FAIL]") . " ai_analyze_product_risk\n";
        if ($passed) echo "  Products analyzed: " . count($result['products']) . "\n";
        
        $result = ai_forecast_revenue($pdo);
        $passed = is_array($result) && isset($result['forecast']);
        echo ($passed ? "[PASS]" : "[FAIL]") . " ai_forecast_revenue\n";
        if ($passed) echo "  7-day forecast: " . number_format($result['forecast']['7_days']) . " DZD\n";
    } catch (Exception $e) {
        echo "[FAIL] " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

// Test 7: HTTP Access
echo "\n--- Test 7: HTTP Access ---\n";
$url = 'http://127.0.0.1/ecom/admin/ai-insights.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 302 = redirect to login (expected if not logged in)
// 200 = page loaded
$passed = in_array($httpCode, [200, 302]);
echo ($passed ? "[PASS]" : "[FAIL]") . " HTTP Status: {$httpCode}\n";
if ($httpCode === 200) {
    echo "  Page size: " . strlen($response) . " bytes\n";
    if (strpos($response, 'الذكاء الاصطناعي') !== false) echo "  [OK] Arabic title found\n";
    if (strpos($response, 'تحليل أسباب الإلغاء') !== false) echo "  [OK] Cancellation analysis section\n";
    if (strpos($response, 'مخاطر المنتجات') !== false) echo "  [OK] Product risk section\n";
    if (strpos($response, 'تحليل أداء الموظفين') !== false) echo "  [OK] Employee performance section\n";
    if (strpos($response, 'تحليل الولايات') !== false) echo "  [OK] Wilaya analysis section\n";
    if (strpos($response, 'تحليل العروض') !== false) echo "  [OK] Offers analysis section\n";
    if (strpos($response, 'زمن الاستجابة') !== false) echo "  [OK] Response time section\n";
    if (strpos($response, 'توقع الإيرادات') !== false) echo "  [OK] Revenue forecast section\n";
    if (strpos($response, 'توقع الإرجاع') !== false) echo "  [OK] Return prediction section\n";
} elseif ($httpCode === 302) {
    echo "  (Redirect to login - expected without session)\n";
}

echo "\n==============================================\n";
echo $allPassed ? "  ALL TESTS PASSED!" : "  SOME TESTS FAILED!";
echo "\n==============================================\n";
