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
$dbname = 'thikastore';

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

// === SaaS Initialization ===
// PSR-4-style autoloader for SaaS namespace
spl_autoload_register(function (string $class): void {
    if (strpos($class, 'SaaS\\') !== 0) return;
    $rel  = str_replace('\\', '/', $class) . '.php';
    $full = __DIR__ . '/' . $rel;
    if (file_exists($full)) {
        require_once $full;
    }
});

// Autoloader for Marketing, Security, Omni namespaces
spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Marketing\\' => __DIR__ . '/Marketing/',
        'Security\\'  => __DIR__ . '/Security/',
        'Omni\\'      => __DIR__ . '/Omni/',
    ];
    foreach ($namespaces as $prefix => $baseDir) {
        if (strpos($class, $prefix) !== 0) continue;
        $rel  = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $file = $baseDir . $rel;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Explicit require in case autoloader timing is off
require_once __DIR__ . '/SaaS/TenantContext.php';
require_once __DIR__ . '/SaaS/TenantMiddleware.php';
require_once __DIR__ . '/SaaS/Repositories/BaseRepository.php';

// Boot Tenant context from session / request
\SaaS\TenantMiddleware::boot($pdo);

// Init Repository globals
global $customerRepo, $productRepo, $orderRepo, $settingsRepo,
       $conversationRepo, $channelRepo, $aiTaskRepo, $knowledgeRepo,
       $eventStoreRepo, $employeeRepo, $dbRepo;

$customerRepo     = new \SaaS\Repositories\CustomerRepository($pdo);
$productRepo      = new \SaaS\Repositories\ProductRepository($pdo);
$orderRepo        = new \SaaS\Repositories\OrderRepository($pdo);
$settingsRepo     = new \SaaS\Repositories\SettingsRepository($pdo);
$conversationRepo = new \SaaS\Repositories\ConversationRepository($pdo);
$channelRepo      = new \SaaS\Repositories\ChannelRepository($pdo);
$aiTaskRepo       = new \SaaS\Repositories\AiTaskRepository($pdo);
$knowledgeRepo    = new \SaaS\Repositories\KnowledgeRepository($pdo);
$eventStoreRepo   = new \SaaS\Repositories\EventStoreRepository($pdo);
$employeeRepo     = new \SaaS\Repositories\EmployeeRepository($pdo);
$dbRepo           = new \SaaS\Repositories\DatabaseRepository($pdo);

// CSRF token
if (session_status() !== PHP_SESSION_NONE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// === End SaaS Initialization ===

define('APP_SECRET_KEY', 'QB/hVVwmFRJJa1FwCH/4Y1qIdHqssCQY4ieYG4GOQhw=');
