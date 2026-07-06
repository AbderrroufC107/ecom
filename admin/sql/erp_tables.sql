-- Supplier System
CREATE TABLE IF NOT EXISTS `tbl_supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchase Orders
CREATE TABLE IF NOT EXISTS `tbl_purchase_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending', -- Pending, Received, Cancelled
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `tbl_supplier`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchase Order Items
CREATE TABLE IF NOT EXISTS `tbl_purchase_order_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`po_id`) REFERENCES `tbl_purchase_order`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Multi-Warehouse Preparation
CREATE TABLE IF NOT EXISTS `tbl_warehouse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `location` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbl_warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`warehouse_id`) REFERENCES `tbl_warehouse`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permanent Stock Audit Log
CREATE TABLE IF NOT EXISTS `tbl_stock_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `old_qty` int(11) NOT NULL,
  `new_qty` int(11) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `reason` varchar(100) NOT NULL, -- Supplier Delivery, Order Delivered, Order Returned, Manual Edit, Damaged, Expired
  `note` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification Center
CREATE TABLE IF NOT EXISTS `tbl_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0, -- 0 for all admins
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL, -- info, warning, danger, success
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API Sync Log
CREATE TABLE IF NOT EXISTS `tbl_api_sync_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_company_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `old_status` varchar(100) DEFAULT NULL,
  `new_status` varchar(100) DEFAULT NULL,
  `result` varchar(50) NOT NULL, -- Success, Failed, Skipped
  `error_message` text DEFAULT NULL,
  `sync_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Commission Adjustments
CREATE TABLE IF NOT EXISTS `tbl_commission_adjustment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL, -- Negative for deduction
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modify Delivery Company Table for API Integration
ALTER TABLE `tbl_delivery_company` 
ADD COLUMN IF NOT EXISTS `api_enabled` tinyint(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `api_type` varchar(50) DEFAULT NULL, -- yalidine, zrexpress, noest, ems
ADD COLUMN IF NOT EXISTS `api_key` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `api_token` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `api_username` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `api_password` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `api_base_url` varchar(255) DEFAULT NULL;

-- Initial default warehouse if empty
INSERT INTO `tbl_warehouse` (`name`, `location`, `is_default`, `active`) 
SELECT 'Main Warehouse', 'Headquarters', 1, 1 
WHERE NOT EXISTS (SELECT 1 FROM `tbl_warehouse` WHERE `is_default` = 1);

-- Phase 8: Order Timeline
CREATE TABLE IF NOT EXISTS `tbl_order_timeline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT 0, -- 0 for system/API, >0 for Admin
  `delivery_company_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 8: API Request Logs
CREATE TABLE IF NOT EXISTS `tbl_api_request_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `delivery_company_id` int(11) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `headers` text DEFAULT NULL,
  `request_body` longtext DEFAULT NULL,
  `response_body` longtext DEFAULT NULL,
  `http_code` int(5) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 8: Order API Status Tracking
ALTER TABLE `tbl_order`
ADD COLUMN IF NOT EXISTS `sync_attempts` int(5) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `next_sync_time` datetime DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `sync_status` varchar(50) DEFAULT 'Pending'; -- Pending, Synced, Failed
