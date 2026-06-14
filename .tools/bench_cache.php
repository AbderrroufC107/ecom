<?php
/**
 * Cache Effectiveness Benchmark
 * Measures: Cold Cache, Warm Cache, Improvement %
 */
require_once __DIR__ . '/bench_lib.php';

echo "============================================" . PHP_EOL;
echo "  CACHE EFFECTIVENESS BENCHMARK" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

$cookieFile = __DIR__ . '/bench_cookie.txt';
@unlink($cookieFile);

if (!bench_login_admin($cookieFile)) {
    echo "[ERROR] Cannot login. Aborting." . PHP_EOL;
    exit(1);
}

$pdo = bench_pdo();

// Pages to test cache on
$testPages = [
    '/admin/store.php' => 'Admin Dashboard',
    '/admin/index.php' => 'Admin Home',
    '/admin/recovery-dashboard.php' => 'Recovery Dashboard',
    '/admin/store-dashboard.php' => 'Store Dashboard',
    '/admin/system-health.php' => 'System Health',
    '/staff/index.php' => 'Staff Dashboard',
];

echo str_pad('Page', 35) . str_pad('Cold(ms)', 12) . str_pad('Avg Warm(ms)', 14) . str_pad('Improvement', 12) . PHP_EOL;
echo str_repeat('-', 73) . PHP_EOL;

$allResults = [];

foreach ($testPages as $path => $label) {
    $url = BENCH_BASE_URL . $path;

    // Clear cache
    $pdo->exec("DELETE FROM tbl_cache");
    $pdo->exec("DELETE FROM tbl_materialized_stats");
    echo "[CACHE] Cleared for $label" . PHP_EOL;

    // Cold request
    $cold = bench_curl($url, $cookieFile);
    $coldTime = $cold['total_time'];
    echo "  Cold: {$coldTime}ms" . PHP_EOL;

    // Warm requests (back-to-back)
    $warmTimes = [];
    for ($i = 0; $i < 3; $i++) {
        $warm = bench_curl($url, $cookieFile);
        $warmTimes[] = $warm['total_time'];
        usleep(100000);
    }
    $avgWarm = round(array_sum($warmTimes) / count($warmTimes), 2);
    echo "  Warm: " . implode('ms, ', $warmTimes) . "ms (avg: {$avgWarm}ms)" . PHP_EOL;

    $improvement = $coldTime > 0
        ? round((1 - $avgWarm / $coldTime) * 100, 1)
        : 0;
    echo "  Improvement: {$improvement}%" . PHP_EOL;
    echo PHP_EOL;

    echo str_pad(substr($label, 0, 34), 35)
       . str_pad($coldTime, 12)
       . str_pad($avgWarm, 14)
       . str_pad($improvement . '%', 12)
       . PHP_EOL;

    $allResults[$label] = [
        'cold_time' => $coldTime,
        'warm_times' => $warmTimes,
        'avg_warm_time' => $avgWarm,
        'improvement_pct' => $improvement,
    ];
}

echo PHP_EOL . "Cache benchmark complete." . PHP_EOL;

// Save
file_put_contents(__DIR__ . '/bench_cache_results.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => $allResults,
], JSON_PRETTY_PRINT));

echo "[*] Results saved to bench_cache_results.json" . PHP_EOL;
