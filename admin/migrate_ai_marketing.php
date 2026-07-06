<?php
require_once __DIR__ . '/inc/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_marketing_ai_recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            campaign_id VARCHAR(100) NULL,
            adset_id VARCHAR(100) NULL,
            ad_id VARCHAR(100) NULL,
            creative_id VARCHAR(100) NULL,
            lead_id VARCHAR(100) NULL,
            customer_id VARCHAR(100) NULL,
            recommendation_type VARCHAR(50) NOT NULL,
            recommendation JSON NOT NULL,
            reasoning TEXT NOT NULL,
            confidence_score DECIMAL(5,2) NOT NULL,
            evidence JSON NULL,
            expected_impact JSON NULL,
            risks JSON NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'NEW',
            model_provider VARCHAR(50) NULL,
            model_name VARCHAR(100) NULL,
            prompt_version VARCHAR(50) NULL,
            context_version VARCHAR(50) NULL,
            tokens_used INT NULL,
            cost DECIMAL(10,4) NULL,
            execution_time_ms INT NULL,
            is_applied BOOLEAN DEFAULT 0,
            feedback_roas_improvement DECIMAL(10,2) NULL,
            feedback_revenue_improvement DECIMAL(10,2) NULL,
            feedback_was_wrong BOOLEAN DEFAULT 0,
            version INT DEFAULT 1,
            parent_recommendation_id INT NULL,
            approved_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_campaign (campaign_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "tbl_marketing_ai_recommendations created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
