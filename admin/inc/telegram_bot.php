<?php
if (!defined('TELEGRAM_BOT_LOADED')) {
    define('TELEGRAM_BOT_LOADED', true);

    if (!function_exists('telegram_ensure_tables')) {
        function telegram_ensure_tables(PDO $pdo): void { global $dbRepo;
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_telegram_delivery_log (
                    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL DEFAULT 0,
                    employee_id INT NOT NULL DEFAULT 0, telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
                    telegram_message_id BIGINT DEFAULT NULL, delivery_status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    response_payload TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_delivery_order (order_id), KEY idx_delivery_employee (employee_id),
                    KEY idx_delivery_status (delivery_status), KEY idx_delivery_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_order_edit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL DEFAULT 0,
                    action VARCHAR(50) NOT NULL DEFAULT '', details TEXT DEFAULT NULL,
                    performed_by INT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_order_id (order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_user (
                    id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(255) DEFAULT '',
                    email VARCHAR(255) DEFAULT '', phone VARCHAR(50) DEFAULT '',
                    password VARCHAR(255) DEFAULT '', role VARCHAR(50) DEFAULT 'Employee',
                    telegram_chat_id VARCHAR(255) DEFAULT NULL, telegram_username VARCHAR(255) DEFAULT NULL,
                    telegram_first_name VARCHAR(255) DEFAULT NULL, telegram_is_linked TINYINT(1) DEFAULT 0,
                    telegram_linked_at DATETIME DEFAULT NULL, telegram_link_token VARCHAR(255) DEFAULT NULL,
                    telegram_link_expires DATETIME DEFAULT NULL, participate_in_assignment TINYINT(1) DEFAULT 1,
                    assignment_weight INT DEFAULT 1, availability_status VARCHAR(20) DEFAULT 'available',
                    max_active_orders INT DEFAULT 5, dashboard_prefs TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Exception $e) {}
        }
    }

    if (!function_exists('telegram_send_message')) {
        function telegram_send_message($chat_id, $message, $parse_mode = 'HTML') { return false; }
    }
    if (!function_exists('telegram_send_order_notification')) {
        function telegram_send_order_notification($order_data, $chat_id = null) { return false; }
    }
    if (!function_exists('telegram_generate_link_token')) {
        function telegram_generate_link_token($user_id, $user_type = 'manager') { return ['success' => false, 'error' => 'Telegram bot not configured']; }
    }
    if (!function_exists('telegram_unlink_account')) {
        function telegram_unlink_account($user_id, $user_type = 'manager') { return ['success' => false, 'error' => 'Telegram bot not configured']; }
    }
    if (!function_exists('telegram_handle_webhook')) {
        function telegram_handle_webhook($data) { return ['ok' => false]; }
    }
}
