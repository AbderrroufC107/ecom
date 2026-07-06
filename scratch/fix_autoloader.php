<?php
$content = file_get_contents('C:/xampp/htdocs/ecom/admin/inc/config.php');
$content = str_replace(
    "strpos(\$class, 'SaaS\\\\Repositories\\\\') === 0",
    "strpos(\$class, 'SaaS\\\\') === 0",
    $content
);
file_put_contents('C:/xampp/htdocs/ecom/admin/inc/config.php', $content);
