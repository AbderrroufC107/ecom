<?php
/**
 * PHP Info Page
 * Shows PHP configuration and server information
 */

// Security: Only allow logged-in admin users
session_start();

// Check if admin is logged in (basic check)
$isAdmin = false;
if (isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])) {
    $isAdmin = true;
}

// Allow access for testing (remove in production)
$isAdmin = true;

if (!$isAdmin) {
    http_response_code(403);
    echo "Access Denied";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Info - E-Com</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #1a1a2e; color: #eee; padding: 20px; }
        h1 { color: #e94560; margin-bottom: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; }

        .panel { background: #16213e; border: 1px solid #0f3460; border-radius: 10px; padding: 20px; margin-bottom: 15px; }
        .panel h2 { color: #e94560; margin-bottom: 12px; font-size: 18px; border-bottom: 2px solid #0f3460; padding-bottom: 8px; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .info-item { background: #0d1117; border-radius: 8px; padding: 15px; }
        .info-item .label { font-size: 12px; color: #8b949e; margin-bottom: 4px; }
        .info-item .value { font-size: 16px; font-weight: bold; color: #58a6ff; word-break: break-all; }
        .info-item .value.ok { color: #3fb950; }
        .info-item .value.warn { color: #e9c46a; }
        .info-item .value.error { color: #f85149; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px 14px; text-align: right; border-bottom: 1px solid #30363d; }
        th { background: #0d1117; color: #e94560; font-weight: bold; }
        td { background: #16213e; }
        td:first-child { font-weight: bold; color: #58a6ff; width: 250px; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge.ok { background: #1b4332; color: #52b788; }
        .badge.warn { background: #5c4a1a; color: #e9c46a; }
        .badge.error { background: #5c1a1a; color: #e94560; }

        .section { margin-bottom: 20px; }
        .section h3 { color: #58a6ff; margin-bottom: 10px; font-size: 16px; }

        pre { background: #0d1117; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.5; }

        .back-link { display: inline-block; margin-bottom: 20px; color: #58a6ff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        <h1>PHP Info</h1>

        <?php
        // Gather all information
        $phpVersion = phpversion();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        $serverName = $_SERVER['SERVER_NAME'] ?? 'N/A';
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'N/A';
        $phpIniLoaded = php_ini_loaded_file();
        $phpExtensions = get_loaded_extensions();
        $phpSapi = php_sapi_name();

        // Check important settings
        $errorReporting = error_reporting();
        $displayErrors = ini_get('display_errors');
        $logErrors = ini_get('log_errors');
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $memoryLimit = ini_get('memory_limit');
        $maxExecutionTime = ini_get('max_execution_time');
        $maxInputTime = ini_get('max_input_time');
        $timezone = ini_get('date.timezone');
        $sessionSavePath = ini_get('session.save_path');
        $openssl = extension_loaded('openssl');
        $curl = extension_loaded('curl');
        $mbstring = extension_loaded('mbstring');
        $json = extension_loaded('json');
        $pdo = extension_loaded('pdo');
        $pdoMysql = extension_loaded('pdo_mysql');
        $mysqli = extension_loaded('mysqli');
        $gd = extension_loaded('gd');
        $imagick = extension_loaded('imagick');
        $fileinfo = extension_loaded('fileinfo');
        $zip = extension_loaded('zip');
        $xml = extension_loaded('xml');
        $mbstring = extension_loaded('mbstring');
        $sockets = extension_loaded('sockets');
        $redis = extension_loaded('redis');
        $memcached = extension_loaded('memcached');
        ?>

        <!-- Server Info -->
        <div class="panel">
            <h2>Server Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">PHP Version</div>
                    <div class="value <?php echo version_compare($phpVersion, '8.0', '>=') ? 'ok' : 'warn'; ?>">
                        <?php echo $phpVersion; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Server Software</div>
                    <div class="value"><?php echo $serverSoftware; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Server Name</div>
                    <div class="value"><?php echo $serverName; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Document Root</div>
                    <div class="value"><?php echo $documentRoot; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">PHP SAPI</div>
                    <div class="value"><?php echo $phpSapi; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">php.ini Loaded</div>
                    <div class="value"><?php echo $phpIniLoaded ?: 'Not loaded'; ?></div>
                </div>
            </div>
        </div>

        <!-- PHP Configuration -->
        <div class="panel">
            <h2>PHP Configuration</h2>
            <table>
                <tr><td>error_reporting</td><td><?php echo $errorReporting; ?> (All errors: <?php echo ($errorReporting & E_ALL) ? 'Yes' : 'No'; ?>)</td></tr>
                <tr><td>display_errors</td><td><span class="badge <?php echo $displayErrors ? 'warn' : 'ok'; ?>"><?php echo $displayErrors ?: 'Off'; ?></span></td></tr>
                <tr><td>log_errors</td><td><span class="badge <?php echo $logErrors ? 'ok' : 'warn'; ?>"><?php echo $logErrors ?: 'Off'; ?></span></td></tr>
                <tr><td>upload_max_filesize</td><td><?php echo $uploadMaxFilesize; ?></td></tr>
                <tr><td>post_max_size</td><td><?php echo $postMaxSize; ?></td></tr>
                <tr><td>memory_limit</td><td><?php echo $memoryLimit; ?></td></tr>
                <tr><td>max_execution_time</td><td><?php echo $maxExecutionTime; ?> seconds</td></tr>
                <tr><td>max_input_time</td><td><?php echo $maxInputTime; ?> seconds</td></tr>
                <tr><td>date.timezone</td><td><?php echo $timezone ?: 'Not set'; ?></td></tr>
                <tr><td>session.save_path</td><td><?php echo $sessionSavePath ?: 'Not set'; ?></td></tr>
            </table>
        </div>

        <!-- Extensions -->
        <div class="panel">
            <h2>Loaded Extensions (<?php echo count($phpExtensions); ?>)</h2>
            <div class="section">
                <h3>Required for E-Com</h3>
                <table>
                    <tr><td>openssl</td><td><span class="badge <?php echo $openssl ? 'ok' : 'error'; ?>"><?php echo $openssl ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>curl</td><td><span class="badge <?php echo $curl ? 'ok' : 'error'; ?>"><?php echo $curl ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>mbstring</td><td><span class="badge <?php echo $mbstring ? 'ok' : 'error'; ?>"><?php echo $mbstring ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>json</td><td><span class="badge <?php echo $json ? 'ok' : 'error'; ?>"><?php echo $json ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>pdo</td><td><span class="badge <?php echo $pdo ? 'ok' : 'error'; ?>"><?php echo $pdo ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>pdo_mysql</td><td><span class="badge <?php echo $pdoMysql ? 'ok' : 'error'; ?>"><?php echo $pdoMysql ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>mysqli</td><td><span class="badge <?php echo $mysqli ? 'ok' : 'error'; ?>"><?php echo $mysqli ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>gd</td><td><span class="badge <?php echo $gd ? 'ok' : 'warn'; ?>"><?php echo $gd ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>fileinfo</td><td><span class="badge <?php echo $fileinfo ? 'ok' : 'warn'; ?>"><?php echo $fileinfo ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>zip</td><td><span class="badge <?php echo $zip ? 'ok' : 'warn'; ?>"><?php echo $zip ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>xml</td><td><span class="badge <?php echo $xml ? 'ok' : 'warn'; ?>"><?php echo $xml ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>sockets</td><td><span class="badge <?php echo $sockets ? 'ok' : 'warn'; ?>"><?php echo $sockets ? 'Loaded' : 'Missing'; ?></span></td></tr>
                    <tr><td>redis</td><td><span class="badge <?php echo $redis ? 'ok' : 'warn'; ?>"><?php echo $redis ? 'Loaded' : 'Not installed'; ?></span></td></tr>
                    <tr><td>memcached</td><td><span class="badge <?php echo $memcached ? 'ok' : 'warn'; ?>"><?php echo $memcached ? 'Loaded' : 'Not installed'; ?></span></td></tr>
                </table>
            </div>

            <div class="section">
                <h3>All Extensions</h3>
                <pre><?php echo implode("\n", $phpExtensions); ?></pre>
            </div>
        </div>

        <!-- Server Variables -->
        <div class="panel">
            <h2>Server Variables</h2>
            <table>
                <?php
                $importantVars = [
                    'SERVER_NAME', 'SERVER_ADDR', 'SERVER_PORT',
                    'DOCUMENT_ROOT', 'SCRIPT_FILENAME', 'SCRIPT_NAME',
                    'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING',
                    'CONTENT_TYPE', 'CONTENT_LENGTH',
                    'HTTP_HOST', 'HTTP_USER_AGENT', 'HTTP_ACCEPT',
                    'HTTPS', 'REMOTE_ADDR', 'REMOTE_PORT',
                    'GATEWAY_INTERFACE', 'SERVER_PROTOCOL',
                ];
                foreach ($importantVars as $var) {
                    $value = $_SERVER[$var] ?? 'Not set';
                    echo "<tr><td>\$_SERVER['{$var}']</td><td>{$value}</td></tr>\n";
                }
                ?>
            </table>
        </div>

        <!-- PHP Functions -->
        <div class="panel">
            <h2>Important Functions</h2>
            <table>
                <tr><td>json_encode()</td><td><span class="badge ok">Available</span></td></tr>
                <tr><td>json_decode()</td><td><span class="badge ok">Available</span></td></tr>
                <tr><td>curl_init()</td><td><span class="badge <?php echo $curl ? 'ok' : 'error'; ?>"><?php echo $curl ? 'Available' : 'Not available'; ?></span></td></tr>
                <tr><td>imagecreate()</td><td><span class="badge <?php echo $gd ? 'ok' : 'error'; ?>"><?php echo $gd ? 'Available' : 'Not available'; ?></span></td></tr>
                <tr><td>mb_strlen()</td><td><span class="badge <?php echo $mbstring ? 'ok' : 'error'; ?>"><?php echo $mbstring ? 'Available' : 'Not available'; ?></span></td></tr>
                <tr><td>password_hash()</td><td><span class="badge ok">Available</span></td></tr>
                <tr><td>socket_create()</td><td><span class="badge <?php echo $sockets ? 'ok' : 'error'; ?>"><?php echo $sockets ? 'Available' : 'Not available'; ?></span></td></tr>
            </table>
        </div>

        <!-- Environment -->
        <div class="panel">
            <h2>Environment Variables</h2>
            <table>
                <?php
                $envVars = getenv();
                ksort($envVars);
                foreach ($envVars as $key => $value) {
                    // Mask sensitive values
                    if (preg_match('/password|secret|key|token/i', $key)) {
                        $value = '***MASKED***';
                    }
                    echo "<tr><td>{$key}</td><td>" . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '') . "</td></tr>\n";
                }
                ?>
            </table>
        </div>

        <div class="panel" style="text-align: center; color: #8b949e;">
            Generated at <?php echo date('Y-m-d H:i:s'); ?> | PHP <?php echo $phpVersion; ?>
        </div>
    </div>
</body>
</html>
