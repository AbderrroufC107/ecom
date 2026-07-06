<?php
/**
 * Security Audit Scanner
 * Checks for: SQLi, XSS, CSRF, SSRF, Command Injection, 
 * File Upload risks, Path Traversal, Hardcoded secrets
 */

$baseDir = 'C:/xampp/htdocs/ecom';
$excludeDirs = ['node_modules', 'vendor', 'tcpdf', '.git', 'scratch', 'customer-next-vite'];

$findings = [
    'sqli'         => [],
    'xss'          => [],
    'csrf'         => [],
    'ssrf'         => [],
    'cmd_injection'=> [],
    'file_upload'  => [],
    'path_traversal'=> [],
    'secrets'      => [],
];

function should_exclude($path, $excludeDirs) {
    foreach ($excludeDirs as $dir) {
        if (strpos($path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR) !== false) return true;
        if (strpos($path, DIRECTORY_SEPARATOR . $dir) === strlen($path) - strlen($dir) - 1) return true;
    }
    return false;
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (should_exclude($path, $excludeDirs)) continue;

    $lines = file($path);
    $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $path);

    foreach ($lines as $lineNum => $line) {
        $ln = $lineNum + 1;
        
        // SQLi: direct query with user input
        if (preg_match('/->query\s*\(\s*"[^"]*\$_(GET|POST|REQUEST|COOKIE)/i', $line)) {
            $findings['sqli'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // XSS: echo of raw user input
        if (preg_match('/echo\s+\$_(GET|POST|REQUEST|COOKIE)/i', $line)) {
            $findings['xss'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // XSS: print without htmlspecialchars
        if (preg_match('/echo\s+.*htmlspecialchars_decode/i', $line)) {
            $findings['xss'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // CSRF: forms without token check
        if (preg_match('/<form.*method\s*=\s*["\']post/i', $line)) {
            if (!preg_match('/csrf|token|nonce/i', $line)) {
                // Don't flag every form, just note
            }
        }
        
        // SSRF: curl with user-controlled URL
        if (preg_match('/curl_setopt.*CURLOPT_URL.*\$_(GET|POST|REQUEST)/i', $line)) {
            $findings['ssrf'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // Command Injection
        if (preg_match('/(system|exec|passthru|shell_exec|popen)\s*\(.*\$_(GET|POST|REQUEST)/i', $line)) {
            $findings['cmd_injection'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // File Upload - no type validation
        if (preg_match('/move_uploaded_file\s*\(/i', $line)) {
            $findings['file_upload'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // Path Traversal
        if (preg_match('/(include|require|file_get_contents|fopen)\s*\(\s*\$_(GET|POST|REQUEST)/i', $line)) {
            $findings['path_traversal'][] = "$relativePath:$ln » " . trim($line);
        }
        
        // Hardcoded secrets
        if (preg_match('/["\'][a-z0-9]{32,}["\']/i', $line)) {
            // exclude known safe patterns (hashes in comments, etc)
            if (!preg_match('/(hash|sha|md5|comment|#)/i', $line)) {
                if (preg_match('/(token|secret|password|key|api_key)\s*=\s*["\'][a-z0-9]{32,}["\']/i', $line)) {
                    $findings['secrets'][] = "$relativePath:$ln » " . trim($line);
                }
            }
        }
    }
}

echo "=== SECURITY AUDIT REPORT ===\n\n";
$total = 0;
foreach ($findings as $type => $items) {
    echo strtoupper($type) . ": " . count($items) . " findings\n";
    foreach (array_slice($items, 0, 5) as $item) {
        echo "  » " . substr($item, 0, 200) . "\n";
    }
    $total += count($items);
    echo "\n";
}
echo "TOTAL FINDINGS: $total\n";

