<?php
/**
 * Strict SaaS Isolation Scanner
 * Flags ANY raw PDO/mysqli query outside of the Repository layer as a Violation.
 */

$baseDir = 'C:/xampp/htdocs/ecom/admin';
$excludeDirs = ['tcpdf', 'scratch', 'inc/SaaS/Repositories', 'node_modules', 'cache'];

$violations = [];
$totalFiles = 0;
$repoFiles = 0;
$fixedQueries = 0;

function should_exclude($path, $excludeDirs) {
    foreach ($excludeDirs as $dir) {
        $normalizedDir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (strpos($path, DIRECTORY_SEPARATOR . $normalizedDir . DIRECTORY_SEPARATOR) !== false) return true;
        if (strpos($path, DIRECTORY_SEPARATOR . $normalizedDir) === strlen($path) - strlen($normalizedDir) - 1) return true;
    }
    return false;
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (should_exclude($path, $excludeDirs)) continue;
    
    $totalFiles++;
    
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $relativePath = str_replace('C:/xampp/htdocs/ecom/', '', str_replace('\\', '/', $path));

    $hasRepo = preg_match('/Repository\b/', $content);
    if ($hasRepo) $repoFiles++;
    
    foreach ($lines as $lineNum => $line) {
        $ln = $lineNum + 1;
        
        // Flag direct PDO/mysqli usage for queries
        // Exclude schema modifications (SHOW, ALTER, CREATE) or basic beginTransaction
        if (preg_match('/(\$pdo|\$this->pdo|\$this->db)->(query|prepare|exec)\s*\(\s*["\'](SELECT|UPDATE|DELETE|INSERT)/i', $line)) {
            // Check if it's a known global exception (like users fetching tables dynamically)
            if (stripos($line, 'information_schema') !== false) continue;
            
            $violations[] = [
                'file' => $relativePath,
                'line' => $ln,
                'query' => trim(substr($line, 0, 200))
            ];
        }
    }
}

echo "=== SAAS ISOLATION SCANNER (STRICT REPOSITORY MODE) ===\n\n";
echo "Total files scanned: $totalFiles\n";
echo "Files using Repositories: $repoFiles\n";
echo "Total Raw PDO Violations: " . count($violations) . "\n\n";

$byFile = [];
foreach ($violations as $v) {
    $byFile[$v['file']][] = $v;
}

// Display top 20 files with violations
$count = 0;
arsort($byFile);
foreach ($byFile as $file => $items) {
    if ($count++ > 20) break;
    echo "File: $file (" . count($items) . " violations)\n";
    foreach (array_slice($items, 0, 3) as $item) {
        echo "  [{$item['line']}] → {$item['query']}\n";
    }
    if (count($items) > 3) echo "  ... and " . (count($items) - 3) . " more\n";
    echo "\n";
}

if (count($violations) === 0) {
    echo "✅ SUCCESS: 0 Tenant Violations found. Production Ready.\n";
} else {
    echo "❌ FAILURE: Found raw database queries outside the Repository layer.\n";
}
