CREATE TABLE IF NOT EXISTS tbl_telegram_delivery_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL DEFAULT 0,
    employee_id INT NOT NULL DEFAULT 0,
    telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
    telegram_message_id BIGINT DEFAULT NULL,
    delivery_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    response_payload TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_delivery_order (order_id),
    KEY idx_delivery_employee (employee_id),
    KEY idx_delivery_status (delivery_status),
    KEY idx_delivery_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
