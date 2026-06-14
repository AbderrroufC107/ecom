<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$content = str_replace(
    ".offer-qty-price, .offer-special-label {\n    font-weight: 700;\n    font-size: 1.1rem;",
    ".offer-qty-price, .offer-special-label {\n    font-weight: 800;\n    font-size: 1.4rem;",
    $content
);

$content = str_replace(
    ".offer-new {\n    color: #ef4444;\n    font-weight: 800;\n    font-size: 1.25rem;",
    ".offer-new {\n    color: #ef4444;\n    font-weight: 900;\n    font-size: 1.6rem;",
    $content
);

$content = str_replace(
    ".offer-old {\n    text-decoration: line-through;\n    color: #9ca3af;\n    font-size: 0.85rem;",
    ".offer-old {\n    text-decoration: line-through;\n    color: #9ca3af;\n    font-size: 1.05rem;",
    $content
);

$content = str_replace(
    ".offer-badge {\n    background: #dcfce7;\n    color: #166534;\n    font-size: 0.75rem;",
    ".offer-badge {\n    background: #dcfce7;\n    color: #166534;\n    font-size: 0.95rem;",
    $content
);

$content = str_replace(
    ".custom-delivery-banner .text {\n    font-size: 0.85rem;",
    ".custom-delivery-banner .text {\n    font-size: 1.05rem;",
    $content
);

$content = str_replace(
    ".custom-delivery-banner .text strong {\n    font-weight: 800;\n    font-size: 0.95rem;",
    ".custom-delivery-banner .text strong {\n    font-weight: 800;\n    font-size: 1.15rem;",
    $content
);

file_put_contents($file, $content);
echo "Done";
?>
