<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('C:/xampp/htdocs/ecom'));
$vulnerable = [];

foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    
    // Check for SQLi patterns: query("...$_POST...")
    if (preg_match('/->query\s*\(\s*["\'].*\$_(POST|GET|REQUEST)/i', $content)) {
        $vulnerable[] = $file->getPathname() . ' (SQLi)';
    }
    // Check for XSS in raw echos: echo $_GET
    if (preg_match('/echo\s+\$_(GET|POST|REQUEST)/i', $content)) {
        $vulnerable[] = $file->getPathname() . ' (XSS)';
    }
}

if (empty($vulnerable)) {
    echo "No obvious SQLi or XSS patterns found.\n";
} else {
    echo "Found potential vulnerabilities:\n" . implode("\n", $vulnerable) . "\n";
}
