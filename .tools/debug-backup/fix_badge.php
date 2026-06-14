<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$content = str_replace(
    ".offer-popular-badge {\n    position: absolute;\n    top: -12px;\n    left: 20px;\n    background: #dc2626;\n    color: white;\n    font-size: 0.75rem;\n    padding: 3px 12px;\n    border-radius: 12px;\n    font-weight: 800;\n    z-index: 2;\n}",
    ".offer-popular-badge {\n    position: absolute;\n    top: -14px;\n    left: 20px;\n    background: #dc2626;\n    color: white;\n    font-size: 1rem;\n    padding: 5px 16px;\n    border-radius: 12px;\n    font-weight: 900;\n    z-index: 2;\n    letter-spacing: 0.3px;\n}",
    $content
);

file_put_contents($file, $content);
echo "Done";
?>
