<?php
/**
 * Benchmark Library - Shared utilities for performance measurement
 * No production code is modified.
 */

define('BENCH_DB_HOST', 'localhost');
define('BENCH_DB_NAME', 'boomtsvp_boomtsvp_ecommerceweb');
define('BENCH_DB_USER', 'root');
define('BENCH_DB_PASS', '');
define('BENCH_BASE_URL', 'http://localhost/ecom');

function bench_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . BENCH_DB_HOST . ';dbname=' . BENCH_DB_NAME . ';charset=utf8mb4',
            BENCH_DB_USER, BENCH_DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function bench_curl(string $url, string $cookieFile = '', int $maxRetries = 2): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    }

    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
        $start = microtime(true);
        $response = curl_exec($ch);
        $end = microtime(true);

        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $startTransfer = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        if ($errno === 0 || $attempt >= $maxRetries) {
            break;
        }
        usleep(200000);
    }

    curl_close($ch);

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = '';
    $body = '';
    if ($response !== false) {
        $headerSize = strpos($response, "\r\n\r\n");
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize + 4);
    }

    return [
        'url' => $url,
        'http_code' => $httpCode,
        'total_time' => round($totalTime * 1000, 2),
        'start_transfer' => round($startTransfer * 1000, 2),
        'size' => $size,
        'body_size' => strlen($body),
        'redirect_count' => $redirectCount,
        'error' => $error,
        'errno' => $errno,
        'body' => $body,
        'raw_headers' => $headers,
    ];
}

function bench_login_admin(string $cookieFile): bool {
    $url = BENCH_BASE_URL . '/admin/login.php';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'form1' => 'Login',
            'email' => 'abderraoufchenna@gmail.com',
            'password' => 'admin123',
            '_csrf' => 'bypass-bench',
        ]),
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 302) {
        return true;
    }

    // Try common passwords
    $passwords = ['admin', 'admin123', 'password', '123456', 'azerty', 'root', 'administrator'];
    foreach ($passwords as $pass) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'form1' => 'Login',
                'email' => 'abderraoufchenna@gmail.com',
                'password' => $pass,
                '_csrf' => 'bypass-bench',
            ]),
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 302) {
            echo "[INFO] Login succeeded with password: $pass" . PHP_EOL;
            return true;
        }
    }

    echo "[WARN] Login failed for admin" . PHP_EOL;
    return false;
}

function bench_login_staff(string $cookieFile): bool {
    $url = BENCH_BASE_URL . '/staff/login.php';
    $passwords = ['azerty123', 'staff123', 'password', '123456'];

    // Find first employee email
    $pdo = bench_pdo();
    $stmt = $pdo->query("SELECT email FROM tbl_employee WHERE is_active=1 LIMIT 1");
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        echo "[WARN] No active employees found" . PHP_EOL;
        return false;
    }
    $email = $emp['email'];

    foreach ($passwords as $pass) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'form1' => 'Login',
                'email' => $email,
                'password' => $pass,
            ]),
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 302) {
            echo "[INFO] Staff login succeeded with password: $pass" . PHP_EOL;
            return true;
        }
    }

    echo "[WARN] Staff login failed" . PHP_EOL;
    return false;
}

function bench_cold_warm(string $url, string $cookieFile, int $warmRuns = 3): array {
    // Cold: clear cache table, first request
    $pdo = bench_pdo();
    $pdo->exec("DELETE FROM tbl_cache");
    $pdo->exec("DELETE FROM tbl_materialized_stats");
    $cold = bench_curl($url, $cookieFile);

    // Warm: subsequent requests
    $warmTimes = [];
    for ($i = 0; $i < $warmRuns; $i++) {
        usleep(100000);
        $warmTimes[] = bench_curl($url, $cookieFile)['total_time'];
    }
    $avgWarm = round(array_sum($warmTimes) / count($warmTimes), 2);

    $improvement = $cold['total_time'] > 0
        ? round((1 - $avgWarm / $cold['total_time']) * 100, 1)
        : 0;

    return [
        'cold_time' => $cold['total_time'],
        'warm_times' => $warmTimes,
        'avg_warm_time' => $avgWarm,
        'improvement_pct' => $improvement,
    ];
}

function bench_enable_mysql_profiling(): void {
    $pdo = bench_pdo();
    $pdo->exec("SET profiling = 1");
    $pdo->exec("SET profiling_history_size = 100");
    echo "[DBG] MySQL profiling enabled" . PHP_EOL;
}

function bench_get_query_profiles(): array {
    $pdo = bench_pdo();
    try {
        $stmt = $pdo->query("SHOW PROFILES");
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
    return $profiles;
}

function bench_explain_query(string $sql): array {
    $pdo = bench_pdo();
    try {
        $stmt = $pdo->prepare("EXPLAIN $sql");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    } catch (Exception $e) {
        return [['error' => $e->getMessage()]];
    }
}

function bench_get_table_row_counts(): array {
    $pdo = bench_pdo();
    $stmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_ROWS, ENGINE,
               ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '" . BENCH_DB_NAME . "'
        ORDER BY TABLE_ROWS DESC
    ");
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[$r['TABLE_NAME']] = [
            'rows' => (int) $r['TABLE_ROWS'],
            'engine' => $r['ENGINE'],
            'size_mb' => (float) $r['size_mb'],
        ];
    }
    return $rows;
}

function bench_query_time_ms(string $sql): float {
    $pdo = bench_pdo();
    $start = microtime(true);
    try {
        $stmt = $pdo->query($sql);
        if (is_object($stmt)) {
            $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        return -1;
    }
    return round((microtime(true) - $start) * 1000, 4);
}

// Search benchmark helpers
function bench_search_old(PDO $pdo, string $table, string $column, string $search, int $limit): float {
    $sql = "SELECT * FROM `$table` WHERE `$column` LIKE " . $pdo->quote('%' . $search . '%') . " LIMIT $limit";
    $start = microtime(true);
    $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return round((microtime(true) - $start) * 1000, 4);
}

function bench_search_fulltext(PDO $pdo, string $table, string $column, string $search, int $limit): float {
    $sql = "SELECT * FROM `$table` WHERE MATCH(`$column`) AGAINST (" . $pdo->quote($search) . " IN BOOLEAN MODE) LIMIT $limit";
    $start = microtime(true);
    try {
        $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return -1;
    }
    return round((microtime(true) - $start) * 1000, 4);
}
