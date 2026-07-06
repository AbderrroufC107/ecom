<?php
$file = 'c:/xampp/htdocs/ecom/admin/header.php';
$content = file_get_contents($file);

if (strpos($content, 'distribution-stats.php') === false) {
    // Add to active pages list
    $content = str_replace(
        "'employees.php'",
        "'employees.php','distribution-stats.php'",
        $content
    );

    // Add the menu item
    $content = str_replace(
        '<li><a href="employees.php">',
        "<li><a href=\"distribution-stats.php\"><i class=\"fa fa-circle-o\"></i> إحصائيات توزيع الطلبات (WRR)</a></li>\n\t\t            <li><a href=\"employees.php\">",
        $content
    );

    file_put_contents($file, $content);
    echo "header.php updated successfully.\n";
} else {
    echo "header.php already updated.\n";
}
