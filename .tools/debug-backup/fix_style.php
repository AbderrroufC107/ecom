<?php
$c = file_get_contents('landing_page.php');

// Match the style block that we injected earlier
if (preg_match('/<style>\s*\/\*\s*Custom Layout overrides\s*\*\/(.*?)<\/style>/s', $c, $m)) {
    // Remove it from current location
    $c = str_replace($m[0], '', $c);
    
    // Insert it before the landing-order section so it always applies
    $c = str_replace('<section class="landing-order">', $m[0] . "\n" . '<section class="landing-order">', $c);
    
    // Fix any Arabic encoding issues that might have happened during the previous script execution on Windows
    // Replace the potentially broken string with the correct one
    $c = preg_replace('/<div class="offer-title">.*?<\/div>/', '<div class="offer-title">عرض خاص لفترة محدودة 🔥</div>', $c);
    
    file_put_contents('landing_page.php', $c);
    echo "Fixed";
} else {
    echo "Style block not found";
}
?>
