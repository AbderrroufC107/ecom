CREATE TABLE IF NOT EXISTS tbl_employee (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_employee_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_order_assignment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    employee_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by VARCHAR(100) NOT NULL DEFAULT 'auto',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    UNIQUE KEY uk_assignment_order (order_id),
    KEY idx_assignment_employee (employee_id),
    KEY idx_assignment_status (status),
    KEY idx_assignment_assigned (assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
