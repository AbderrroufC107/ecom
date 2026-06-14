-- =====================================================
-- Performance Index Migration
-- Date: 2026-06-03
-- 
-- Adds missing indexes identified by index audit.
-- Run: mysql -u root -D boomtsvp_boomtsvp_ecommerceweb < this_file
-- =====================================================

-- 1. tbl_order — Most critical table (185 rows, frequent queries)
ALTER TABLE tbl_order
  ADD INDEX idx_order_status (order_status),
  ADD INDEX idx_order_date (order_date),
  ADD INDEX idx_customer_phone (customer_phone),
  ADD INDEX idx_customer_name (customer_name),
  ADD INDEX idx_product_id (product_id),
  ADD INDEX idx_ecotrack_tracking (ecotrack_tracking),
  ADD INDEX idx_ecotrack_status (ecotrack_status),
  ADD INDEX idx_delivery_type (delivery_type),
  ADD INDEX idx_customer_id (customer_id),
  ADD FULLTEXT INDEX ft_order_search (customer_name, customer_phone, product_name, wilaya, commune);

-- 2. tbl_product — Product catalog searches
ALTER TABLE tbl_product
  ADD INDEX idx_ecat_id (ecat_id),
  ADD INDEX idx_p_current_price (p_current_price),
  ADD INDEX idx_p_qty (p_qty),
  ADD FULLTEXT INDEX ft_product_search (p_name, p_description, p_featured_photo);

-- 3. tbl_customer — Customer lookup
ALTER TABLE tbl_customer
  ADD INDEX idx_cust_name (cust_name),
  ADD INDEX idx_wilaya (wilaya),
  ADD INDEX idx_commune (commune),
  ADD FULLTEXT INDEX ft_customer_search (cust_name, cust_phone, cust_address);

-- 4. tbl_user — Admin login lookups
ALTER TABLE tbl_user
  ADD INDEX idx_email (email),
  ADD INDEX idx_username (username),
  ADD INDEX idx_role (role),
  ADD INDEX idx_status (status);

-- 5. tbl_employee — Staff portal
ALTER TABLE tbl_employee
  ADD INDEX idx_full_name (full_name),
  ADD INDEX idx_is_active (is_active),
  ADD INDEX idx_telegram_chat_id (telegram_chat_id);

-- 6. tbl_store_user — Store owner login
ALTER TABLE tbl_store_user
  ADD INDEX idx_email (email),
  ADD INDEX idx_role (role),
  ADD INDEX idx_status (status);

-- 7. tbl_audit_log — Audit log queries (currently has composite indexes, add single-column for flexibility)
ALTER TABLE tbl_audit_log
  ADD INDEX idx_created_at (created_at),
  ADD INDEX idx_performed_by (performed_by_type, performed_by_id);

-- 8. tbl_order_call_log — Order call log
ALTER TABLE tbl_order_call_log
  ADD INDEX idx_call_status (call_status),
  ADD INDEX idx_called_by (created_by);

-- 9. tbl_order_status_log — Status changes
ALTER TABLE tbl_order_status_log
  ADD INDEX idx_to_status (to_status),
  ADD INDEX idx_from_status (from_status),
  ADD INDEX idx_changed_by (changed_by);

-- 10. tbl_order_contact_attempt — Recovery contact attempts
-- (already has indexes on order_id, tracking, sub_status, attempt_date)

-- 11. tbl_recovery_tasks — Recovery tasks
-- (already has indexes on order_id, status, type, assigned, scheduled)

-- 12. tbl_recovery_queue — Recovery queue
-- (already has indexes on order_id, phone, action_taken)

-- 13. tbl_customer_risk_timeline — Risk tracking
-- (already has indexes on phone, event, date, order_id)

-- 14. tbl_order_assignment — Order assignments
ALTER TABLE tbl_order_assignment
  ADD INDEX idx_assigned_at (assigned_at);

-- 15. tbl_order_trash — Trashed orders (same pattern as tbl_order)
ALTER TABLE tbl_order_trash
  ADD INDEX idx_original_id (original_id),
  ADD INDEX idx_deleted_at (deleted_at),
  ADD INDEX idx_customer_phone (customer_phone);

-- 16. tbl_incomplete_orders — Incomplete order searches
ALTER TABLE incomplete_orders
  ADD INDEX idx_customer_phone (customer_phone),
  ADD INDEX idx_customer_name (customer_name),
  ADD FULLTEXT INDEX ft_incomplete_search (customer_name, customer_phone);

-- 17. tbl_language — Admin language lookups
ALTER TABLE tbl_language
  ADD INDEX idx_lang_name (lang_name);

-- 18. tbl_end_category / tbl_mid_category / tbl_top_category — Category lookups
ALTER TABLE tbl_end_category
  ADD INDEX idx_mcat_id (mcat_id);
ALTER TABLE tbl_mid_category
  ADD INDEX idx_tcat_id (tcat_id);

-- =====================================================
-- Convert MyISAM tables to InnoDB
-- =====================================================
ALTER TABLE tbl_country ENGINE=InnoDB;
ALTER TABLE tbl_language ENGINE=InnoDB;
ALTER TABLE tbl_customer_message ENGINE=InnoDB;
