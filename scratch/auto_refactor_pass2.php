<?php
$baseDir = 'C:/xampp/htdocs/ecom/admin';
$excludeDirs = ['tcpdf', 'scratch', 'inc/SaaS', 'node_modules', 'cache', 'assets', 'css', 'js', 'fonts', 'img'];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

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

    // Detect if we need to replace $this->pdo
    if (preg_match('/\$this->pdo->(prepare|query|exec)/', $content)) {
        
        $content = preg_replace('/\$this->pdo->prepare/', '(new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare', $content);
        $content = preg_replace('/\$this->pdo->query/', '(new \SaaS\Repositories\DatabaseRepository($this->pdo))->query', $content);
        $content = preg_replace('/\$this->pdo->exec/', '(new \SaaS\Repositories\DatabaseRepository($this->pdo))->executeCommand', $content);
        
    }
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        $filesChanged++;
        $queriesFixed += preg_match_all('/new \\\SaaS\\\Repositories\\\DatabaseRepository/', $content);
    }
}

echo "=== SAAS AUTO REFACTOR PASS 2 COMPLETE ===\n";
echo "Files changed: $filesChanged\n";
echo "Queries fixed: $queriesFixed\n";
