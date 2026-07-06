<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

try {
    // Providers
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_providers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        api_key VARCHAR(255) NULL,
        base_url VARCHAR(255) NULL,
        model VARCHAR(100) NULL,
        max_tokens INT DEFAULT 2000,
        temperature DECIMAL(3,2) DEFAULT 0.70,
        is_enabled TINYINT(1) DEFAULT 0,
        priority INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_providers created.\n";

    // Insert dummy providers if none exist
    $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_providers");
    if ($stmt->fetchColumn() == 0) {
        $dbRepo->executeCommand("INSERT INTO tbl_ai_providers (name, model, is_enabled, priority) VALUES ('OpenAI', 'gpt-4o', 1, 10)");
        $dbRepo->executeCommand("INSERT INTO tbl_ai_providers (name, model, is_enabled, priority) VALUES ('Gemini', 'gemini-1.5-pro', 1, 5)");
    }

    // Prompts
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_prompts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prompt_type VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        version INT DEFAULT 1,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_prompts created.\n";

    // Prompt History
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_prompt_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prompt_id INT NOT NULL,
        content TEXT NOT NULL,
        version INT NOT NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (prompt_id) REFERENCES tbl_ai_prompts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_prompt_history created.\n";

    // Tasks (Universal Engine)
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_type VARCHAR(100) NOT NULL,
        entity_type VARCHAR(100) NOT NULL,
        entity_id INT NOT NULL,
        language VARCHAR(10) DEFAULT 'ar',
        provider_id INT NULL,
        prompt_id INT NULL,
        priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') DEFAULT 'NORMAL',
        status ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED') DEFAULT 'PENDING',
        payload JSON NULL,
        result JSON NULL,
        error_message TEXT NULL,
        retries INT DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        KEY idx_status (status),
        KEY idx_priority (priority),
        FOREIGN KEY (provider_id) REFERENCES tbl_ai_providers(id) ON DELETE SET NULL,
        FOREIGN KEY (prompt_id) REFERENCES tbl_ai_prompts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_tasks created.\n";

    // Metrics
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        provider_id INT NULL,
        model VARCHAR(100) NULL,
        prompt_tokens INT DEFAULT 0,
        completion_tokens INT DEFAULT 0,
        total_cost DECIMAL(10,6) DEFAULT 0.000000,
        duration_ms INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tbl_ai_tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (provider_id) REFERENCES tbl_ai_providers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_metrics created.\n";

    // Add language column to product tables if missing
    $tablesToAlter = ['tbl_ai_product', 'tbl_ai_keyword', 'tbl_ai_faq', 'tbl_ai_objection', 'tbl_ai_training'];
    foreach ($tablesToAlter as $t) {
        try {
            $stmt = $dbRepo->query("SHOW COLUMNS FROM {$t} LIKE 'language'");
            if ($stmt->rowCount() == 0) {
                $dbRepo->executeCommand("ALTER TABLE {$t} ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'ar'");
                // Update primary/unique keys if necessary, but we'll handle uniqueness at app level for now
                echo "Added language column to {$t}.\n";
            }
        } catch (Exception $e) {
            echo "Error altering {$t}: " . $e->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
