<?php
/**
 * Telegram Bot Plugin Installer
 *
 * Idempotent installer to update database schema and build tables.
 * Can be run via Command Line or Dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../inc/config.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed.");
}

/**
 * Checks if a column exists in a database table.
 */
function columnExists(PDO $pdo, string $table, string $column): bool
{ global $dbRepo;
    try {
        $stmt = $dbRepo->prepare("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $col = trim($column)]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks if an index exists on a database table.
 */
function indexExists(PDO $pdo, string $table, string $indexName): bool
{ global $dbRepo;
    try {
        $stmt = $dbRepo->prepare("
            SELECT INDEX_NAME 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $indexName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

try {
    echo "Starting Telegram Bot database migration...\n";

    // 1. Alter tbl_settings dynamically
    $settingsColumns = [
        'telegram_webhook_url' => "VARCHAR(255) NULL",
        'telegram_is_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'telegram_enable_employee_notifications' => "TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_enable_manager_notifications' => "TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_enable_complaint_notifications' => "TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_enable_daily_reports' => "TINYINT(1) NOT NULL DEFAULT 0",
        'telegram_enable_queue_processing' => "TINYINT(1) NOT NULL DEFAULT 1",
        'telegram_queue_retry_attempts' => "INT NOT NULL DEFAULT 3",
        'telegram_daily_report_time' => "TIME NOT NULL DEFAULT '08:00:00'",
        'telegram_reminder_hours' => "INT NOT NULL DEFAULT 24",
        'telegram_weekly_report_day' => "INT NOT NULL DEFAULT 5",
        'telegram_secret_token' => "VARCHAR(255) NULL"
    ];

    foreach ($settingsColumns as $col => $definition) {
        if (!columnExists($pdo, 'tbl_settings', $col)) {
            $dbRepo->executeCommand("ALTER TABLE `tbl_settings` ADD COLUMN `{$col}` {$definition}");
            echo "Added column '{$col}' to 'tbl_settings'.\n";
        }
    }

    // 2. Alter tbl_employee dynamically
    $employeeColumns = [
        'telegram_username' => "VARCHAR(255) NULL",
        'telegram_first_name' => "VARCHAR(255) NULL",
        'telegram_link_token' => "VARCHAR(64) NULL",
        'telegram_link_expires_at' => "DATETIME NULL",
        'telegram_linked_at' => "DATETIME NULL",
        'telegram_is_linked' => "TINYINT(1) NOT NULL DEFAULT 0",
        'telegram_lang' => "VARCHAR(10) NOT NULL DEFAULT 'ar'"
    ];

    foreach ($employeeColumns as $col => $definition) {
        if (!columnExists($pdo, 'tbl_employee', $col)) {
            $dbRepo->executeCommand("ALTER TABLE `tbl_employee` ADD COLUMN `{$col}` {$definition}");
            echo "Added column '{$col}' to 'tbl_employee'.\n";
        }
    }

    if (!indexExists($pdo, 'tbl_employee', 'idx_employee_telegram_link_token')) {
        $dbRepo->executeCommand("ALTER TABLE `tbl_employee` ADD INDEX `idx_employee_telegram_link_token` (`telegram_link_token`)");
        echo "Created index on 'tbl_employee.telegram_link_token'.\n";
    }

    // 3. Alter tbl_user dynamically
    $userColumns = [
        'telegram_chat_id' => "BIGINT NULL",
        'telegram_username' => "VARCHAR(255) NULL",
        'telegram_first_name' => "VARCHAR(255) NULL",
        'telegram_link_token' => "VARCHAR(64) NULL",
        'telegram_link_expires_at' => "DATETIME NULL",
        'telegram_linked_at' => "DATETIME NULL",
        'telegram_is_linked' => "TINYINT(1) NOT NULL DEFAULT 0",
        'telegram_lang' => "VARCHAR(10) NOT NULL DEFAULT 'ar'",
        'telegram_notifications' => "TEXT NULL"
    ];

    foreach ($userColumns as $col => $definition) {
        if (!columnExists($pdo, 'tbl_user', $col)) {
            $dbRepo->executeCommand("ALTER TABLE `tbl_user` ADD COLUMN `{$col}` {$definition}");
            echo "Added column '{$col}' to 'tbl_user'.\n";
        }
    }

    if (!indexExists($pdo, 'tbl_user', 'idx_user_telegram_chat_id')) {
        $dbRepo->executeCommand("ALTER TABLE `tbl_user` ADD INDEX `idx_user_telegram_chat_id` (`telegram_chat_id`)");
        echo "Created index on 'tbl_user.telegram_chat_id'.\n";
    }

    if (!indexExists($pdo, 'tbl_user', 'idx_user_telegram_link_token')) {
        $dbRepo->executeCommand("ALTER TABLE `tbl_user` ADD INDEX `idx_user_telegram_link_token` (`telegram_link_token`)");
        echo "Created index on 'tbl_user.telegram_link_token'.\n";
    }

    // 4. Create tbl_telegram_logs
    $dbRepo->executeCommand("
        CREATE TABLE IF NOT EXISTS `tbl_telegram_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chat_id` VARCHAR(255) NOT NULL,
            `user_type` VARCHAR(50) NULL,
            `user_id` INT NULL,
            `telegram_message_id` BIGINT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `action_name` VARCHAR(100) NOT NULL,
            `payload` LONGTEXT NULL,
            `status` VARCHAR(50) NOT NULL,
            `ip_address` VARCHAR(45) NULL,
            `error_message` TEXT NULL,
            `latency_ms` INT DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_telegram_log_chat` (`chat_id`),
            KEY `idx_telegram_log_user` (`user_type`, `user_id`),
            KEY `idx_telegram_log_type` (`message_type`),
            KEY `idx_telegram_log_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Verified 'tbl_telegram_logs' table exists.\n";

    // 5. Create tbl_telegram_queue
    $dbRepo->executeCommand("
        CREATE TABLE IF NOT EXISTS `tbl_telegram_queue` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chat_id` VARCHAR(255) NOT NULL,
            `method` VARCHAR(50) NOT NULL,
            `payload` LONGTEXT NOT NULL,
            `attempts` INT NOT NULL DEFAULT 0,
            `max_attempts` INT NOT NULL DEFAULT 3,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `error_message` TEXT NULL,
            `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_telegram_queue_status` (`status`),
            KEY `idx_telegram_queue_next` (`next_attempt_at`),
            KEY `idx_telegram_queue_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Verified 'tbl_telegram_queue' table exists.\n";

    // 6. Create tbl_telegram_conversation_state
    $dbRepo->executeCommand("
        CREATE TABLE IF NOT EXISTS `tbl_telegram_conversation_state` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chat_id` VARCHAR(255) NOT NULL,
            `state_key` VARCHAR(100) NOT NULL,
            `payload` LONGTEXT NULL,
            `expires_at` DATETIME NOT NULL,
            UNIQUE KEY `uk_telegram_state_chat` (`chat_id`),
            KEY `idx_telegram_state_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Verified 'tbl_telegram_conversation_state' table exists.\n";

    // 7. Create tbl_telegram_audit
    $dbRepo->executeCommand("
        CREATE TABLE IF NOT EXISTS `tbl_telegram_audit` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT NOT NULL,
            `action_name` VARCHAR(100) NOT NULL,
            `previous_value` TEXT NULL,
            `new_value` TEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_telegram_audit_admin` (`admin_id`),
            KEY `idx_telegram_audit_action` (`action_name`),
            KEY `idx_telegram_audit_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Verified 'tbl_telegram_audit' table exists.\n";

    // Initialize default values for newly added tbl_settings columns
    $dbRepo->executeCommand("
        UPDATE `tbl_settings`
        SET
            `telegram_webhook_url` = COALESCE(`telegram_webhook_url`, ''),
            `telegram_secret_token` = COALESCE(`telegram_secret_token`, MD5(RAND())),
            `telegram_is_enabled` = COALESCE(`telegram_is_enabled`, 0)
        WHERE `id` = 1
    ");

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
