<?php
require 'admin/inc/config.php';

try {
    // 1. Add commission_per_order to tbl_employee
    $pdo->exec("ALTER TABLE `tbl_employee` ADD COLUMN `commission_per_order` DECIMAL(12,2) NOT NULL DEFAULT 0.00;");
} catch (Exception $e) { }

try {
    // 2. Create tbl_employee_payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_employee_payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_number` VARCHAR(50) NOT NULL,
        `employee_id` INT NOT NULL,
        `admin_id` INT NOT NULL,
        `period_start` DATETIME DEFAULT NULL,
        `period_end` DATETIME DEFAULT NULL,
        `confirmed_orders` INT NOT NULL,
        `commission_rate` DECIMAL(12,2) NOT NULL,
        `total_amount` DECIMAL(12,2) NOT NULL,
        `payment_status` VARCHAR(50) NOT NULL DEFAULT 'Completed',
        `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `pdf_path` VARCHAR(255) NOT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_invoice_number` (`invoice_number`),
        KEY `idx_employee_id` (`employee_id`),
        KEY `idx_payment_date` (`payment_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Exception $e) { }

try {
    // 3. Modify tbl_order_assignment
    $pdo->exec("ALTER TABLE `tbl_order_assignment` ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 0;");
} catch (Exception $e) { }

try {
    $pdo->exec("ALTER TABLE `tbl_order_assignment` ADD COLUMN `payment_id` INT NULL DEFAULT NULL;");
    $pdo->exec("ALTER TABLE `tbl_order_assignment` ADD INDEX `idx_assignment_is_paid` (`is_paid`);");
    $pdo->exec("ALTER TABLE `tbl_order_assignment` ADD INDEX `idx_assignment_payment_id` (`payment_id`);");
} catch (Exception $e) { }

echo "Database schema updated successfully!\n";
