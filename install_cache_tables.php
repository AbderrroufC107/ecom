<?php
require_once __DIR__ . '/admin/inc/config.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `tbl_delivery_cache_locations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `wilaya_id` INT NULL,
        `wilaya_name` VARCHAR(100) NOT NULL,
        `commune_name` VARCHAR(100) NOT NULL,
        `is_home_supported` TINYINT(1) DEFAULT 0,
        `home_price` DECIMAL(10,2) DEFAULT 0,
        `is_desk_supported` TINYINT(1) DEFAULT 0,
        `desk_price` DECIMAL(10,2) DEFAULT 0,
        `last_updated` DATETIME,
        UNIQUE KEY `idx_company_wilaya_commune` (`company_id`, `wilaya_name`, `commune_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `tbl_delivery_cache_desks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `wilaya_id` INT NULL,
        `wilaya_name` VARCHAR(100) NOT NULL,
        `commune_name` VARCHAR(100) NOT NULL,
        `desk_id` VARCHAR(100) NOT NULL,
        `desk_name` VARCHAR(255) NOT NULL,
        `desk_address` TEXT,
        `last_updated` DATETIME,
        UNIQUE KEY `idx_company_desk` (`company_id`, `desk_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `tbl_delivery_cache_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT,
        `sync_type` VARCHAR(50),
        `status` VARCHAR(20),
        `message` TEXT,
        `locations_added` INT DEFAULT 0,
        `locations_updated` INT DEFAULT 0,
        `locations_deleted` INT DEFAULT 0,
        `desks_added` INT DEFAULT 0,
        `desks_updated` INT DEFAULT 0,
        `desks_deleted` INT DEFAULT 0,
        `created_at` DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "Successfully executed query.\n";
    } catch (PDOException $e) {
        echo "Error executing query: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
