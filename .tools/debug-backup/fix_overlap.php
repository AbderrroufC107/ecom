<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$content = str_replace(
    ".offer-card .offer-select-dot {\n    width: 20px;",
    ".offer-card .offer-select-dot {\n    position: static !important;\n    transform: none !important;\n    width: 20px;",
    $content
);

file_put_contents($file, $content);
echo "Fixed overlap";
?>
