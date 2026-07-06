<?php
$dir = 'c:/xampp/htdocs/ecom/admin/dist/';
$version = '?v=react-admin-20260620-largerinputs2';
$search_double = '"./admin-react.js"';
$replace_double = '"./admin-react.js' . $version . '"';
$search_single = "'./admin-react.js'";
$replace_single = "'./admin-react.js" . $version . "'";

$files = glob($dir . '*.js');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    if (strpos($content, $search_double) !== false) {
        $content = str_replace($search_double, $replace_double, $content);
        $modified = true;
    }
    if (strpos($content, $search_single) !== false) {
        $content = str_replace($search_single, $replace_single, $content);
        $modified = true;
    }
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated imports in: " . basename($file) . "\n";
    }
}
?>
