<?php
$configFile = 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$content = file_get_contents($configFile);

if (strpos($content, 'SaaS Initialization') !== false) {
    echo "Already injected.\n";
    exit(0);
}

// Remove trailing whitespace/newline before appending
$content = rtrim($content);

$saasBlock = '

// === SaaS Initialization ===
// PSR-4-style autoloader for SaaS namespace
spl_autoload_register(function (string $class): void {
    if (strpos($class, \'SaaS\\\\\') !== 0) return;
    $rel  = str_replace(\'\\\\\', \'/\', $class) . \'.php\';
    $full = __DIR__ . \'/\' . $rel;
    if (file_exists($full)) {
        require_once $full;
    }
});

// Explicit require in case autoloader timing is off
require_once __DIR__ . \'/SaaS/TenantContext.php\';
require_once __DIR__ . \'/SaaS/TenantMiddleware.php\';
require_once __DIR__ . \'/SaaS/Repositories/BaseRepository.php\';

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
if (session_status() !== PHP_SESSION_NONE && empty($_SESSION[\'csrf_token\'])) {
    $_SESSION[\'csrf_token\'] = bin2hex(random_bytes(32));
}
// === End SaaS Initialization ===
';

file_put_contents($configFile, $content . $saasBlock);
echo "Injected SaaS block.\n";
