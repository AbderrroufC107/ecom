<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

try {
    // 1. Channels
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_omni_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT DEFAULT 1,
        channel_type VARCHAR(50) NOT NULL, -- e.g. 'facebook_page', 'instagram_account', 'whatsapp_number'
        provider VARCHAR(50) NOT NULL, -- e.g. 'meta', 'whatsapp', 'telegram'
        account_name VARCHAR(255) NOT NULL,
        account_id VARCHAR(255) NOT NULL, -- The page ID, phone number ID, etc.
        access_token TEXT NULL,
        refresh_token TEXT NULL,
        webhook_secret VARCHAR(255) NULL,
        status VARCHAR(50) DEFAULT 'ACTIVE',
        settings JSON NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_omni_channels created.\n";

    // 2. Customers (Customer 360)
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_omni_customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NULL,
        last_name VARCHAR(255) NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        merged_from_id INT NULL, -- If this profile was merged into another
        lead_score INT DEFAULT 0,
        journey_stage VARCHAR(50) DEFAULT 'NEW',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_omni_customers created.\n";

    // 3. Customer Identities (Mapping platform specific user IDs to omni_customers)
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_omni_customer_identities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL, -- 'meta', 'whatsapp'
        platform_user_id VARCHAR(255) NOT NULL, -- PSID, IGID, WA Number
        profile_pic_url TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES tbl_omni_customers(id) ON DELETE CASCADE,
        UNIQUE INDEX idx_platform_user (provider, platform_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_omni_customer_identities created.\n";

    // 4. Conversations
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_omni_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        current_channel_id INT NOT NULL, -- Channel currently handling this
        platform_conversation_id VARCHAR(255) NULL, -- Thread ID if applicable
        language VARCHAR(10) DEFAULT 'ar',
        current_status VARCHAR(50) DEFAULT 'OPEN', -- 'OPEN', 'CLOSED', 'SNOOZED'
        assigned_agent INT NULL, -- Human user_id
        ai_status VARCHAR(50) DEFAULT 'ACTIVE', -- 'ACTIVE', 'PAUSED'
        current_product_id INT NULL,
        campaign_id VARCHAR(255) NULL,
        ad_id VARCHAR(255) NULL,
        ad_set_id VARCHAR(255) NULL,
        sla_deadline DATETIME NULL,
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES tbl_omni_customers(id) ON DELETE CASCADE,
        FOREIGN KEY (current_channel_id) REFERENCES tbl_omni_channels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_omni_conversations created.\n";

    // 5. Timeline (Messages, Comments, System Events)
    $dbRepo->executeCommand("
    CREATE TABLE IF NOT EXISTS tbl_omni_timeline (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        type VARCHAR(50) NOT NULL, -- 'MESSAGE', 'COMMENT', 'SYSTEM', 'ORDER_CREATED', etc.
        sender_type VARCHAR(50) NOT NULL, -- 'CUSTOMER', 'AGENT', 'AI', 'SYSTEM'
        sender_id VARCHAR(255) NULL, -- Can be agent ID, or platform user ID
        content LONGTEXT NULL,
        media_urls JSON NULL,
        post_id VARCHAR(255) NULL, -- If comment
        comment_id VARCHAR(255) NULL, -- If comment
        reply_to_id VARCHAR(255) NULL, -- If replying to a specific message/comment
        metadata JSON NULL, -- Raw payload, ad context, etc.
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES tbl_omni_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_omni_timeline created.\n";

    echo "All OmniChannel Management tables created successfully.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
