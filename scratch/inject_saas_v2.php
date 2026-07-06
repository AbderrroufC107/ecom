<?php
// Full SaaS config injector - idempotent (safe to run multiple times)
$configFile = 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$content = file_get_contents($configFile);

// Check if already injected
if (strpos($content, '// === SaaS Initialization ===') !== false) {
    echo "SaaS already injected.\n";
    exit;
}

$saasBlock = <<<'PHPEOF'

// === SaaS Initialization ===
// Auto-load SaaS namespace classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'SaaS\\') === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

// Boot TenantMiddleware
require_once __DIR__ . '/SaaS/TenantContext.php';
require_once __DIR__ . '/SaaS/TenantMiddleware.php';
require_once __DIR__ . '/SaaS/Repositories/BaseRepository.php';
\SaaS\TenantMiddleware::boot($pdo);

// Init Repositories globally
global $customerRepo, $productRepo, $orderRepo, $settingsRepo, $conversationRepo;
global $channelRepo, $aiTaskRepo, $knowledgeRepo, $eventStoreRepo, $employeeRepo, $dbRepo;
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

// CSRF Token
if (session_status() !== PHP_SESSION_NONE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// === End SaaS Initialization ===
PHPEOF;

// Inject after the DB connection block (after the closing brace of the try-catch)
$insertAfter = "    exit('Database connection failed.');\n}";
$content = str_replace($insertAfter, $insertAfter . "\n" . $saasBlock, $content);

file_put_contents($configFile, $content);
echo "SaaS initialization injected into config.php\n";
