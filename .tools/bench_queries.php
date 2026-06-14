<?php
/**
 * Top 50 Queries + EXPLAIN Analyzer
 * Enables MySQL profiling, runs a representative workload, captures profiles
 */
require_once __DIR__ . '/bench_lib.php';

echo "============================================" . PHP_EOL;
echo "  TOP 50 SLOW QUERIES + EXPLAIN ANALYSIS" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

$pdo = bench_pdo();

// Enable slow query log for real capture
$pdo->exec("SET GLOBAL slow_query_log = ON");
$pdo->exec("SET GLOBAL long_query_time = 0");
$pdo->exec("SET GLOBAL log_queries_not_using_indexes = ON");
echo "[*] Slow query log enabled (captures ALL queries)" . PHP_EOL;

// Run representative workload
echo "[*] Running representative workload..." . PHP_EOL;

// Collect the key queries the app runs
$representativeQueries = [];

// 1. Dashboard queries
$representativeQueries['Dashboard - Product Summary'] = "SELECT COUNT(*) AS total_products, SUM(CASE WHEN p_is_active = 1 THEN 1 ELSE 0 END) AS active_products, SUM(CASE WHEN p_is_featured = 1 THEN 1 ELSE 0 END) AS featured_products, SUM(CASE WHEN p_is_active = 0 THEN 1 ELSE 0 END) AS inactive_products, SUM(CASE WHEN p_qty <= 0 THEN 1 ELSE 0 END) AS out_of_stock_products, SUM(CASE WHEN p_qty BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock_products, SUM(CASE WHEN p_qty > 5 THEN 1 ELSE 0 END) AS healthy_stock_products, COALESCE(SUM(p_qty), 0) AS total_units, COALESCE(SUM(COALESCE(purchase_price, 0) * p_qty), 0) AS inventory_cost, COALESCE(SUM(CAST(NULLIF(p_current_price, '') AS DECIMAL(12,2)) * p_qty), 0) AS inventory_value FROM tbl_product";

$representativeQueries['Dashboard - Pending Orders Count'] = "SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Pending'";
$representativeQueries['Dashboard - Confirmed Orders Count'] = "SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Confirmed'";
$representativeQueries['Dashboard - Completed Today Revenue'] = "SELECT COALESCE(SUM(total_price), 0) FROM tbl_order WHERE order_status = 'Completed' AND DATE(order_date) = CURDATE()";

// 2. Order queries
$representativeQueries['Orders - Full List'] = "SELECT * FROM tbl_order ORDER BY id DESC LIMIT 50";
$representativeQueries['Orders - Status Counts'] = "SELECT order_status, COUNT(*) AS cnt FROM tbl_order GROUP BY order_status ORDER BY cnt DESC";

// 3. Order details
$representativeQueries['Order Details - Main Order'] = "SELECT * FROM tbl_order WHERE id = 1";
$representativeQueries['Order Details - Order Items'] = "SELECT * FROM tbl_order_details WHERE order_id = 1";
$representativeQueries['Order Details - Status Log'] = "SELECT * FROM tbl_order_status_log WHERE order_id = 1 ORDER BY created_at DESC";
$representativeQueries['Order Details - Call Log'] = "SELECT * FROM tbl_order_call_log WHERE order_id = 1 ORDER BY called_at DESC";

// 4. Customer queries
$representativeQueries['Customers - List'] = "SELECT * FROM tbl_customer ORDER BY id DESC LIMIT 50";
$representativeQueries['Customers - Active Count'] = "SELECT COUNT(*) FROM tbl_customer WHERE is_active = 1";

// 5. Product queries
$representativeQueries['Products - List'] = "SELECT * FROM tbl_product ORDER BY id DESC LIMIT 50";

// 6. Employee queries
$representativeQueries['Employees - All Active'] = "SELECT * FROM tbl_employee WHERE is_active = 1";
$representativeQueries['Employees - Ranking Query'] = "SELECT e.id, e.full_name, e.email, e.telegram_chat_id, COUNT(oa.id) AS total_assigned, COALESCE(SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_count, COALESCE(SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_count, COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_count, COALESCE(SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count, COALESCE(SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END), 0) AS returned_count, COALESCE(SUM(CASE WHEN o.order_status IN ('Completed', 'Cancelled', 'Returned') THEN 1 ELSE 0 END), 0) AS processed_count, COALESCE(AVG(CASE WHEN o.order_status IN ('Completed', 'Cancelled', 'Returned') THEN TIMESTAMPDIFF(HOUR, o.order_date, COALESCE(o.last_update, NOW())) END), 0) AS avg_processing_hours FROM tbl_employee e LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active' LEFT JOIN tbl_order o ON o.id = oa.order_id WHERE e.is_active = 1 GROUP BY e.id, e.full_name, e.email, e.telegram_chat_id";

// 7. Audit log
$representativeQueries['Audit Log - Full'] = "SELECT * FROM tbl_audit_log ORDER BY id DESC LIMIT 100";

// 8. Recovery queries
$representativeQueries['Recovery - Dashboard Tasks'] = "SELECT * FROM tbl_recovery_tasks ORDER BY id DESC LIMIT 50";

// 9. Ecotrack queries
$representativeQueries['Ecotrack - Delivery Stats Scan'] = "SELECT ecotrack_remote_status, order_date FROM tbl_order WHERE ecotrack_tracking IS NOT NULL AND ecotrack_tracking != ''";

// 10. Category queries
$representativeQueries['Top Categories'] = "SELECT * FROM tbl_top_category";
$representativeQueries['Mid Categories'] = "SELECT * FROM tbl_mid_category";

// 11. Commission queries
$representativeQueries['Commission - Payment Sum'] = "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM tbl_commission_payment WHERE employee_id = 1";

// 12. Performance KPIs
$representativeQueries['Performance - Employee KPIs'] = "SELECT COUNT(*) AS total_assigned, COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed, COALESCE(SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END), 0) AS confirmed, COALESCE(SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled, COALESCE(SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END), 0) AS returned, COALESCE(AVG(TIMESTAMPDIFF(HOUR, o.order_date, COALESCE(o.last_update, NOW()))), 0) AS avg_processing_hours, COALESCE(SUM(COALESCE(o.total_price, 0)), 0) AS total_revenue FROM tbl_order_assignment oa JOIN tbl_order o ON o.id = oa.order_id WHERE oa.employee_id = 1";

// 13. Store stats
$representativeQueries['Store - 7 Day Sales'] = "SELECT DATE(order_date) AS day_key, COUNT(*) AS order_count, COALESCE(SUM(CASE WHEN order_status IN ('Completed', 'Confirmed') THEN total_price ELSE 0 END), 0) AS revenue_total FROM tbl_order WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(order_date) ORDER BY DATE(order_date) ASC";

// 14. Cache service
$representativeQueries['Cache - Read'] = "SELECT `value`, expires_at FROM tbl_cache WHERE `key` = 'benchmark_test' LIMIT 1";
$representativeQueries['Cache - Write'] = "INSERT INTO tbl_cache (`key`, `value`, expires_at) VALUES ('benchmark_test', '1', DATE_ADD(NOW(), INTERVAL 60 SECOND)) ON DUPLICATE KEY UPDATE `value` = '1', expires_at = DATE_ADD(NOW(), INTERVAL 60 SECOND)";

// 15. Search-style LIKE queries
$representativeQueries['Search - LIKE on customer name'] = "SELECT * FROM tbl_customer WHERE full_name LIKE '%test%' LIMIT 20";
$representativeQueries['Search - LIKE on order status'] = "SELECT * FROM tbl_order WHERE order_status LIKE '%end%' LIMIT 20";
$representativeQueries['Search - LIKE on product name'] = "SELECT * FROM tbl_product WHERE p_name LIKE '%test%' LIMIT 20";

// Run each query through EXPLAIN + profile
echo PHP_EOL;
echo str_pad('Query', 50) . str_pad('Time(ms)', 12) . str_pad('Rows', 10) . str_pad('Type', 14) . str_pad('Key', 20) . str_pad('Extra', 30) . PHP_EOL;
echo str_repeat('-', 136) . PHP_EOL;

$allResults = [];
$slowQueries = []; // > 100ms

foreach ($representativeQueries as $name => $sql) {
    // Measure execution time
    $start = microtime(true);
    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rowCount = count($rows);
        $timeMs = round((microtime(true) - $start) * 1000, 4);
        $success = true;
    } catch (Exception $e) {
        $timeMs = round((microtime(true) - $start) * 1000, 4);
        $rowCount = 0;
        $success = false;
        $errorMsg = $e->getMessage();
    }

    // Run EXPLAIN
    $explainOutput = '';
    $explainData = [];
    if ($success) {
        try {
            $expStmt = $pdo->prepare("EXPLAIN $sql");
            $expStmt->execute();
            $explainData = $expStmt->fetchAll(PDO::FETCH_ASSOC);
            $explainOutput = json_encode($explainData);
        } catch (Exception $e) {
            $explainOutput = 'EXPLAIN failed: ' . $e->getMessage();
        }
    }

    // Parse key EXPLAIN info
    $accessType = '-';
    $keyUsed = '-';
    $extraInfo = '-';
    $rowsExamined = 0;
    if (!empty($explainData)) {
        $info = $explainData[0];
        $accessType = $info['type'] ?? '-';
        $keyUsed = $info['key'] ?? '-';
        $extraInfo = $info['Extra'] ?? '-';
        $rowsExamined = (int) ($info['rows'] ?? 0);
    }

    echo str_pad(substr($name, 0, 49), 50)
       . str_pad($timeMs, 12)
       . str_pad($rowCount, 10)
       . str_pad($accessType, 14)
       . str_pad(substr($keyUsed, 0, 19), 20)
       . str_pad(substr($extraInfo, 0, 29), 30)
       . PHP_EOL;

    if (!$success) {
        echo "  [ERROR] " . ($errorMsg ?? 'unknown') . PHP_EOL;
    }

    if ($timeMs > 100) {
        $slowQueries[] = [
            'name' => $name,
            'sql' => substr($sql, 0, 200),
            'time_ms' => $timeMs,
            'rows_returned' => $rowCount,
            'access_type' => $accessType,
            'key_used' => $keyUsed,
            'extra' => $extraInfo,
            'rows_examined' => $rowsExamined,
            'explain' => $explainData,
        ];
    }

    $allResults[] = [
        'name' => $name,
        'sql' => substr($sql, 0, 500),
        'time_ms' => $timeMs,
        'rows_returned' => $rowCount,
        'success' => $success,
        'access_type' => $accessType,
        'key_used' => $keyUsed,
        'extra' => $extraInfo,
        'rows_examined' => $rowsExamined,
        'explain' => $explainOutput,
    ];
}

echo PHP_EOL;
echo "============================================" . PHP_EOL;
echo "  SLOW QUERIES (>100ms)" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

if (empty($slowQueries)) {
    echo "No queries >100ms found." . PHP_EOL;
} else {
    usort($slowQueries, fn($a, $b) => $b['time_ms'] <=> $a['time_ms']);
    echo str_pad('Query', 50) . str_pad('Time(ms)', 12) . str_pad('Rows', 10) . str_pad('Type', 14) . str_pad('Key', 20) . str_pad('Extra', 30) . PHP_EOL;
    echo str_repeat('-', 136) . PHP_EOL;
    foreach ($slowQueries as $q) {
        echo str_pad(substr($q['name'], 0, 49), 50)
           . str_pad($q['time_ms'], 12)
           . str_pad($q['rows_returned'], 10)
           . str_pad($q['access_type'], 14)
           . str_pad(substr($q['key_used'], 0, 19), 20)
           . str_pad(substr($q['extra'], 0, 29), 30)
           . PHP_EOL;

        // Show EXPLAIN details
        if (!empty($q['explain'])) {
            foreach ($q['explain'] as $exp) {
                echo "  -> table: " . ($exp['table'] ?? '-')
                   . " | type: " . ($exp['type'] ?? '-')
                   . " | possible_keys: " . ($exp['possible_keys'] ?? '-')
                   . " | key: " . ($exp['key'] ?? '-')
                   . " | rows: " . ($exp['rows'] ?? '-')
                   . " | filtered: " . ($exp['filtered'] ?? '-')
                   . " | Extra: " . ($exp['Extra'] ?? '-')
                   . PHP_EOL;
            }
        }
        echo PHP_EOL;
    }
}

// Summary statistics
$allTimes = array_column($allResults, 'time_ms');
$totalTime = round(array_sum($allTimes), 2);
$avgTime = round($totalTime / count($allTimes), 4);
$maxTime = round(max($allTimes), 4);

echo PHP_EOL;
echo "Query Summary:" . PHP_EOL;
echo "  Total queries tested: " . count($allResults) . PHP_EOL;
echo "  Total query time: {$totalTime}ms" . PHP_EOL;
echo "  Average query time: {$avgTime}ms" . PHP_EOL;
echo "  Max query time: {$maxTime}ms" . PHP_EOL;
echo "  Queries > 100ms: " . count($slowQueries) . PHP_EOL;

// Detect issues
echo PHP_EOL;
echo "Issues Detected:" . PHP_EOL;
$fullScans = array_filter($allResults, fn($r) => $r['access_type'] === 'ALL');
$usingFilesort = array_filter($allResults, fn($r) => strpos($r['extra'] ?? '', 'Using filesort') !== false);
$usingTemporary = array_filter($allResults, fn($r) => strpos($r['extra'] ?? '', 'Using temporary') !== false);
$noIndex = array_filter($allResults, fn($r) => $r['key_used'] === '-' || $r['key_used'] === 'NULL' || empty($r['key_used']));

echo "  Full table scans: " . count($fullScans) . PHP_EOL;
foreach ($fullScans as $q) echo "    - {$q['name']}: {$q['access_type']} (rows: {$q['rows_examined']})" . PHP_EOL;

echo "  Using filesort: " . count($usingFilesort) . PHP_EOL;
foreach ($usingFilesort as $q) echo "    - {$q['name']}" . PHP_EOL;

echo "  Using temporary: " . count($usingTemporary) . PHP_EOL;
foreach ($usingTemporary as $q) echo "    - {$q['name']}" . PHP_EOL;

// Disable slow log
$pdo->exec("SET GLOBAL slow_query_log = OFF");

// Save results
file_put_contents(__DIR__ . '/bench_queries_results.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'all_queries' => $allResults,
    'slow_queries' => $slowQueries,
    'full_scans' => count($fullScans),
    'using_filesort' => count($usingFilesort),
    'using_temporary' => count($usingTemporary),
    'no_index' => count($noIndex),
], JSON_PRETTY_PRINT));

echo PHP_EOL . "[*] Results saved to bench_queries_results.json" . PHP_EOL;
