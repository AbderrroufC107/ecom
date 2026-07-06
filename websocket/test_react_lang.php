<?php
echo "==============================================\n";
echo "  React Pages Translation Validation Test\n";
echo "==============================================\n\n";

$pass = 0;
$fail = 0;

function check($label, $found, $detail = '') {
    global $pass, $fail;
    if ($found) { $pass++; echo "[PASS] {$label}" . ($detail ? " - {$detail}" : "") . "\n"; }
    else { $fail++; echo "[FAIL] {$label}" . ($detail ? " - {$detail}" : "") . "\n"; }
}

$dir = __DIR__ . '/../admin/react-src/src/pages';
$files = glob($dir . '/*.jsx');

if (empty($files)) {
    echo "[WARNING] No JSX pages found at {$dir}\n";
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Remove comments to ignore explanatory notes in Arabic
    $content = preg_replace('!/\*.*?\*/!s', '', $content);
    $content = preg_replace('!//.*?$!m', '', $content);

    // Search for Arabic letters: \x{0600}-\x{06FF} (Arabic Unicode range) with UTF-8 flag
    $hasArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $content, $matches);
    
    $filename = basename($file);
    if ($hasArabic) {
        // Find matching snippets to print in detail
        preg_match_all('/.{0,15}[\x{0600}-\x{06FF}]+.{0,15}/u', $content, $snippets);
        $snippet_str = implode(' | ', array_map('trim', array_slice($snippets[0], 0, 3)));
        check("Translation validation for {$filename}", false, "Contains hardcoded Arabic: {$snippet_str}");
    } else {
        check("Translation validation for {$filename}", true, "No hardcoded Arabic characters found.");
    }
}

echo "\n==============================================\n";
echo "  RESULTS: {$pass} passed, {$fail} failed\n";
echo "==============================================\n";

exit($fail > 0 ? 1 : 0);
