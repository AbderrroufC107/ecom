<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$content = str_replace(
    ".order-offers-top .offer-title {\n    font-size: 1.3rem;\n    font-weight: 800;",
    ".order-offers-top .offer-title {\n    font-size: 1.8rem;\n    font-weight: 900;",
    $content
);

file_put_contents($file, $content);
echo "Updated title size";
?>
