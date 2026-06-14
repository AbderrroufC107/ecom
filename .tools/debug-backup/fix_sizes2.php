<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

// Make price (offer-new) even bigger
$content = str_replace(
    ".offer-new {\n    color: #ef4444;\n    font-weight: 900;\n    font-size: 1.6rem;",
    ".offer-new {\n    color: #ef4444;\n    font-weight: 900;\n    font-size: 2.2rem;",
    $content
);

// Make old price bigger too proportionally
$content = str_replace(
    ".offer-old {\n    text-decoration: line-through;\n    color: #9ca3af;\n    font-size: 1.05rem;",
    ".offer-old {\n    text-decoration: line-through;\n    color: #9ca3af;\n    font-size: 1.3rem;",
    $content
);

// Make badge even bigger
$content = str_replace(
    "    font-size: 1.2rem;\n    padding: 6px 20px;\n    border-radius: 14px;\n    font-weight: 900;",
    "    font-size: 1.5rem;\n    padding: 8px 24px;\n    border-radius: 14px;\n    font-weight: 900;",
    $content
);

file_put_contents($file, $content);
echo "Done";
?>
