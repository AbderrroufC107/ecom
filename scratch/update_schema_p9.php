<?php
require_once __DIR__ . '/../admin/inc/config.php';

try {
    // 1. Modify tbl_employee
    $pdo->exec("ALTER TABLE `tbl_employee` 
        ADD COLUMN `assignment_weight` INT NOT NULL DEFAULT 1 AFTER `is_active`,
        ADD COLUMN `availability_status` VARCHAR(50) NOT NULL DEFAULT 'Available' AFTER `assignment_weight`,
        ADD COLUMN `max_active_orders` INT NOT NULL DEFAULT 50 AFTER `availability_status`
    ");
    echo "tbl_employee updated.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "tbl_employee already has the columns.\n";
    } else {
        echo "Error tbl_employee: " . $e->getMessage() . "\n";
    }
}

try {
    // 2. Modify tbl_user
    $pdo->exec("ALTER TABLE `tbl_user` 
        ADD COLUMN `participate_in_assignment` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`,
        ADD COLUMN `assignment_weight` INT NOT NULL DEFAULT 1 AFTER `participate_in_assignment`,
        ADD COLUMN `availability_status` VARCHAR(50) NOT NULL DEFAULT 'Available' AFTER `assignment_weight`,
        ADD COLUMN `max_active_orders` INT NOT NULL DEFAULT 50 AFTER `availability_status`
    ");
    echo "tbl_user updated.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "tbl_user already has the columns.\n";
    } else {
        echo "Error tbl_user: " . $e->getMessage() . "\n";
    }
}

try {
    // 3. Modify tbl_order_assignment
    $pdo->exec("ALTER TABLE `tbl_order_assignment` 
        MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL,
        ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `employee_id`
    ");
    echo "tbl_order_assignment updated.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "tbl_order_assignment already has the columns.\n";
    } else {
        echo "Error tbl_order_assignment: " . $e->getMessage() . "\n";
    }
}

echo "Database Schema Update Complete.\n";
