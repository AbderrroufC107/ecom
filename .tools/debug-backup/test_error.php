<?php
$file = 'error_log';
if (file_exists($file)) {
    $size = filesize($file);
    $fp = fopen($file, 'r');
    if ($size > 2000) {
        fseek($fp, -2000, SEEK_END);
    }
    echo nl2br(htmlspecialchars(fread($fp, 2000)));
    fclose($fp);
} else {
    echo "No error_log found.";
}
