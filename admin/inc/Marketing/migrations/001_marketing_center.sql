-- ============================================================
-- Marketing Center Enterprise - Database Migration
-- Version: 1.0.0
-- Date: 2026-06-28
-- Safe Migration: Uses IF NOT EXISTS, no existing tables modified
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- 1. tbl_meta_ad_accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_ad_accounts` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`        INT UNSIGNED NOT NULL,
    `account_id`       VARCHAR(60)  NOT NULL COMMENT 'Meta act_XXXXXXXXX',
    `account_name`     VARCHAR(255) NOT NULL DEFAULT '',
    `currency`         VARCHAR(10)  NOT NULL DEFAULT 'DZD',
    `timezone`         VARCHAR(100) NOT NULL DEFAULT 'Africa/Algiers',
    `status`           ENUM('ACTIVE','PAUSED','DISABLED') NOT NULL DEFAULT 'ACTIVE',
    `channel_id`       INT UNSIGNED DEFAULT NULL COMMENT 'FK tbl_omni_channels.id',
    `graph_api_ver`    VARCHAR(10)  NOT NULL DEFAULT 'v19.0',
    `business_id`      VARCHAR(60)  DEFAULT NULL,
    `business_name`    VARCHAR(255) DEFAULT NULL,
    `is_deleted`       TINYINT(1)   NOT NULL DEFAULT 0,
    `last_synced_at`   DATETIME     DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_account_tenant` (`tenant_id`, `account_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Advertising Accounts';


-- ============================================================
-- 2. tbl_meta_campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_campaigns` (
    `id`                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`               INT UNSIGNED NOT NULL,
    `ad_account_id`           INT UNSIGNED NOT NULL COMMENT 'FK tbl_meta_ad_accounts.id',
    `meta_campaign_id`        VARCHAR(100) NOT NULL COMMENT 'Meta campaign ID',
    `name`                    VARCHAR(255) NOT NULL DEFAULT '',
    `objective`               VARCHAR(100) DEFAULT NULL COMMENT 'OUTCOME_SALES|OUTCOME_TRAFFIC|OUTCOME_LEADS',
    `status`                  ENUM('ACTIVE','PAUSED','DELETED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    `effective_status`        VARCHAR(50)  DEFAULT NULL COMMENT 'Meta effective_status',
    `budget_daily`            DECIMAL(14,2) DEFAULT NULL COMMENT 'In local currency cents',
    `budget_lifetime`         DECIMAL(14,2) DEFAULT NULL,
    `spend_cap`               DECIMAL(14,2) DEFAULT NULL,
    `start_time`              DATETIME     DEFAULT NULL,
    `end_time`                DATETIME     DEFAULT NULL,
    `bid_strategy`            VARCHAR(100) DEFAULT NULL,
    `special_ad_category`     VARCHAR(100) NOT NULL DEFAULT 'NONE',
    `product_ids`             JSON         DEFAULT NULL COMMENT 'Linked store product IDs',
    `buying_type`             VARCHAR(50)  DEFAULT 'AUCTION',
    `meta_json`               JSON         DEFAULT NULL COMMENT 'Full Meta API response',
    `is_deleted`              TINYINT(1)   NOT NULL DEFAULT 0,
    `last_synced_at`          DATETIME     DEFAULT NULL,
    `sync_hash`               VARCHAR(64)  DEFAULT NULL COMMENT 'SHA256 of last synced state',
    `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_meta_campaign` (`tenant_id`, `meta_campaign_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_account` (`ad_account_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_last_synced` (`last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Advertising Campaigns';


-- ============================================================
-- 3. tbl_meta_ad_sets
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_ad_sets` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`            INT UNSIGNED NOT NULL,
    `campaign_id`          INT UNSIGNED NOT NULL COMMENT 'FK tbl_meta_campaigns.id',
    `meta_adset_id`        VARCHAR(100) NOT NULL,
    `name`                 VARCHAR(255) DEFAULT '',
    `status`               ENUM('ACTIVE','PAUSED','DELETED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    `effective_status`     VARCHAR(50)  DEFAULT NULL,
    `budget_daily`         DECIMAL(14,2) DEFAULT NULL,
    `budget_lifetime`      DECIMAL(14,2) DEFAULT NULL,
    `bid_amount`           DECIMAL(14,2) DEFAULT NULL,
    `bid_strategy`         VARCHAR(100) DEFAULT NULL,
    `optimization_goal`    VARCHAR(100) DEFAULT NULL COMMENT 'REACH|LINK_CLICKS|CONVERSIONS',
    `billing_event`        VARCHAR(100) DEFAULT NULL COMMENT 'IMPRESSIONS|LINK_CLICKS',
    `targeting_json`       JSON         DEFAULT NULL,
    `destination_type`     VARCHAR(100) DEFAULT NULL,
    `promoted_object`      JSON         DEFAULT NULL,
    `pacing_type`          JSON         DEFAULT NULL,
    `rf_prediction_id`     VARCHAR(100) DEFAULT NULL,
    `start_time`           DATETIME     DEFAULT NULL,
    `end_time`             DATETIME     DEFAULT NULL,
    `is_deleted`           TINYINT(1)   NOT NULL DEFAULT 0,
    `last_synced_at`       DATETIME     DEFAULT NULL,
    `meta_json`            JSON         DEFAULT NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_meta_adset` (`tenant_id`, `meta_adset_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Ad Sets';


-- ============================================================
-- 4. tbl_meta_ads
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_ads` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`         INT UNSIGNED NOT NULL,
    `adset_id`          INT UNSIGNED NOT NULL COMMENT 'FK tbl_meta_ad_sets.id',
    `meta_ad_id`        VARCHAR(100) NOT NULL,
    `name`              VARCHAR(255) DEFAULT '',
    `status`            ENUM('ACTIVE','PAUSED','DELETED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    `effective_status`  VARCHAR(50)  DEFAULT NULL,
    `creative_id`       INT UNSIGNED DEFAULT NULL COMMENT 'FK tbl_meta_creatives.id',
    `tracking_specs`    JSON         DEFAULT NULL,
    `bid_amount`        DECIMAL(14,2) DEFAULT NULL,
    `conversion_specs`  JSON         DEFAULT NULL,
    `is_deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
    `last_synced_at`    DATETIME     DEFAULT NULL,
    `meta_json`         JSON         DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_meta_ad` (`tenant_id`, `meta_ad_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_adset` (`adset_id`),
    INDEX `idx_creative` (`creative_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Ads';


-- ============================================================
-- 5. tbl_meta_creatives
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_creatives` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`           INT UNSIGNED NOT NULL,
    `ad_account_id`       INT UNSIGNED DEFAULT NULL,
    `meta_creative_id`    VARCHAR(100) DEFAULT NULL,
    `name`                VARCHAR(255) DEFAULT '',
    `type`                ENUM('IMAGE','VIDEO','CAROUSEL','COLLECTION','DYNAMIC','CATALOG') NOT NULL DEFAULT 'IMAGE',
    `headline`            VARCHAR(255) DEFAULT NULL,
    `body`                TEXT         DEFAULT NULL COMMENT 'Primary text',
    `description`         VARCHAR(500) DEFAULT NULL,
    `cta_type`            VARCHAR(100) DEFAULT NULL COMMENT 'SHOP_NOW|LEARN_MORE|SIGN_UP',
    `image_url`           VARCHAR(1000) DEFAULT NULL,
    `image_hash`          VARCHAR(100)  DEFAULT NULL COMMENT 'Meta image hash',
    `video_url`           VARCHAR(1000) DEFAULT NULL,
    `video_id`            VARCHAR(100)  DEFAULT NULL,
    `link_url`            VARCHAR(1000) DEFAULT NULL,
    `display_link`        VARCHAR(255) DEFAULT NULL,
    `carousel_cards`      JSON         DEFAULT NULL,
    `product_id`          INT UNSIGNED DEFAULT NULL COMMENT 'FK tbl_product.p_id',
    `ai_generated`        TINYINT(1)   NOT NULL DEFAULT 0,
    `ai_prompt`           TEXT         DEFAULT NULL,
    `status`              ENUM('DRAFT','ACTIVE','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
    `meta_json`           JSON         DEFAULT NULL,
    `is_deleted`          TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_account` (`ad_account_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Ad Creatives - images, videos, carousels';


-- ============================================================
-- 6. tbl_meta_lead_forms
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_lead_forms` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`         INT UNSIGNED NOT NULL,
    `ad_account_id`     INT UNSIGNED NOT NULL,
    `meta_form_id`      VARCHAR(100) NOT NULL,
    `name`              VARCHAR(255) DEFAULT '',
    `status`            ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    `locale`            VARCHAR(20)  DEFAULT 'ar_DZ',
    `questions_json`    JSON         DEFAULT NULL COMMENT 'Form questions schema',
    `privacy_policy_url` VARCHAR(1000) DEFAULT NULL,
    `follow_up_action_url` VARCHAR(1000) DEFAULT NULL,
    `leads_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `context_card`      JSON         DEFAULT NULL,
    `is_deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
    `last_synced_at`    DATETIME     DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_form_tenant` (`tenant_id`, `meta_form_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_account` (`ad_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Lead Ad Forms';


-- ============================================================
-- 7. tbl_meta_campaign_insights
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_campaign_insights` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`           INT UNSIGNED NOT NULL,
    `level`               ENUM('CAMPAIGN','ADSET','AD') NOT NULL DEFAULT 'CAMPAIGN',
    `entity_meta_id`      VARCHAR(100) NOT NULL COMMENT 'campaign/adset/ad Meta ID',
    `campaign_id`         INT UNSIGNED DEFAULT NULL,
    `adset_id`            INT UNSIGNED DEFAULT NULL,
    `ad_id`               INT UNSIGNED DEFAULT NULL,
    `date_start`          DATE NOT NULL,
    `date_stop`           DATE NOT NULL,
    `impressions`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `reach`               BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `frequency`           DECIMAL(10,4)  NOT NULL DEFAULT 0,
    `clicks`              INT UNSIGNED   NOT NULL DEFAULT 0,
    `unique_clicks`       INT UNSIGNED   NOT NULL DEFAULT 0,
    `spend`               DECIMAL(14,2)  NOT NULL DEFAULT 0,
    `cpc`                 DECIMAL(10,4)  DEFAULT NULL,
    `cpm`                 DECIMAL(10,4)  DEFAULT NULL,
    `ctr`                 DECIMAL(10,6)  DEFAULT NULL,
    `cpp`                 DECIMAL(10,4)  DEFAULT NULL,
    `leads`               INT UNSIGNED   NOT NULL DEFAULT 0,
    `cost_per_lead`       DECIMAL(14,2)  DEFAULT NULL,
    `purchases`           INT UNSIGNED   NOT NULL DEFAULT 0,
    `purchase_value`      DECIMAL(14,2)  NOT NULL DEFAULT 0,
    `roas`                DECIMAL(10,4)  DEFAULT NULL,
    `cost_per_purchase`   DECIMAL(14,2)  DEFAULT NULL,
    `link_clicks`         INT UNSIGNED   NOT NULL DEFAULT 0,
    `post_engagements`    INT UNSIGNED   NOT NULL DEFAULT 0,
    `page_likes`          INT UNSIGNED   NOT NULL DEFAULT 0,
    `video_views`         INT UNSIGNED   NOT NULL DEFAULT 0,
    `video_view_3s`       INT UNSIGNED   NOT NULL DEFAULT 0,
    `actions_json`        JSON           DEFAULT NULL COMMENT 'Full actions array from Meta',
    `raw_json`            JSON           DEFAULT NULL,
    `synced_at`           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_insight` (`tenant_id`, `level`, `entity_meta_id`, `date_start`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_date` (`date_start`, `date_stop`),
    INDEX `idx_entity` (`entity_meta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Campaign Performance Insights - time-series data';


-- ============================================================
-- 8. tbl_meta_sync_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_sync_logs` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `sync_type`       ENUM('CAMPAIGNS','ADSETS','ADS','INSIGHTS','LEADS','CREATIVES','LEAD_FORMS','ACCOUNTS') NOT NULL,
    `direction`       ENUM('IMPORT','EXPORT','BIDIRECTIONAL') NOT NULL DEFAULT 'IMPORT',
    `status`          ENUM('PENDING','RUNNING','SUCCESS','FAILED','PARTIAL') NOT NULL DEFAULT 'PENDING',
    `entity_id`       VARCHAR(100) DEFAULT NULL COMMENT 'campaign/adset/ad Meta ID if specific',
    `records_synced`  INT UNSIGNED NOT NULL DEFAULT 0,
    `records_failed`  INT UNSIGNED NOT NULL DEFAULT 0,
    `error_message`   TEXT         DEFAULT NULL,
    `detail_json`     JSON         DEFAULT NULL,
    `started_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`    DATETIME     DEFAULT NULL,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`sync_type`),
    INDEX `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Marketing sync operation logs';


-- ============================================================
-- 9. tbl_meta_audiences
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_audiences` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `ad_account_id`   INT UNSIGNED NOT NULL,
    `meta_audience_id` VARCHAR(100) DEFAULT NULL,
    `name`            VARCHAR(255) NOT NULL DEFAULT '',
    `type`            ENUM('SAVED','CUSTOM','LOOKALIKE','AI_GENERATED') NOT NULL DEFAULT 'SAVED',
    `subtype`         VARCHAR(100) DEFAULT NULL COMMENT 'WEBSITE|CUSTOMER_LIST|ENGAGEMENT|LOOKALIKE',
    `description`     TEXT DEFAULT NULL,
    `targeting_json`  JSON DEFAULT NULL,
    `approximate_count` BIGINT UNSIGNED DEFAULT NULL,
    `operation_status` JSON DEFAULT NULL,
    `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
    `last_synced_at`  DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meta Audience Manager - saved, custom, lookalike audiences';


-- ============================================================
-- 10. tbl_meta_ab_tests
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_ab_tests` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `status`          ENUM('DRAFT','RUNNING','COMPLETED','PAUSED') NOT NULL DEFAULT 'DRAFT',
    `test_type`       ENUM('CREATIVE','AUDIENCE','PLACEMENT','BUDGET') NOT NULL DEFAULT 'CREATIVE',
    `winner_metric`   ENUM('CTR','CPA','ROAS','REVENUE','ORDERS') NOT NULL DEFAULT 'ROAS',
    `winner_variant`  VARCHAR(10) DEFAULT NULL,
    `start_date`      DATE DEFAULT NULL,
    `end_date`        DATE DEFAULT NULL,
    `variants_json`   JSON DEFAULT NULL COMMENT 'Array of variant configs',
    `results_json`    JSON DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='A/B Testing campaigns';


-- ============================================================
-- 11. tbl_meta_automation_rules
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_automation_rules` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `scope`           ENUM('CAMPAIGN','ADSET','AD','ACCOUNT') NOT NULL DEFAULT 'CAMPAIGN',
    `entity_id`       INT UNSIGNED DEFAULT NULL COMMENT 'Optional: specific entity',
    `condition_json`  JSON NOT NULL COMMENT 'metric, operator, threshold',
    `action_json`     JSON NOT NULL COMMENT 'ALERT|PAUSE|EMAIL|TELEGRAM',
    `alert_channels`  JSON DEFAULT NULL COMMENT '["dashboard","telegram","email"]',
    `trigger_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `last_triggered`  DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Marketing automation and alert rules';


-- ============================================================
-- 12. tbl_meta_attribution (Attribution Engine)
-- ============================================================
CREATE TABLE IF NOT EXISTS `tbl_meta_attribution` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`         INT UNSIGNED NOT NULL,
    `customer_id`       INT UNSIGNED DEFAULT NULL COMMENT 'FK tbl_omni_customers.id',
    `order_id`          INT UNSIGNED DEFAULT NULL COMMENT 'FK tbl_order.id',
    `meta_campaign_id`  VARCHAR(100) DEFAULT NULL,
    `meta_adset_id`     VARCHAR(100) DEFAULT NULL,
    `meta_ad_id`        VARCHAR(100) DEFAULT NULL,
    `meta_creative_id`  VARCHAR(100) DEFAULT NULL,
    `lead_form_id`      VARCHAR(100) DEFAULT NULL,
    `leadgen_id`        VARCHAR(100) DEFAULT NULL,
    `product_id`        INT UNSIGNED DEFAULT NULL,
    `touchpoints_json`  JSON DEFAULT NULL COMMENT 'Full journey touchpoints',
    `first_touch_at`    DATETIME DEFAULT NULL,
    `conversion_at`     DATETIME DEFAULT NULL,
    `revenue`           DECIMAL(14,2) DEFAULT NULL,
    `spend_attributed`  DECIMAL(14,2) DEFAULT NULL,
    `roas_attributed`   DECIMAL(10,4) DEFAULT NULL,
    `model`             ENUM('LAST_CLICK','FIRST_CLICK','LINEAR','TIME_DECAY') NOT NULL DEFAULT 'LAST_CLICK',
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_order` (`order_id`),
    INDEX `idx_campaign` (`meta_campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Multi-touch Attribution Engine data';


-- ============================================================
-- Safe additions to existing tables
-- ============================================================

-- Add marketing context to tbl_omni_conversations (if not exists)
ALTER TABLE `tbl_omni_conversations`
    ADD COLUMN IF NOT EXISTS `meta_campaign_id` VARCHAR(100) DEFAULT NULL AFTER `ad_id`,
    ADD COLUMN IF NOT EXISTS `meta_adset_id`    VARCHAR(100) DEFAULT NULL AFTER `meta_campaign_id`,
    ADD COLUMN IF NOT EXISTS `meta_creative_id` VARCHAR(100) DEFAULT NULL AFTER `meta_adset_id`,
    ADD COLUMN IF NOT EXISTS `lead_form_id`     VARCHAR(100) DEFAULT NULL AFTER `meta_creative_id`,
    ADD COLUMN IF NOT EXISTS `leadgen_id`       VARCHAR(100) DEFAULT NULL AFTER `lead_form_id`,
    ADD COLUMN IF NOT EXISTS `lead_score`       TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `leadgen_id`,
    ADD COLUMN IF NOT EXISTS `lead_source`      VARCHAR(100) DEFAULT NULL AFTER `lead_score`;

-- Add graph_api_version to tbl_settings if not exists
ALTER TABLE `tbl_settings`
    ADD COLUMN IF NOT EXISTS `graph_api_version`         VARCHAR(10)  NOT NULL DEFAULT 'v19.0',
    ADD COLUMN IF NOT EXISTS `meta_app_id`               VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `marketing_api_enabled`     TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `marketing_sync_interval`   INT UNSIGNED NOT NULL DEFAULT 3600,
    ADD COLUMN IF NOT EXISTS `marketing_insights_days`   INT UNSIGNED NOT NULL DEFAULT 30,
    ADD COLUMN IF NOT EXISTS `marketing_alert_telegram`  TINYINT(1)   NOT NULL DEFAULT 0;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Verification
-- ============================================================
SELECT 'Migration completed successfully' AS status;
SELECT TABLE_NAME, TABLE_ROWS 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME LIKE 'tbl_meta_%'
ORDER BY TABLE_NAME;
