<?php
require 'admin/inc/config.php';

try {
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_omni_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(100) NOT NULL, -- Incoming Webhook, AI Response, Order Created, etc.
        entity_type VARCHAR(50) DEFAULT NULL, -- conversation, customer, order
        entity_id INT DEFAULT NULL,
        conversation_id INT DEFAULT NULL,
        customer_id INT DEFAULT NULL,
        channel VARCHAR(50) DEFAULT NULL, -- meta, whatsapp
        user_id INT DEFAULT NULL, -- if triggered by human employee
        ai_agent_id INT DEFAULT NULL,
        status ENUM('SUCCESS', 'FAILED', 'RETRY', 'PENDING', 'IGNORED') DEFAULT 'SUCCESS',
        duration_ms INT DEFAULT 0,
        metadata JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_type (event_type),
        INDEX idx_entity (entity_type, entity_id),
        INDEX idx_conv (conversation_id),
        INDEX idx_cust (customer_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "tbl_omni_events created successfully.\n";

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
