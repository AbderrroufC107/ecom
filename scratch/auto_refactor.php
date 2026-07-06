<?php
$baseDir = 'C:/xampp/htdocs/ecom/admin';
$excludeDirs = ['tcpdf', 'scratch', 'inc/SaaS', 'node_modules', 'cache', 'assets', 'css', 'js', 'fonts', 'img'];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

$repoMap = [
    'tbl_customer' => '$customerRepo',
    'tbl_order' => '$orderRepo',
    'tbl_product' => '$productRepo',
    'tbl_settings' => '$settingsRepo',
    'tbl_omni_conversations' => '$conversationRepo',
    'tbl_omni_channels' => '$channelRepo',
    'tbl_ai_tasks' => '$aiTaskRepo',
    'tbl_ai_knowledge' => '$knowledgeRepo',
    'tbl_omni_events' => '$eventStoreRepo',
    'tbl_employee' => '$employeeRepo'
];

$filesChanged = 0;
$queriesFixed = 0;

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    
    $exclude = false;
    foreach ($excludeDirs as $dir) {
        $normalizedDir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (strpos($path, DIRECTORY_SEPARATOR . $normalizedDir . DIRECTORY_SEPARATOR) !== false) $exclude = true;
    }
    if ($exclude) continue;

    $content = file_get_contents($path);
    $original = $content;

    // Detect if we need global repos
    if (preg_match('/\$pdo->(prepare|query|exec)/', $content)) {
        
        // Let's replace $pdo->prepare(...) with $repo->prepare(...)
        // We will just use $dbRepo globally to be safe, because some queries join multiple tables
        // So we just replace `$pdo->` with `$dbRepo->` for database queries.
        
        // Wait, $pdo is also used for lastInsertId() and inTransaction().
        // We can replace those too.
        
        $content = preg_replace('/\$pdo->prepare/', '$dbRepo->prepare', $content);
        $content = preg_replace('/\$pdo->query/', '$dbRepo->query', $content);
        $content = preg_replace('/\$pdo->exec/', '$dbRepo->executeCommand', $content);
        $content = preg_replace('/\$pdo->lastInsertId/', '$dbRepo->lastInsertId', $content); // wait, BaseRepo needs lastInsertId
        
        // Ensure $dbRepo is available globally in the file
        if (strpos($content, 'global $dbRepo;') === false && preg_match('/\$dbRepo->/', $content)) {
            // Find <?php and insert after it, or just rely on config.php
            // Since config.php is required everywhere, $dbRepo is usually available, but inside functions it needs `global $dbRepo;`
            
            // To be safe, let's inject `global $dbRepo;` at the top of functions that use $dbRepo
            $content = preg_replace_callback('/function\s+[a-zA-Z0-9_]+\s*\([^)]*\)\s*\{/i', function($m) {
                return $m[0] . "\n    global \$dbRepo;\n";
            }, $content);
            
            // For global scope, we don't need `global $dbRepo;` if config.php is included.
        }
    }
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        $filesChanged++;
        $queriesFixed += preg_match_all('/\$dbRepo->(prepare|query|executeCommand)/', $content);
    }
}

echo "=== SAAS AUTO REFACTOR COMPLETE ===\n";
echo "Files changed: $filesChanged\n";
echo "Queries fixed: $queriesFixed\n";
