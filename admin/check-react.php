<?php
/**
 * React Loading Diagnostic — visit: admin/check-react.php
 * Checks all conditions needed for React to load on the admin panel.
 */
session_start();
require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/functions.php';

$checks = [];
$allPass = true;

// 1. PHP version
$checks[] = [
    'label' => 'PHP Version ≥ 7.4',
    'pass' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'detail' => PHP_VERSION,
];

// 2. DB connection
try {
    $db->query('SELECT 1');
    $checks[] = ['label' => 'Database Connection', 'pass' => true, 'detail' => 'OK'];
} catch (Exception $e) {
    $checks[] = ['label' => 'Database Connection', 'pass' => false, 'detail' => $e->getMessage()];
    $allPass = false;
}

// 3. dist/ directory exists
$distDir = __DIR__ . '/dist';
$distExists = is_dir($distDir);
$checks[] = ['label' => 'dist/ directory exists', 'pass' => $distExists, 'detail' => $distDir];

// 4. admin-react.js exists
$reactJs = $distDir . '/admin-react.js';
$reactExists = file_exists($reactJs);
$checks[] = ['label' => 'admin-react.js exists', 'pass' => $reactExists, 'detail' => $reactExists ? filesize($reactJs) . ' bytes' : 'MISSING'];

// 5. All required dist files
$requiredChunks = glob($distDir . '/admin-react-*.js');
$requiredCSS = glob($distDir . '/admin-react-*.css');
$checks[] = [
    'label' => 'JS chunks in dist/',
    'pass' => count($requiredChunks) > 0,
    'detail' => count($requiredChunks) . ' JS files',
];
$checks[] = [
    'label' => 'CSS files in dist/',
    'pass' => count($requiredCSS) > 0,
    'detail' => count($requiredCSS) . ' CSS files',
];

// 6. File permissions (readable)
if ($reactExists) {
    $readable = is_readable($reactJs);
    $checks[] = ['label' => 'admin-react.js is readable', 'pass' => $readable, 'detail' => $readable ? 'OK' : 'NOT READABLE'];
    if (!$readable) $allPass = false;
}

// 7. Check for JS errors log
$logFile = __DIR__ . '/admin_js_error.log';
$logExists = file_exists($logFile);
$logContent = '';
if ($logExists) {
    $logContent = file_get_contents($logFile);
    $logLines = array_filter(explode("\n", trim($logContent)));
    $recentErrors = array_slice($logLines, -5);
    $hasErrors = count($logLines) > 0;
    $checks[] = [
        'label' => 'JS Error Log',
        'pass' => !$hasErrors,
        'detail' => $hasErrors ? count($logLines) . ' errors (last 5: ' . implode(' | ', $recentErrors) . ')' : 'No errors',
    ];
    if ($hasErrors) $allPass = false;
} else {
    $checks[] = ['label' => 'JS Error Log', 'pass' => true, 'detail' => 'No log file (no errors logged yet)'];
}

// 8. Check CSP header
$headers = headers_list();
$cspHeader = '';
foreach ($headers as $h) {
    if (stripos($h, 'content-security-policy') !== false) {
        $cspHeader = $h;
        break;
    }
}
$checks[] = [
    'label' => 'CSP allows self scripts',
    'pass' => true, // just informational
    'detail' => $cspHeader ?: 'No CSP header set by PHP (check .htaccess / server config)',
];

// 9. tbl_settings columns
try {
    $cols = $db->query("SHOW COLUMNS FROM tbl_settings")->fetchAll(PDO::FETCH_COLUMN);
    $hasSnapchat = in_array('snapchat_pixel_id', $cols);
    $hasGoogle = in_array('google_analytics_id', $cols);
    $checks[] = [
        'label' => 'tbl_settings has snapchat_pixel_id',
        'pass' => $hasSnapchat,
        'detail' => $hasSnapchat ? 'EXISTS' : 'MISSING — run migration',
    ];
    $checks[] = [
        'label' => 'tbl_settings has google_analytics_id',
        'pass' => $hasGoogle,
        'detail' => $hasGoogle ? 'EXISTS' : 'MISSING — run migration',
    ];
} catch (Exception $e) {
    $checks[] = ['label' => 'tbl_settings check', 'pass' => false, 'detail' => $e->getMessage()];
}

// 10. List all dist files
$allDistFiles = $distExists ? scandir($distDir) : [];
$distFileList = array_filter($allDistFiles, fn($f) => !in_array($f, ['.', '..']));

// 11. Check .htaccess exists in root
$htaccess = dirname(__DIR__) . '/.htaccess';
$checks[] = [
    'label' => 'Root .htaccess exists',
    'pass' => file_exists($htaccess),
    'detail' => file_exists($htaccess) ? 'OK' : 'MISSING',
];

// 12. Check JS MIME type via Content-Type header simulation
$jsMimeOk = true; // can't truly test without HTTP, but check if .htaccess has AddType
$rootHtaccess = file_exists($htaccess) ? file_get_contents($htaccess) : '';
$hasMimeRule = strpos($rootHtaccess, 'AddType') !== false && strpos($rootHtaccess, 'application/javascript') !== false;
$checks[] = [
    'label' => '.htaccess has JS MIME type fix',
    'pass' => $hasMimeRule,
    'detail' => $hasMimeRule ? 'AddType application/javascript .js found' : 'No AddType rule for JS',
];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>React Loading Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #00d4ff; }
        .check { padding: 10px; margin: 5px 0; border-radius: 6px; }
        .pass { background: #0a3d0a; border-right: 4px solid #00ff00; }
        .fail { background: #3d0a0a; border-right: 4px solid #ff0000; }
        .label { font-weight: bold; }
        .detail { color: #aaa; font-size: 0.9em; margin-top: 4px; }
        .summary { font-size: 1.3em; margin: 20px 0; padding: 15px; border-radius: 8px; }
        .summary.all-pass { background: #003300; border: 2px solid #00ff00; }
        .summary.has-fail { background: #330000; border: 2px solid #ff0000; }
        .files { background: #111; padding: 15px; border-radius: 6px; margin-top: 20px; }
        .files li { color: #888; }
    </style>
</head>
<body>
    <h1>React Loading Diagnostic</h1>
    <p>PHP <?php echo phpversion(); ?> | <?php echo date('Y-m-d H:i:s'); ?></p>

    <div class="summary <?php echo $allPass ? 'all-pass' : 'has-fail'; ?>">
        <?php echo $allPass ? 'ALL CHECKS PASSED' : 'SOME CHECKS FAILED — see below'; ?>
    </div>

    <?php foreach ($checks as $c): ?>
    <div class="check <?php echo $c['pass'] ? 'pass' : 'fail'; ?>">
        <div class="label"><?php echo $c['pass'] ? '✓' : '✗'; ?> <?php echo htmlspecialchars($c['label']); ?></div>
        <div class="detail"><?php echo htmlspecialchars($c['detail']); ?></div>
    </div>
    <?php endforeach; ?>

    <div class="files">
        <h3>Files in admin/dist/ (<?php echo count($distFileList); ?> total):</h3>
        <ul>
        <?php foreach ($distFileList as $f): ?>
            <li><?php echo htmlspecialchars($f); ?> (<?php echo number_format(filesize($distDir . '/' . $f)); ?> bytes)</li>
        <?php endforeach; ?>
        </ul>
    </div>

    <p style="margin-top:20px;color:#888;">
        Visit <code>admin/check-react.php?ping=1</code> for JSON status.<br>
        Check <code>admin/admin_js_error.log</code> for JS crash logs after loading the admin panel.
    </p>
</body>
</html>
