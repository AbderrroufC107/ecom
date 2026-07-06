-- ============================================================
-- Migration: 001_meta_integration.sql
-- Phase 1: Meta Integration - Database & Security Foundations
-- Safe Migration: Only CREATE TABLE IF NOT EXISTS and
--                 ALTER TABLE ... ADD COLUMN IF NOT EXISTS
-- NO DROP TABLE / DROP COLUMN / RENAME COLUMN
-- ============================================================

-- ============================================================
-- SECTION 1: Upgrade tbl_omni_channels
-- ============================================================

ALTER TABLE `tbl_omni_channels`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) NOT NULL DEFAULT 1 AFTER `id`;

ALTER TABLE `tbl_omni_channels`
    ADD COLUMN IF NOT EXISTS `meta_page_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_channels`
    ADD COLUMN IF NOT EXISTS `meta_ig_account_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_channels`
    ADD COLUMN IF NOT EXISTS `token_expiry_at` DATETIME DEFAULT NULL;

ALTER TABLE `tbl_omni_channels`
    ADD COLUMN IF NOT EXISTS `permissions_scope` TEXT DEFAULT NULL;

ALTER TABLE `tbl_omni_channels`
    ADD COLUMN IF NOT EXISTS `token_encrypted` TINYINT(1) NOT NULL DEFAULT 0;

-- Index guard: idx_tenant on tbl_omni_channels
SET @dbname = DATABASE();
SET @tblname = 'tbl_omni_channels';
SET @idxname = 'idx_tenant';
SET @sql = (
    SELECT IF(
        COUNT(1) = 0,
        CONCAT('ALTER TABLE `', @tblname, '` ADD INDEX `', @idxname, '` (`tenant_id`)'),
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
      AND table_name   = @tblname
      AND index_name   = @idxname
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- SECTION 2: Upgrade tbl_omni_events
-- ============================================================

ALTER TABLE `tbl_omni_events`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) NOT NULL DEFAULT 1 AFTER `id`;

ALTER TABLE `tbl_omni_events`
    ADD COLUMN IF NOT EXISTS `event_fingerprint` VARCHAR(64) DEFAULT NULL;

ALTER TABLE `tbl_omni_events`
    ADD COLUMN IF NOT EXISTS `source_platform` VARCHAR(50) DEFAULT NULL;

ALTER TABLE `tbl_omni_events`
    ADD COLUMN IF NOT EXISTS `source_ad_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_events`
    ADD COLUMN IF NOT EXISTS `source_campaign_id` VARCHAR(100) DEFAULT NULL;

-- Unique index: idx_fingerprint on tbl_omni_events
SET @tblname = 'tbl_omni_events';
SET @idxname = 'idx_fingerprint';
SET @sql = (
    SELECT IF(
        COUNT(1) = 0,
        CONCAT('ALTER TABLE `', @tblname, '` ADD UNIQUE INDEX `', @idxname, '` (`event_fingerprint`)'),
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
      AND table_name   = @tblname
      AND index_name   = @idxname
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Composite index: idx_tenant_event on tbl_omni_events
SET @idxname = 'idx_tenant_event';
SET @sql = (
    SELECT IF(
        COUNT(1) = 0,
        CONCAT('ALTER TABLE `', @tblname, '` ADD INDEX `', @idxname, '` (`tenant_id`, `event_type`)'),
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
      AND table_name   = @tblname
      AND index_name   = @idxname
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- SECTION 3: Upgrade tbl_omni_customers
-- ============================================================

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) NOT NULL DEFAULT 1;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `lead_source` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `campaign_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `campaign_name` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `ad_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `ad_set_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `creative_id` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `source_platform` VARCHAR(50) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `utm_source` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `utm_medium` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `utm_campaign` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `utm_content` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `referral_url` TEXT DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `first_click_time` DATETIME DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `first_contact_time` DATETIME DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `acquisition_date` DATE DEFAULT NULL;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `ltv` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `lead_score` INT(11) NOT NULL DEFAULT 0;

ALTER TABLE `tbl_omni_customers`
    ADD COLUMN IF NOT EXISTS `journey_stage` VARCHAR(50) NOT NULL DEFAULT 'new';

-- Index: idx_tenant_customer on tbl_omni_customers
SET @tblname = 'tbl_omni_customers';
SET @idxname = 'idx_tenant_customer';
SET @sql = (
    SELECT IF(
        COUNT(1) = 0,
        CONCAT('ALTER TABLE `', @tblname, '` ADD INDEX `', @idxname, '` (`tenant_id`)'),
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
      AND table_name   = @tblname
      AND index_name   = @idxname
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: idx_campaign on tbl_omni_customers
SET @idxname = 'idx_campaign';
SET @sql = (
    SELECT IF(
        COUNT(1) = 0,
        CONCAT('ALTER TABLE `', @tblname, '` ADD INDEX `', @idxname, '` (`campaign_id`)'),
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = @dbname
      AND table_name   = @tblname
      AND index_name   = @idxname
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- SECTION 4: New table tbl_meta_pages
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_pages` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `tenant_id`           INT(11)      NOT NULL DEFAULT 1,
  `channel_id`          INT(11)               DEFAULT NULL,
  `page_id`             VARCHAR(100) NOT NULL,
  `page_name`           VARCHAR(255)          DEFAULT NULL,
  `page_category`       VARCHAR(100)          DEFAULT NULL,
  `ig_account_id`       VARCHAR(100)          DEFAULT NULL,
  `ig_username`         VARCHAR(100)          DEFAULT NULL,
  `token_secret_name`   VARCHAR(100)          DEFAULT NULL,
  `token_expiry_at`     DATETIME              DEFAULT NULL,
  `permissions`         TEXT                  DEFAULT NULL,
  `messenger_enabled`   TINYINT(1)   NOT NULL DEFAULT 1,
  `instagram_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
  `comments_enabled`    TINYINT(1)   NOT NULL DEFAULT 1,
  `marketing_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
  `status`              ENUM('ACTIVE','INACTIVE','ERROR','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `last_token_refresh`  DATETIME              DEFAULT NULL,
  `meta`                LONGTEXT              DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_page_tenant` (`page_id`, `tenant_id`),
  KEY `idx_tenant`  (`tenant_id`),
  KEY `idx_channel` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 5: New table tbl_meta_campaigns
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_campaigns` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `tenant_id`           INT(11)       NOT NULL DEFAULT 1,
  `campaign_id`         VARCHAR(100)  NOT NULL,
  `campaign_name`       VARCHAR(255)           DEFAULT NULL,
  `objective`           VARCHAR(100)           DEFAULT NULL,
  `status`              VARCHAR(50)   NOT NULL DEFAULT 'ACTIVE',
  `daily_budget`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `lifetime_budget`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `start_time`          DATETIME               DEFAULT NULL,
  `stop_time`           DATETIME               DEFAULT NULL,
  `spend`               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `impressions`         INT(11)       NOT NULL DEFAULT 0,
  `clicks`              INT(11)       NOT NULL DEFAULT 0,
  `reach`               INT(11)       NOT NULL DEFAULT 0,
  `ctr`                 DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `cpc`                 DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `cpm`                 DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `frequency`           DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `conversions`         INT(11)       NOT NULL DEFAULT 0,
  `leads`               INT(11)       NOT NULL DEFAULT 0,
  `insights_updated_at` DATETIME               DEFAULT NULL,
  `raw_data`            LONGTEXT               DEFAULT NULL,
  `synced_at`           DATETIME               DEFAULT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_campaign_tenant` (`campaign_id`, `tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 6: New table tbl_meta_ad_sets
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_ad_sets` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT(11)       NOT NULL DEFAULT 1,
  `ad_set_id`    VARCHAR(100)  NOT NULL,
  `campaign_id`  VARCHAR(100)           DEFAULT NULL,
  `ad_set_name`  VARCHAR(255)           DEFAULT NULL,
  `status`       VARCHAR(50)   NOT NULL DEFAULT 'ACTIVE',
  `daily_budget` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `targeting`    LONGTEXT               DEFAULT NULL,
  `spend`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `impressions`  INT(11)       NOT NULL DEFAULT 0,
  `clicks`       INT(11)       NOT NULL DEFAULT 0,
  `ctr`          DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `synced_at`    DATETIME               DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_adset_tenant` (`ad_set_id`, `tenant_id`),
  KEY `idx_tenant`   (`tenant_id`),
  KEY `idx_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 7: New table tbl_meta_ads
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_ads` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT(11)       NOT NULL DEFAULT 1,
  `ad_id`       VARCHAR(100)  NOT NULL,
  `ad_set_id`   VARCHAR(100)           DEFAULT NULL,
  `campaign_id` VARCHAR(100)           DEFAULT NULL,
  `creative_id` VARCHAR(100)           DEFAULT NULL,
  `ad_name`     VARCHAR(255)           DEFAULT NULL,
  `status`      VARCHAR(50)   NOT NULL DEFAULT 'ACTIVE',
  `spend`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `impressions` INT(11)       NOT NULL DEFAULT 0,
  `clicks`      INT(11)       NOT NULL DEFAULT 0,
  `reach`       INT(11)       NOT NULL DEFAULT 0,
  `ctr`         DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `cpc`         DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `cpm`         DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
  `conversions` INT(11)       NOT NULL DEFAULT 0,
  `leads`       INT(11)       NOT NULL DEFAULT 0,
  `raw_data`    LONGTEXT               DEFAULT NULL,
  `synced_at`   DATETIME               DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ad_tenant` (`ad_id`, `tenant_id`),
  KEY `idx_tenant`   (`tenant_id`),
  KEY `idx_adset`    (`ad_set_id`),
  KEY `idx_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 8: New table tbl_meta_lead_forms
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_lead_forms` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT(11)      NOT NULL DEFAULT 1,
  `form_id`     VARCHAR(100) NOT NULL,
  `form_name`   VARCHAR(255)          DEFAULT NULL,
  `page_id`     VARCHAR(100)          DEFAULT NULL,
  `campaign_id` VARCHAR(100)          DEFAULT NULL,
  `leads_count` INT(11)      NOT NULL DEFAULT 0,
  `status`      VARCHAR(50)  NOT NULL DEFAULT 'ACTIVE',
  `questions`   LONGTEXT              DEFAULT NULL,
  `synced_at`   DATETIME              DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_form_tenant` (`form_id`, `tenant_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 9: New table tbl_meta_leads
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_leads` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT(11)      NOT NULL DEFAULT 1,
  `lead_id`      VARCHAR(100) NOT NULL,
  `form_id`      VARCHAR(100)          DEFAULT NULL,
  `page_id`      VARCHAR(100)          DEFAULT NULL,
  `campaign_id`  VARCHAR(100)          DEFAULT NULL,
  `ad_id`        VARCHAR(100)          DEFAULT NULL,
  `customer_id`  INT(11)               DEFAULT NULL,
  `field_data`   LONGTEXT              DEFAULT NULL,
  `created_time` DATETIME              DEFAULT NULL,
  `processed`    TINYINT(1)   NOT NULL DEFAULT 0,
  `processed_at` DATETIME              DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_lead_tenant` (`lead_id`, `tenant_id`),
  KEY `idx_tenant`   (`tenant_id`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 10: New table tbl_webhook_replay_cache
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_webhook_replay_cache` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `fingerprint` VARCHAR(64)  NOT NULL,
  `event_id`    VARCHAR(255)          DEFAULT NULL,
  `tenant_id`   INT(11)      NOT NULL DEFAULT 1,
  `expires_at`  DATETIME     NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_fingerprint` (`fingerprint`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_tenant`  (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 11: New table tbl_meta_comment_rules
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_meta_comment_rules` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT(11)      NOT NULL DEFAULT 1,
  `rule_name`       VARCHAR(100) NOT NULL,
  `priority`        INT(11)      NOT NULL DEFAULT 10,
  `condition_type`  ENUM('keyword','intent','lead_score','always') NOT NULL DEFAULT 'keyword',
  `condition_value` TEXT                  DEFAULT NULL,
  `action`          ENUM('public_reply','private_reply','ignore','escalate') NOT NULL DEFAULT 'public_reply',
  `reply_template`  TEXT                  DEFAULT NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant`   (`tenant_id`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 12: New table tbl_omni_customer_journey
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_omni_customer_journey` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT(11)      NOT NULL DEFAULT 1,
  `customer_id` INT(11)      NOT NULL,
  `event_type`  VARCHAR(100) NOT NULL,
  `channel`     VARCHAR(50)           DEFAULT NULL,
  `platform`    VARCHAR(50)           DEFAULT NULL,
  `campaign_id` VARCHAR(100)          DEFAULT NULL,
  `ad_id`       VARCHAR(100)          DEFAULT NULL,
  `description` TEXT                  DEFAULT NULL,
  `metadata`    LONGTEXT              DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer`   (`customer_id`),
  KEY `idx_tenant`     (`tenant_id`),
  KEY `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- END OF MIGRATION 001_meta_integration.sql
-- ============================================================
