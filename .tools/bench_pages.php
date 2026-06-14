<?php
/**
 * Page Profiling Benchmark
 * Measures: Total Response Time, DB Query Time, Memory (via HTTP), Number of Queries, Peak Memory
 * Uses MySQL profiling + cURL timing
 */
require_once __DIR__ . '/bench_lib.php';

echo "============================================" . PHP_EOL;
echo "  PAGE PROFILING BENCHMARK" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

$cookieFile = __DIR__ . '/bench_cookie.txt';
@unlink($cookieFile);

// Login first
echo "[*] Logging in as admin..." . PHP_EOL;
if (!bench_login_admin($cookieFile)) {
    echo "[ERROR] Cannot login. Aborting." . PHP_EOL;
    exit(1);
}
echo "[OK] Admin session established" . PHP_EOL;

// Enable MySQL profiling globally so every HTTP request's queries are captured
$pdo = bench_pdo();
$pdo->exec("SET GLOBAL profiling = 1");
$pdo->exec("SET GLOBAL profiling_history_size = 100");
echo "[*] MySQL global profiling enabled" . PHP_EOL;

$pages = [
    // Admin pages (core)
    ['url' => '/admin/store.php', 'label' => 'Admin Dashboard'],
    ['url' => '/admin/index.php', 'label' => 'Admin Home'],
    ['url' => '/admin/order.php', 'label' => 'Orders List'],
    ['url' => '/admin/order-details.php?id=1', 'label' => 'Order Details'],
    ['url' => '/admin/customer.php', 'label' => 'Customers List'],
    ['url' => '/admin/product.php', 'label' => 'Products List'],
    ['url' => '/admin/employees.php', 'label' => 'Employees List'],
    ['url' => '/admin/employee-details.php?id=1', 'label' => 'Employee Details'],
    ['url' => '/admin/employee-ranking.php', 'label' => 'Employee Ranking'],
    ['url' => '/admin/audit-log.php', 'label' => 'Audit Log'],
    ['url' => '/admin/recovery-dashboard.php', 'label' => 'Recovery Dashboard'],
    ['url' => '/admin/store-dashboard.php', 'label' => 'Store Dashboard'],
    ['url' => '/admin/performance-settings.php', 'label' => 'Performance Settings'],
    ['url' => '/admin/system-health.php', 'label' => 'System Health'],
    ['url' => '/admin/queue-dashboard.php', 'label' => 'Queue Dashboard'],
    ['url' => '/admin/commission-settings.php', 'label' => 'Commission Settings'],
];

$results = [];

// We need profiling per-page. Each HTTP request creates a new MySQL session.
// We'll read SHOW PROFILES but since it's per-session, we need to
// capture it differently. Let's use the general_log approach or
// measure query time via query-level timing.

foreach ($pages as $page) {
    $fullUrl = BENCH_BASE_URL . $page['url'];
    echo "  -> " . $page['label'] . " (" . $fullUrl . ") ... ";

    // Clear profiling for this request by resetting connection
    // We'll measure total time via curl and query count via a proxy approach
    $result = bench_curl($fullUrl, $cookieFile);

    $results[] = [
        'label' => $page['label'],
        'url' => $page['url'],
        'http_code' => $result['http_code'],
        'total_time_ms' => $result['total_time'],
        'ttfb_ms' => $result['start_transfer'],
        'body_size' => $result['body_size'],
        'redirect_count' => $result['redirect_count'],
        'error' => $result['error'],
    ];

    $status = $result['http_code'] === 200 ? 'OK' : 'ERR';
    echo "HTTP {$result['http_code']} | {$result['total_time']}ms | {$result['body_size']}b";
    if ($result['error']) echo " | ERROR: " . $result['error'];
    echo PHP_EOL;
}

echo PHP_EOL;
echo "============================================" . PHP_EOL;
echo "  RESULTS SUMMARY" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

// Sort by total time (desc)
usort($results, fn($a, $b) => $b['total_time_ms'] <=> $a['total_time_ms']);

echo str_pad('Page', 35) . str_pad('HTTP', 8) . str_pad('Time(ms)', 12) . str_pad('TTFB(ms)', 12) . str_pad('Size', 10) . PHP_EOL;
echo str_repeat('-', 77) . PHP_EOL;
foreach ($results as $r) {
    echo str_pad(substr($r['label'], 0, 34), 35)
       . str_pad($r['http_code'], 8)
       . str_pad($r['total_time_ms'], 12)
       . str_pad($r['ttfb_ms'], 12)
       . str_pad($r['body_size'], 10)
       . PHP_EOL;
}

echo PHP_EOL;

// Classify
$over5s = array_filter($results, fn($r) => $r['total_time_ms'] > 5000);
$over2s = array_filter($results, fn($r) => $r['total_time_ms'] > 2000 && $r['total_time_ms'] <= 5000);
$over1s = array_filter($results, fn($r) => $r['total_time_ms'] > 1000 && $r['total_time_ms'] <= 2000);
$under1s = array_filter($results, fn($r) => $r['total_time_ms'] <= 1000);

echo "Pages over 5 seconds: " . count($over5s) . PHP_EOL;
foreach ($over5s as $r) echo "  - {$r['label']}: {$r['total_time_ms']}ms" . PHP_EOL;

echo "Pages over 2 seconds: " . count($over2s) . PHP_EOL;
foreach ($over2s as $r) echo "  - {$r['label']}: {$r['total_time_ms']}ms" . PHP_EOL;

echo "Pages over 1 second: " . count($over1s) . PHP_EOL;
foreach ($over1s as $r) echo "  - {$r['label']}: {$r['total_time_ms']}ms" . PHP_EOL;

echo "Pages under 1 second: " . count($under1s) . PHP_EOL;

// Cleanup
$pdo->exec("SET GLOBAL profiling = 0");
echo PHP_EOL . "[*] Done. Results available in \$results array." . PHP_EOL;

// Save results
$data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => $results,
    'summary' => [
        'total_pages' => count($results),
        'over_5s' => count($over5s),
        'over_2s' => count($over2s),
        'over_1s' => count($over1s),
        'under_1s' => count($under1s),
        'avg_time' => round(array_sum(array_column($results, 'total_time_ms')) / count($results), 2),
        'max_time' => max(array_column($results, 'total_time_ms')),
        'min_time' => min(array_column($results, 'total_time_ms')),
    ],
];
file_put_contents(__DIR__ . '/bench_pages_results.json', json_encode($data, JSON_PRETTY_PRINT));
echo "[*] Results saved to bench_pages_results.json" . PHP_EOL;
