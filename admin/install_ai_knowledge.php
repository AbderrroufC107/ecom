<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

try {
    // 1. Categories
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_knowledge_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT NULL,
        icon VARCHAR(100) NULL,
        color VARCHAR(50) NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES tbl_ai_knowledge_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_knowledge_categories created.\n";

    // Insert Default Categories
    $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_ai_knowledge_categories");
    if ($stmt->fetchColumn() == 0) {
        $cats = ['Company', 'Sales', 'Products', 'Shipping', 'Returns', 'Payments', 'FAQ', 'Legal', 'Marketing', 'Support', 'Style', 'Variables'];
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_knowledge_categories (name, slug) VALUES (?, ?)");
        foreach ($cats as $cat) {
            $stmt->execute([$cat, strtolower($cat)]);
        }
    }

    // 2. Knowledge Items
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        category_id INT NULL,
        knowledge_type VARCHAR(100) NOT NULL, -- Policy, Instruction, FAQ, Prompt, Template, Checklist, Sales Rule, Training, Script, Workflow, Company Info
        language VARCHAR(10) DEFAULT 'ar',
        content LONGTEXT NOT NULL,
        priority INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        version INT DEFAULT 1,
        valid_from DATETIME NULL,
        valid_until DATETIME NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES tbl_ai_knowledge_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_knowledge created.\n";

    // 3. Knowledge History
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_knowledge_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        knowledge_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        version INT NOT NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (knowledge_id) REFERENCES tbl_ai_knowledge(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_knowledge_history created.\n";

    // 4. Tags
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_tags created.\n";

    // 5. Knowledge Tags M2M
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_knowledge_tags (
        knowledge_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (knowledge_id, tag_id),
        FOREIGN KEY (knowledge_id) REFERENCES tbl_ai_knowledge(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tbl_ai_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_knowledge_tags created.\n";

    // 6. Knowledge Relations (Polymorphic)
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_knowledge_relations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        knowledge_id INT NOT NULL,
        entity_type VARCHAR(100) NOT NULL, -- 'product', 'category', 'brand', 'campaign', 'shipping_provider'
        entity_id INT NOT NULL,
        FOREIGN KEY (knowledge_id) REFERENCES tbl_ai_knowledge(id) ON DELETE CASCADE,
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_knowledge_relations created.\n";

    // 7. Attachments
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_ai_knowledge_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        knowledge_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (knowledge_id) REFERENCES tbl_ai_knowledge(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_ai_knowledge_attachments created.\n";

    // Alter tbl_ai_knowledge to add fulltext index for search (if not exists)
    try {
        $dbRepo->executeCommand("ALTER TABLE tbl_ai_knowledge ADD FULLTEXT INDEX ft_knowledge (title, content)");
        echo "Fulltext index added.\n";
    } catch (Exception $e) {
        // May already exist or unsupported engine
    }

    echo "All Knowledge Management tables created successfully.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
