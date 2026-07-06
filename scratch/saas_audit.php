<?php
/**
 * SaaS Tenant Isolation Audit
 * Checks that all major admin queries include tenant_id
 */

require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

$baseDir = 'C:/xampp/htdocs/ecom/admin';
$excludeDirs = ['tcpdf', 'scratch'];

// Critical tables that MUST always be filtered by tenant_id
$critical_tables = [
    'tbl_order', 'tbl_product', 'tbl_customer', 'tbl_settings',
    'tbl_omni_conversations', 'tbl_omni_channels', 'tbl_ai_knowledge',
    'tbl_employee', 'tbl_payment', 'tbl_webhook'
];

$violations = [];

function should_exclude($path, $excludeDirs) {
    foreach ($excludeDirs as $dir) {
        if (strpos($path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR) !== false) return true;
    }
    return false;
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (should_exclude($path, $excludeDirs)) continue;

    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $relativePath = str_replace('C:/xampp/htdocs/ecom/', '', str_replace('\\', '/', $path));

    foreach ($lines as $lineNum => $line) {
        $ln = $lineNum + 1;
        foreach ($critical_tables as $table) {
            // Find SELECT/UPDATE/DELETE from this table
            if (preg_match('/\b(SELECT|UPDATE|DELETE)\b.*\b' . preg_quote($table, '/') . '\b/i', $line)) {
                // Check if tenant_id appears on same or next few lines
                $context = implode(' ', array_slice($lines, $lineNum, 5));
                if (stripos($context, 'tenant_id') === false) {
                    $violations[] = [
                        'file' => $relativePath,
                        'line' => $ln,
                        'table' => $table,
                        'query' => trim(substr($line, 0, 150))
                    ];
                }
            }
        }
    }
}

echo "=== SAAS TENANT ISOLATION AUDIT ===\n\n";
echo "Total violations found: " . count($violations) . "\n\n";

$byTable = [];
foreach ($violations as $v) {
    $byTable[$v['table']][] = $v;
}

foreach ($byTable as $table => $items) {
    echo "Table: $table — " . count($items) . " violations\n";
    foreach (array_slice($items, 0, 3) as $item) {
        echo "  [{$item['line']}] {$item['file']}\n";
        echo "  → {$item['query']}\n";
    }
    echo "\n";
}
