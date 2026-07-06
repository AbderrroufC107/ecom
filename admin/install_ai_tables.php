<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

try {
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_product (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL UNIQUE,
        ai_version INT DEFAULT 1,
        selling_title VARCHAR(255),
        short_pitch TEXT,
        long_pitch TEXT,
        cta VARCHAR(255),
        negotiable TINYINT(1) DEFAULT 0,
        lowest_price DECIMAL(10,2) DEFAULT 0.00,
        max_discount_pct DECIMAL(5,2) DEFAULT 0.00,
        discount_conditions TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_product created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_keyword (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        keyword VARCHAR(100) NOT NULL,
        is_synonym TINYINT(1) DEFAULT 0,
        KEY idx_keyword (keyword),
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_keyword created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_faq (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_faq created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_objection (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        objection VARCHAR(255) NOT NULL,
        best_reply TEXT NOT NULL,
        priority INT DEFAULT 0,
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_objection created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_training (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        topic VARCHAR(100) NOT NULL,
        training_reply TEXT NOT NULL,
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_training created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_related_product (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        related_p_id INT NOT NULL,
        relation_type ENUM('substitute', 'complement', 'upsell', 'cross_sell') NOT NULL,
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE,
        FOREIGN KEY (related_p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_related_product created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        media_type VARCHAR(50) NOT NULL,
        media_url TEXT NOT NULL,
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_media created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_campaign (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL,
        platform VARCHAR(50) NOT NULL,
        campaign_id VARCHAR(100),
        ad_id VARCHAR(100),
        post_id VARCHAR(100),
        story_id VARCHAR(100),
        reel_id VARCHAR(100),
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_campaign created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p_id INT NOT NULL UNIQUE,
        times_asked INT DEFAULT 0,
        orders INT DEFAULT 0,
        conversion_rate DECIMAL(5,2) DEFAULT 0.00,
        best_campaign VARCHAR(100),
        most_objection VARCHAR(100),
        FOREIGN KEY (p_id) REFERENCES tbl_product(p_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_analytics created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_chat_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL,
        customer_identifier VARCHAR(100),
        role ENUM('user', 'assistant', 'system') NOT NULL,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_chat_log created.\n";

    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_embeddings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT NOT NULL,
        text_content TEXT,
        embedding TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_embeddings created.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
