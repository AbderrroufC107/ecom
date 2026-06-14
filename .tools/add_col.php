<?php
require('inc/config.php');
try {
    $pdo->exec("ALTER TABLE tbl_order ADD COLUMN ecotrack_remote_time DATETIME NULL DEFAULT NULL AFTER ecotrack_remote_status");
    echo "Column added successfully";
} catch(PDOException $e) {
    echo "Column already exists or error: " . $e->getMessage();
}
