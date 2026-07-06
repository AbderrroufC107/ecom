<?php
$url = "https://github.com/tecnickcom/TCPDF/archive/refs/heads/master.zip";
$zipFile = "C:/xampp/htdocs/ecom/tcpdf.zip";
$extractPath = "C:/xampp/htdocs/ecom/admin/inc/";

echo "Downloading TCPDF from master...\n";
file_put_contents($zipFile, fopen($url, 'r'));

echo "Extracting TCPDF...\n";
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($extractPath);
    $zip->close();
    rename($extractPath . 'TCPDF-master', $extractPath . 'tcpdf');
    unlink($zipFile);
    echo "TCPDF installed successfully.\n";
} else {
    echo "Failed to extract TCPDF.\n";
}
