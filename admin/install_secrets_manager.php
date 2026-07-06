<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

try {
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_secrets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        secret_name VARCHAR(255) NOT NULL UNIQUE,
        provider VARCHAR(50) NOT NULL, -- e.g., 'meta', 'whatsapp'
        encrypted_value TEXT NOT NULL,
        key_version INT DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_secrets created.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
