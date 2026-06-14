<?php
/**
 * AI Insights - Full Functional Test (bypasses auth)
 */

echo "==============================================\n";
echo "  AI Insights - Full Functional Test\n";
echo "==============================================\n\n";

$pass = 0;
$fail = 0;

function check($label, $found, $detail = '') {
    global $pass, $fail;
    if ($found) { $pass++; echo "[PASS] {$label}" . ($detail ? " - {$detail}" : "") . "\n"; }
    else { $fail++; echo "[FAIL] {$label}" . ($detail ? " - {$detail}" : "") . "\n"; }
}

// Test 1: RTL in header.php
echo "--- Test 1: RTL Fix in header.php ---\n";
$header = file_get_contents(__DIR__ . '/../admin/header.php');
check('dir="rtl"', strpos($header, 'dir="rtl"') !== false);
check('lang="ar"', strpos($header, 'lang="ar"') !== false);
check('No dir="ltr"', strpos($header, 'dir="ltr"') === false);
check('No lang="fr"', strpos($header, 'lang="fr"') === false);

// Test 2: Database & Functions
echo "\n--- Test 2: Database & Functions ---\n";
$dbhost = 'localhost';
$dbname = 'boomtsvp_boomtsvp_ecommerceweb';
$dbuser = 'root';
$dbpass = '';
try {
    $pdo = new PDO("mysql:host={$dbhost};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    check('Database connection', true);
} catch (PDOException $e) {
    check('Database connection', false, $e->getMessage());
    die("\nCannot continue without DB.\n");
}

require_once __DIR__ . '/../admin/inc/ai_functions.php';
require_once __DIR__ . '/../admin/inc/performance_functions.php';

$funcs = [
    'ai_ensure_tables', 'ai_save_report', 'ai_get_last_report',
    'ai_analyze_cancellations', 'ai_analyze_product_risk',
    'ai_analyze_employee_performance', 'ai_analyze_wilayas',
    'ai_analyze_offers', 'ai_analyze_response_time',
    'ai_forecast_revenue', 'ai_predict_returns',
    'ai_build_morning_report', 'ai_send_morning_report',
    'performance_ensure_tables', 'performance_calculate_score',
    'performance_get_ranking',
];
foreach ($funcs as $fn) {
    check("Function: {$fn}", function_exists($fn));
}

// Test 3: Tables
echo "\n--- Test 3: Database Tables ---\n";
$tables = ['tbl_ai_reports', 'tbl_order', 'tbl_employee', 'tbl_order_assignment', 'tbl_order_cancellation_reason', 'tbl_employee_commission', 'tbl_commission_payment', 'tbl_performance_settings'];
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$t}'");
    check("Table: {$t}", $stmt->fetch() !== false);
}

// Test 4: Execute All Analyses
echo "\n--- Test 4: Execute All 8 Analyses ---\n";
$analyses = [
    ['ai_analyze_cancellations', [$pdo, 90], 'cancellation_analysis'],
    ['ai_analyze_product_risk', [$pdo, 90], 'product_risk'],
    ['ai_analyze_employee_performance', [$pdo, 90], 'employee_performance'],
    ['ai_analyze_wilayas', [$pdo, 180], 'wilaya_analysis'],
    ['ai_analyze_offers', [$pdo, 90], 'offer_analysis'],
    ['ai_analyze_response_time', [$pdo, 90], 'response_time'],
    ['ai_forecast_revenue', [$pdo], 'revenue_forecast'],
    ['ai_predict_returns', [$pdo, 180], 'return_prediction'],
];

foreach ($analyses as [$fn, $args, $type]) {
    try {
        $result = call_user_func_array($fn, $args);
        check("{$fn}", is_array($result), "Data: " . json_encode(array_keys($result)));
        
        // Verify saved to DB
        $stmt = $pdo->prepare("SELECT id FROM tbl_ai_reports WHERE report_type = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$type]);
        $saved = $stmt->fetch() !== false;
        check("  Saved to tbl_ai_reports ({$type})", $saved);
    } catch (Exception $e) {
        check("{$fn}", false, $e->getMessage());
    }
}

// Test 5: Revenue Forecast Logic
echo "\n--- Test 5: Revenue Forecast Logic ---\n";
$forecast = ai_forecast_revenue($pdo);
check('forecast has 7_days', isset($forecast['forecast']['7_days']));
check('forecast has 30_days', isset($forecast['forecast']['30_days']));
check('forecast has 90_days', isset($forecast['forecast']['90_days']));
check('forecast has trend', isset($forecast['trend']));
check('forecast has historical', isset($forecast['historical']) && is_array($forecast['historical']));
check('trend is valid', in_array($forecast['trend'], ['up', 'down', 'stable']));
check('7_days >= 0', $forecast['forecast']['7_days'] >= 0);
check('30_days >= 0', $forecast['forecast']['30_days'] >= 0);
check('90_days >= 30_days', $forecast['forecast']['90_days'] >= $forecast['forecast']['30_days']);

// Test 6: Product Risk Logic
echo "\n--- Test 6: Product Risk Logic ---\n";
$risk = ai_analyze_product_risk($pdo);
check('risk has products', isset($risk['products']));
check('risk has summary', isset($risk['summary']));
check('summary has low', isset($risk['summary']['low']));
check('summary has medium', isset($risk['summary']['medium']));
check('summary has high', isset($risk['summary']['high']));
if (!empty($risk['products'])) {
    $first = $risk['products'][0];
    check('product has risk_level', isset($first['risk_level']));
    check('risk_level is valid', in_array($first['risk_level'], ['low', 'medium', 'high']));
    check('product has risk_score', isset($first['risk_score']));
}

// Test 7: Employee Performance Logic
echo "\n--- Test 7: Employee Performance Logic ---\n";
$emp = ai_analyze_employee_performance($pdo);
check('has employees', isset($emp['employees']));
check('has averages', isset($emp['averages']));
check('has recommendations', isset($emp['recommendations']) && is_array($emp['recommendations']));
check('averages has delivery_rate', isset($emp['averages']['delivery_rate']));
check('averages has cancel_rate', isset($emp['averages']['cancel_rate']));

// Test 8: Morning Report Build
echo "\n--- Test 8: Morning Report Build ---\n";
try {
    $report = ai_build_morning_report($pdo);
    check('report is string', is_string($report));
    check('report not empty', strlen($report) > 100);
    check('report has Arabic', preg_match('/[\x{0600}-\x{06FF}]/u', $report));
} catch (Exception $e) {
    check('build_morning_report', false, $e->getMessage());
}

// Test 9: Page HTML Structure
echo "\n--- Test 9: Page HTML Structure ---\n";
$page = file_get_contents(__DIR__ . '/../admin/ai-insights.php');
check('has content-header', strpos($page, 'content-header') !== false);
check('has run_all action', strpos($page, 'run_all') !== false);
check('has send_report action', strpos($page, 'send_report') !== false);
check('has Chart.js script', strpos($page, 'chart.js') !== false || strpos($page, 'Chart') !== false);
check('has revenueChart canvas', strpos($page, 'revenueChart') !== false);
check('8 analysis sections', substr_count($page, 'ai_section_header') === 8);

echo "\n==============================================\n";
echo "  RESULTS: {$pass} passed, {$fail} failed\n";
echo "==============================================\n";

exit($fail > 0 ? 1 : 0);
