<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

// Make "الأكثر طلباً" badge even bigger
$content = str_replace(
    "    font-size: 1rem;\n    padding: 5px 16px;\n    border-radius: 12px;\n    font-weight: 900;\n    z-index: 2;\n    letter-spacing: 0.3px;\n}",
    "    font-size: 1.2rem;\n    padding: 6px 20px;\n    border-radius: 14px;\n    font-weight: 900;\n    z-index: 2;\n    letter-spacing: 0.3px;\n}",
    $content
);

// Make offer name (offer-qty-price & offer-special-label) bigger
$content = str_replace(
    ".offer-qty-price, .offer-special-label {\n    font-weight: 800;\n    font-size: 1.4rem;",
    ".offer-qty-price, .offer-special-label {\n    font-weight: 900;\n    font-size: 1.7rem;",
    $content
);

file_put_contents($file, $content);
echo "Done";
?>
