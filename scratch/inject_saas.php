<?php
$configFile = 'C:/xampp/htdocs/ecom/admin/inc/config.php';
$content = file_get_contents($configFile);

if (strpos($content, 'TenantMiddleware::handle();') === false) {
    // We must ensure the SaaS files are loaded and middleware is called.
    $saasInit = <<<PHP

// ─── SaaS Isolation Initialization ─────────────────────────────────────────
require_once __DIR__ . '/SaaS/TenantContext.php';
require_once __DIR__ . '/SaaS/TenantMiddleware.php';
\SaaS\TenantMiddleware::handle();

// Auto-load Repositories
spl_autoload_register(function (\$class) {
    if (strpos(\$class, 'SaaS\\\\Repositories\\\\') === 0) {
        \$file = __DIR__ . '/' . str_replace('\\\\', '/', \$class) . '.php';
        if (file_exists(\$file)) require_once \$file;
    }
});

// Create global repository instances for convenience during refactoring
global \$customerRepo, \$orderRepo, \$productRepo, \$settingsRepo, \$conversationRepo;
global \$channelRepo, \$aiTaskRepo, \$knowledgeRepo, \$eventStoreRepo, \$employeeRepo;

\$customerRepo = new \SaaS\Repositories\CustomerRepository(\$pdo);
\$orderRepo = new \SaaS\Repositories\OrderRepository(\$pdo);
\$productRepo = new \SaaS\Repositories\ProductRepository(\$pdo);
\$settingsRepo = new \SaaS\Repositories\SettingsRepository(\$pdo);
\$conversationRepo = new \SaaS\Repositories\ConversationRepository(\$pdo);
\$channelRepo = new \SaaS\Repositories\ChannelRepository(\$pdo);
\$aiTaskRepo = new \SaaS\Repositories\AiTaskRepository(\$pdo);
\$knowledgeRepo = new \SaaS\Repositories\KnowledgeRepository(\$pdo);
\$eventStoreRepo = new \SaaS\Repositories\EventStoreRepository(\$pdo);
\$employeeRepo = new \SaaS\Repositories\EmployeeRepository(\$pdo);

PHP;
    
    // Insert after PDO connection is established
    $content = preg_replace('/(\$pdo\s*=\s*new\s*PDO.*?;\n)/s', "$1\n$saasInit", $content);
    file_put_contents($configFile, $content);
    echo "Added SaaS Initialization to config.php\n";
} else {
    echo "SaaS Initialization already exists in config.php\n";
}
