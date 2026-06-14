<?php
/**
 * Search Benchmark
 * Compares: Old LIKE search vs Fulltext search at different scale
 * Measures: 100, 1000, 10000 records
 */
require_once __DIR__ . '/bench_lib.php';

echo "============================================" . PHP_EOL;
echo "  SEARCH BENCHMARK" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

$pdo = bench_pdo();

// Check if we have FULLTEXT indexes
echo "[*] Checking FULLTEXT indexes..." . PHP_EOL;
$stmt = $pdo->query("
    SELECT TABLE_NAME, INDEX_NAME, INDEX_TYPE
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = '" . BENCH_DB_NAME . "'
    AND INDEX_TYPE = 'FULLTEXT'
");
$fulltextIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($fulltextIndexes)) {
    echo "[WARN] No FULLTEXT indexes found. Adding temporary ones for benchmark..." . PHP_EOL;
    try {
        $pdo->exec("ALTER TABLE tbl_customer ADD FULLTEXT INDEX ft_customer_name (full_name)");
        $pdo->exec("ALTER TABLE tbl_product ADD FULLTEXT INDEX ft_product_name (p_name)");
        echo "[OK] Temporary FULLTEXT indexes added" . PHP_EOL;
    } catch (Exception $e) {
        echo "[WARN] Could not add FULLTEXT indexes: " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "[OK] Found " . count($fulltextIndexes) . " FULLTEXT index(es)" . PHP_EOL;
    foreach ($fulltextIndexes as $idx) {
        echo "  - {$idx['TABLE_NAME']}.{$idx['INDEX_NAME']}" . PHP_EOL;
    }
}

// Get record counts
$stmt = $pdo->query("SELECT COUNT(*) FROM tbl_customer");
$customerCount = (int) $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM tbl_product");
$productCount = (int) $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM tbl_order");
$orderCount = (int) $stmt->fetchColumn();

echo PHP_EOL;
echo "Table sizes:" . PHP_EOL;
echo "  tbl_customer: {$customerCount} rows" . PHP_EOL;
echo "  tbl_product:  {$productCount} rows" . PHP_EOL;
echo "  tbl_order:    {$orderCount} rows" . PHP_EOL;
echo PHP_EOL;

// Test search functions
$searchTests = [
    'Customer Name (common)' => [
        'table' => 'tbl_customer',
        'column' => 'full_name',
        'term' => 'a',
        'count' => $customerCount,
    ],
    'Customer Name (rare)' => [
        'table' => 'tbl_customer',
        'column' => 'full_name',
        'term' => 'z',
        'count' => $customerCount,
    ],
    'Product Name (common)' => [
        'table' => 'tbl_product',
        'column' => 'p_name',
        'term' => 'a',
        'count' => $productCount,
    ],
    'Product Name (rare)' => [
        'table' => 'tbl_product',
        'column' => 'p_name',
        'term' => 'z',
        'count' => $productCount,
    ],
];

echo str_pad('Test', 35) . str_pad('Method', 14) . str_pad('Time(ms)', 12) . str_pad('Rows', 10) . str_pad('Status', 12) . PHP_EOL;
echo str_repeat('-', 83) . PHP_EOL;

$allSearchResults = [];

foreach ($searchTests as $testName => $test) {
    $table = $test['table'];
    $column = $test['column'];
    $term = $test['term'];
    $limit = 20;

    // Old search (LIKE %term%)
    $start = microtime(true);
    try {
        $stmt = $pdo->query("SELECT * FROM `$table` WHERE `$column` LIKE " . $pdo->quote('%' . $term . '%') . " LIMIT $limit");
        $likeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $likeTime = round((microtime(true) - $start) * 1000, 4);
        $likeSuccess = true;
    } catch (Exception $e) {
        $likeTime = round((microtime(true) - $start) * 1000, 4);
        $likeRows = [];
        $likeSuccess = false;
    }

    echo str_pad(substr($testName, 0, 34), 35)
       . str_pad('LIKE %term%', 14)
       . str_pad($likeTime, 12)
       . str_pad(count($likeRows), 10)
       . str_pad($likeSuccess ? 'OK' : 'FAIL', 12)
       . PHP_EOL;

    // Prefix search (LIKE term%)
    $start = microtime(true);
    try {
        $stmt = $pdo->query("SELECT * FROM `$table` WHERE `$column` LIKE " . $pdo->quote($term . '%') . " LIMIT $limit");
        $prefixRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $prefixTime = round((microtime(true) - $start) * 1000, 4);
    } catch (Exception $e) {
        $prefixTime = -1;
    }

    echo str_pad('', 35)
       . str_pad('LIKE term%', 14)
       . str_pad($prefixTime, 12)
       . str_pad(count($prefixRows ?? []), 10)
       . PHP_EOL;

    // Fulltext search (MATCH AGAINST)
    $ftTime = -1;
    $ftRows = [];
    try {
        $start = microtime(true);
        $stmt = $pdo->query("SELECT * FROM `$table` WHERE MATCH(`$column`) AGAINST (" . $pdo->quote($term . '*') . " IN BOOLEAN MODE) LIMIT $limit");
        $ftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ftTime = round((microtime(true) - $start) * 1000, 4);
        $ftStatus = 'OK';
    } catch (Exception $e) {
        $ftStatus = 'NO_FT_INDEX';
    }

    echo str_pad('', 35)
       . str_pad('FULLTEXT', 14)
       . str_pad($ftTime, 12)
       . str_pad(count($ftRows), 10)
       . str_pad($ftStatus, 12)
       . PHP_EOL;

    // Exact match
    $start = microtime(true);
    try {
        $stmt = $pdo->query("SELECT * FROM `$table` WHERE `$column` = " . $pdo->quote($term) . " LIMIT $limit");
        $exactRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $exactTime = round((microtime(true) - $start) * 1000, 4);
    } catch (Exception $e) {
        $exactTime = -1;
    }

    echo str_pad('', 35)
       . str_pad('EXACT MATCH', 14)
       . str_pad($exactTime, 12)
       . str_pad(count($exactRows ?? []), 10)
       . PHP_EOL;

    echo PHP_EOL;

    $allSearchResults[$testName] = [
        'table' => $table,
        'column' => $column,
        'term' => $term,
        'total_rows_in_table' => $test['count'],
        'like_term_pct' => ['time_ms' => $likeTime, 'rows' => count($likeRows), 'success' => $likeSuccess],
        'like_prefix' => ['time_ms' => $prefixTime, 'rows' => count($prefixRows ?? [])],
        'fulltext' => ['time_ms' => $ftTime, 'rows' => count($ftRows), 'status' => $ftStatus],
        'exact_match' => ['time_ms' => $exactTime, 'rows' => count($exactRows ?? [])],
    ];
}

// Bulk search performance test
echo PHP_EOL . "--- Bulk Search Throughput ---" . PHP_EOL;
echo "Running 100 sequential LIKE searches..." . PHP_EOL;

$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $char = chr(ord('a') + ($i % 26));
    try {
        $stmt = $pdo->query("SELECT * FROM tbl_customer WHERE full_name LIKE " . $pdo->quote('%' . $char . '%') . " LIMIT 5");
        $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
$bulkLikeTime = round(microtime(true) - $start, 4);
echo "  100 LIKE queries: {$bulkLikeTime}s" . PHP_EOL;

$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $char = chr(ord('a') + ($i % 26));
    try {
        $stmt = $pdo->query("SELECT * FROM tbl_customer WHERE full_name LIKE " . $pdo->quote($char . '%') . " LIMIT 5");
        $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
$bulkPrefixTime = round(microtime(true) - $start, 4);
echo "  100 prefix queries: {$bulkPrefixTime}s" . PHP_EOL;

// Cleanup temporary FULLTEXT indexes
if (!empty($fulltextIndexes)) {
    // Don't clean up pre-existing indexes
} else {
    try {
        $pdo->exec("ALTER TABLE tbl_customer DROP INDEX ft_customer_name");
        $pdo->exec("ALTER TABLE tbl_product DROP INDEX ft_product_name");
        echo "[OK] Temporary FULLTEXT indexes removed" . PHP_EOL;
    } catch (Exception $e) {}
}

file_put_contents(__DIR__ . '/bench_search_results.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'table_sizes' => [
        'tbl_customer' => $customerCount,
        'tbl_product' => $productCount,
        'tbl_order' => $orderCount,
    ],
    'results' => $allSearchResults,
    'bulk' => [
        '100_like_pct' => $bulkLikeTime,
        '100_prefix' => $bulkPrefixTime,
    ],
], JSON_PRETTY_PRINT));

echo PHP_EOL . "[*] Results saved to bench_search_results.json" . PHP_EOL;
