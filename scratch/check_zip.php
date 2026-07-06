<?php
$zip = new ZipArchive;
if ($zip->open('C:/xampp/htdocs/ecom/tcpdf.zip') === TRUE) {
    for($i = 0; $i < $zip->numFiles; $i++) {
        echo $zip->getNameIndex($i) . "\n";
        if ($i > 5) break;
    }
    $zip->close();
} else {
    echo "Failed";
}
