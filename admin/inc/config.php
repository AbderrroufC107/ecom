<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('Africa/Algiers');

// Session security hardening
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
}

// DB
// Host Name
$dbhost = 'localhost';

// Database Name
$dbname = 'boomtsvp_boomtsvp_ecommerceweb';

// Database Username
$dbuser = 'root';

// Database Password
$dbpass = '';

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

if (!defined('SITE_URL')) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);

    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['PHP_SELF'] ?? '/';
    $base_path = str_replace('\\', '/', dirname(dirname($script)));

    if ($base_path === '/' || $base_path === '.' || $base_path === '\\') {
        $base_path = '';
    } else {
        $base_path = rtrim($base_path, '/');
    }

    define('SITE_URL', $scheme . '://' . $host . $base_path);
}

if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', BASE_URL . 'admin/');
}

if (!defined('EXTERNAL_IMAGE_PROXY_ENABLED')) define('EXTERNAL_IMAGE_PROXY_ENABLED', true);
if (!defined('EXTERNAL_IMAGE_PROXY_BASE')) define('EXTERNAL_IMAGE_PROXY_BASE', 'https://wsrv.nl/');
if (!defined('EXTERNAL_IMAGE_PROXY_QUALITY')) define('EXTERNAL_IMAGE_PROXY_QUALITY', 72);

if (!defined('TELEGRAM_BOT_TOKEN')) define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'BOT_TOKEN');
if (!defined('EVENT_BOT_CHAT_ID')) define('EVENT_BOT_CHAT_ID', getenv('EVENT_BOT_CHAT_ID') ?: '');
if (!defined('EVENT_BOT_ENABLED')) define('EVENT_BOT_ENABLED', getenv('EVENT_BOT_ENABLED') ?: '0');

if (!defined('CLOUDINARY_CLOUD_NAME')) define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: '');
if (!defined('CLOUDINARY_API_KEY')) define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '');
if (!defined('CLOUDINARY_API_SECRET')) define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '');
if (!defined('CLOUDINARY_UPLOAD_PRESET')) define('CLOUDINARY_UPLOAD_PRESET', getenv('CLOUDINARY_UPLOAD_PRESET') ?: '');
if (!defined('CLOUDINARY_FOLDER')) define('CLOUDINARY_FOLDER', getenv('CLOUDINARY_FOLDER') ?: 'ecom');
if (!defined('CLOUDINARY_STRICT_MODE')) define('CLOUDINARY_STRICT_MODE', true);
if (!defined('CLOUDINARY_HTTP_TIMEOUT')) define('CLOUDINARY_HTTP_TIMEOUT', 20);

if (!defined('PRESERVE_LOCAL_UPLOAD_FILES')) define('PRESERVE_LOCAL_UPLOAD_FILES', false);

try {
    $pdo = new PDO(
        "mysql:host={$dbhost};dbname={$dbname};charset=utf8mb4",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}
