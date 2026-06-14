<?php
require_once("admin/inc/config.php");
require_once("admin/inc/functions.php");

echo "Starting DB setup...<br>";

// 1. Create Pixel Tables
$sql_pixel = "CREATE TABLE IF NOT EXISTS tbl_pixel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pixel_name VARCHAR(255) NOT NULL,
    pixel_network VARCHAR(100) NOT NULL,
    pixel_id VARCHAR(255) NOT NULL,
    pixel_script TEXT
)";
$pdo->exec($sql_pixel);
echo "Table tbl_pixel created/exists.<br>";

$sql_product_pixel = "CREATE TABLE IF NOT EXISTS tbl_product_pixel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    pixel_id INT NOT NULL
)";
$pdo->exec($sql_product_pixel);
echo "Table tbl_product_pixel created/exists.<br>";

// 2. Fix the database by importing database_structure.sql if tbl_language is missing
$stmt = $pdo->query("SHOW TABLES LIKE 'tbl_language'");
if($stmt->rowCount() == 0) {
    echo "tbl_language is missing. Attempting to import database_structure.sql...<br>";
    $sql_file = 'database_structure.sql';
    if(file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Remove comments
        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
        $sql_content = preg_replace('/^\s*$/m', '', $sql_content);
        
        $queries = explode(';', $sql_content);
        $success_count = 0;
        $error_count = 0;
        
        foreach($queries as $query) {
            $query = trim($query);
            if(!empty($query)) {
                try {
                    $pdo->exec($query);
                    $success_count++;
                } catch(PDOException $e) {
                    // Ignore errors (e.g. table already exists)
                    $error_count++;
                }
            }
        }
        echo "Import completed. $success_count queries succeeded, $error_count queries failed/ignored.<br>";
    } else {
        echo "database_structure.sql not found!<br>";
    }
} else {
    echo "tbl_language already exists. Database structure seems fine.<br>";
}

echo "Done.";
