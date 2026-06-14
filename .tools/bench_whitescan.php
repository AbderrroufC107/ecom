<?php
/**
 * White Page Scan - Automatic HTTP + PHP error detection
 * Opens every admin, staff, super-admin page and API endpoint
 * Captures: HTTP Code, Fatal Errors, Warnings, Blank Output
 */
require_once __DIR__ . '/bench_lib.php';

echo "============================================" . PHP_EOL;
echo "  WHITE PAGE SCAN" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

$cookieFile = __DIR__ . '/bench_cookie.txt';
@unlink($cookieFile);

// Login admin
if (!bench_login_admin($cookieFile)) {
    echo "[ERROR] Cannot login. Aborting." . PHP_EOL;
    exit(1);
}
echo "[OK] Admin session established" . PHP_EOL;

// Also login staff
$staffCookie = __DIR__ . '/bench_cookie_staff.txt';
@unlink($staffCookie);
$staffLogin = bench_login_staff($staffCookie);

function scan_pages(array $pages, string $cookieFile, string $label): array {
    echo PHP_EOL . "--- $label ---" . PHP_EOL;
    $results = [];

    foreach ($pages as $page) {
        $url = BENCH_BASE_URL . (strpos($page['url'], 'http') === 0 ? '' : $page['url']);
        $result = bench_curl($url, $cookieFile);
        $body = $result['body'];

        $issues = [];
        if ($result['http_code'] === 0 || $result['http_code'] >= 500) {
            $issues[] = 'HTTP_' . $result['http_code'];
        }
        if (empty(trim($body))) {
            $issues[] = 'BLANK';
        }
        if ($result['error']) {
            $issues[] = 'CURL_ERR';
        }
        // Detect PHP errors in body
        if (preg_match('/Fatal error|Parse error|Catchable fatal error|Uncaught .*Exception/i', $body)) {
            $issues[] = 'FATAL_ERROR';
        }
        if (preg_match('/Warning:|Notice:|Deprecated:|Strict Standards/i', $body)) {
            $issues[] = 'PHP_WARN';
        }
        if (preg_match('/require_once.*failed|include.*failed|Fatal error.*require/i', $body)) {
            $issues[] = 'MISSING_INCLUDE';
        }
        if (preg_match('/Class.*not found|Interface.*not found/i', $body)) {
            $issues[] = 'AUTOLOAD_ERR';
        }
        // Detect redirect loops
        if ($result['redirect_count'] > 2) {
            $issues[] = 'REDIRECT_LOOP(' . $result['redirect_count'] . ')';
        }

        $results[] = [
            'label' => $page['label'],
            'url' => $page['url'],
            'http_code' => $result['http_code'],
            'body_size' => $result['body_size'],
            'time_ms' => $result['total_time'],
            'issues' => $issues,
            'has_content' => !empty(trim($body)),
        ];

        $status = empty($issues) ? 'OK' : implode('|', $issues);
        echo str_pad(substr($page['label'], 0, 39), 40)
           . str_pad($result['http_code'], 8)
           . str_pad($result['body_size'], 10)
           . $status
           . PHP_EOL;
    }

    return $results;
}

// Admin pages - comprehensive scan
$adminPages = [
    ['url' => '/admin/login.php', 'label' => 'Login'],
    ['url' => '/admin/store.php', 'label' => 'Dashboard (store)'],
    ['url' => '/admin/index.php', 'label' => 'Index'],
    ['url' => '/admin/order.php', 'label' => 'Orders List'],
    ['url' => '/admin/order-details.php?id=1', 'label' => 'Order Details'],
    ['url' => '/admin/order-process.php', 'label' => 'Order Process'],
    ['url' => '/admin/order-bulk-action.php', 'label' => 'Order Bulk Action'],
    ['url' => '/admin/order-statistics.php', 'label' => 'Order Statistics'],
    ['url' => '/admin/order-change-status.php', 'label' => 'Order Change Status'],
    ['url' => '/admin/order-ecotrack-action.php', 'label' => 'Order Ecotrack'],
    ['url' => '/admin/order-ecotrack-label.php', 'label' => 'Order Ecotrack Label'],
    ['url' => '/admin/order-add-from-incomplete.php', 'label' => 'Add from Incomplete'],
    ['url' => '/admin/customer.php', 'label' => 'Customers'],
    ['url' => '/admin/customer-message.php', 'label' => 'Customer Messages'],
    ['url' => '/admin/product.php', 'label' => 'Products'],
    ['url' => '/admin/product-add.php', 'label' => 'Product Add'],
    ['url' => '/admin/product-edit.php?id=1', 'label' => 'Product Edit'],
    ['url' => '/admin/employees.php', 'label' => 'Employees'],
    ['url' => '/admin/employee-details.php?id=1', 'label' => 'Employee Details'],
    ['url' => '/admin/employee-ranking.php', 'label' => 'Employee Ranking'],
    ['url' => '/admin/commission-settings.php', 'label' => 'Commission Settings'],
    ['url' => '/admin/performance-settings.php', 'label' => 'Performance Settings'],
    ['url' => '/admin/recovery-dashboard.php', 'label' => 'Recovery Dashboard'],
    ['url' => '/admin/disaster-recovery.php', 'label' => 'Disaster Recovery'],
    ['url' => '/admin/audit-log.php', 'label' => 'Audit Log'],
    ['url' => '/admin/system-health.php', 'label' => 'System Health'],
    ['url' => '/admin/architecture-health.php', 'label' => 'Architecture Health'],
    ['url' => '/admin/store-dashboard.php', 'label' => 'Store Dashboard'],
    ['url' => '/admin/backups.php', 'label' => 'Backups'],
    ['url' => '/admin/billing.php', 'label' => 'Billing'],
    ['url' => '/admin/queue-dashboard.php', 'label' => 'Queue Dashboard'],
    ['url' => '/admin/settings.php', 'label' => 'Settings'],
    ['url' => '/admin/site-security.php', 'label' => 'Site Security'],
    ['url' => '/admin/users.php', 'label' => 'Users'],
    ['url' => '/admin/profile-edit.php', 'label' => 'Profile Edit'],
    ['url' => '/admin/api-keys.php', 'label' => 'API Keys'],
    ['url' => '/admin/integrations.php', 'label' => 'Integrations'],
    ['url' => '/admin/social-media.php', 'label' => 'Social Media'],
    ['url' => '/admin/faq.php', 'label' => 'FAQ'],
    ['url' => '/admin/slider.php', 'label' => 'Slider'],
    ['url' => '/admin/pixel.php', 'label' => 'Pixel'],
    ['url' => '/admin/send-bulk-sms.php', 'label' => 'Send Bulk SMS'],
    ['url' => '/admin/language.php', 'label' => 'Language'],
    ['url' => '/admin/photo.php', 'label' => 'Photo Gallery'],
    ['url' => '/admin/service.php', 'label' => 'Services'],
    ['url' => '/admin/shipping-cost.php', 'label' => 'Shipping Cost'],
    ['url' => '/admin/delivery-company.php', 'label' => 'Delivery Companies'],
    ['url' => '/admin/delivery_list.php', 'label' => 'Delivery List'],
    ['url' => '/admin/top-category.php', 'label' => 'Top Categories'],
    ['url' => '/admin/mid-category.php', 'label' => 'Mid Categories'],
    ['url' => '/admin/end-category.php', 'label' => 'End Categories'],
    ['url' => '/admin/country.php', 'label' => 'Countries'],
    ['url' => '/admin/color.php', 'label' => 'Colors'],
    ['url' => '/admin/size.php', 'label' => 'Sizes'],
    ['url' => '/admin/ecotrack-diagnostics.php', 'label' => 'Ecotrack Diagnostics'],
    ['url' => '/admin/incomplete-orders.php', 'label' => 'Incomplete Orders'],
    ['url' => '/admin/ai-insights.php', 'label' => 'AI Insights'],
    ['url' => '/admin/event-settings.php', 'label' => 'Event Settings'],
    ['url' => '/admin/live-update-status.php', 'label' => 'Live Update Status'],
    ['url' => '/admin/exchange-requests.php', 'label' => 'Exchange Requests'],
];

$staffPages = [
    ['url' => '/staff/index.php', 'label' => 'Staff Dashboard'],
    ['url' => '/staff/orders.php', 'label' => 'Staff Orders'],
    ['url' => '/staff/order-details.php?id=1', 'label' => 'Staff Order Details'],
    ['url' => '/staff/performance.php', 'label' => 'Staff Performance'],
    ['url' => '/staff/commissions.php', 'label' => 'Staff Commissions'],
    ['url' => '/staff/profile.php', 'label' => 'Staff Profile'],
];

$superAdminPages = [
    ['url' => '/super-admin/index.php', 'label' => 'Super Admin Dashboard'],
    ['url' => '/super-admin/store-create.php', 'label' => 'Store Create'],
];

// Scan admin with admin cookie
$adminResults = scan_pages($adminPages, $cookieFile, 'ADMIN PAGES');

// Scan staff with staff cookie
$staffResults = [];
if ($staffLogin) {
    $staffResults = scan_pages($staffPages, $staffCookie, 'STAFF PAGES');
} else {
    echo PHP_EOL . "--- STAFF PAGES (no login) ---" . PHP_EOL;
    echo "[SKIP] Staff login failed, scanning without auth" . PHP_EOL;
    $staffResults = scan_pages($staffPages, $staffCookie, 'STAFF PAGES (no auth)');
}

// Try super-admin login
$saCookie = __DIR__ . '/bench_cookie_sa.txt';
@unlink($saCookie);
// Try login
$saLogin = false;
$saResults = scan_pages($superAdminPages, $saCookie, 'SUPER ADMIN PAGES');

$allResults = array_merge($adminResults, $staffResults, $saResults);

// Summary
echo PHP_EOL;
echo "============================================" . PHP_EOL;
echo "  WHITE PAGE SCAN SUMMARY" . PHP_EOL;
echo "============================================" . PHP_EOL;
echo PHP_EOL;

$withIssues = array_filter($allResults, fn($r) => !empty($r['issues']));
$blank = array_filter($allResults, fn($r) => !$r['has_content']);
$errors = array_filter($allResults, fn($r) => $r['http_code'] >= 500 || in_array('FATAL_ERROR', $r['issues'] ?? []));
$warnings = array_filter($allResults, fn($r) => in_array('PHP_WARN', $r['issues'] ?? []));

echo "Total pages scanned: " . count($allResults) . PHP_EOL;
echo "Pages with issues: " . count($withIssues) . PHP_EOL;
echo "Pages with errors: " . count($errors) . PHP_EOL;
echo "Pages with warnings: " . count($warnings) . PHP_EOL;
echo "Blank pages: " . count($blank) . PHP_EOL;

if (count($errors) > 0) {
    echo PHP_EOL . "ERRORS:" . PHP_EOL;
    foreach ($errors as $r) {
        echo "  - {$r['label']} ({$r['url']}): HTTP {$r['http_code']} | " . implode(', ', $r['issues']) . PHP_EOL;
    }
}

if (count($warnings) > 0) {
    echo PHP_EOL . "WARNINGS:" . PHP_EOL;
    foreach ($warnings as $r) {
        echo "  - {$r['label']} ({$r['url']}): HTTP {$r['http_code']} | " . implode(', ', $r['issues']) . PHP_EOL;
    }
}

if (count($blank) > 0) {
    echo PHP_EOL . "BLANK PAGES:" . PHP_EOL;
    foreach ($blank as $r) {
        echo "  - {$r['label']} ({$r['url']}): HTTP {$r['http_code']}" . PHP_EOL;
    }
}

// Save results
file_put_contents(__DIR__ . '/bench_whitescan_results.json', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'total' => count($allResults),
    'with_issues' => count($withIssues),
    'with_errors' => count($errors),
    'with_warnings' => count($warnings),
    'blank' => count($blank),
    'results' => $allResults,
], JSON_PRETTY_PRINT));

echo PHP_EOL . "[*] Results saved to bench_whitescan_results.json" . PHP_EOL;
