-- Phase 3: Telegram Order Management Tables

CREATE TABLE IF NOT EXISTS tbl_order_edit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    employee_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_edit_order (order_id),
    KEY idx_edit_employee (employee_id),
    KEY idx_edit_field (field_name),
    KEY idx_edit_at (edited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_order_cancellation_reason (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    employee_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cancel_order (order_id),
    KEY idx_cancel_employee (employee_id),
    KEY idx_cancel_reason (reason),
    KEY idx_cancel_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_telegram_action_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    order_id INT NOT NULL DEFAULT 0,
    action_type VARCHAR(50) NOT NULL,
    telegram_user_id BIGINT DEFAULT NULL,
    payload TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_action_employee (employee_id),
    KEY idx_action_order (order_id),
    KEY idx_action_type (action_type),
    KEY idx_action_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
