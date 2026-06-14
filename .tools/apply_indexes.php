<?php
$pdo = new PDO('mysql:host=localhost;dbname=boomtsvp_boomtsvp_ecommerceweb;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$indexes = [
    // tbl_order
    "ALTER TABLE tbl_order ADD INDEX idx_order_status (order_status)",
    "ALTER TABLE tbl_order ADD INDEX idx_order_date (order_date)",
    "ALTER TABLE tbl_order ADD INDEX idx_customer_phone (customer_phone)",
    "ALTER TABLE tbl_order ADD INDEX idx_customer_name (customer_name)",
    "ALTER TABLE tbl_order ADD INDEX idx_product_id (product_id)",
    "ALTER TABLE tbl_order ADD INDEX idx_ecotrack_tracking (ecotrack_tracking)",
    "ALTER TABLE tbl_order ADD INDEX idx_ecotrack_status (ecotrack_status)",
    "ALTER TABLE tbl_order ADD INDEX idx_delivery_type (delivery_type)",
    "ALTER TABLE tbl_order ADD INDEX idx_customer_id (customer_id)",
    "ALTER TABLE tbl_order ADD FULLTEXT INDEX ft_order_search (customer_name, customer_phone, product_name, wilaya, commune)",
    // tbl_product
    "ALTER TABLE tbl_product ADD INDEX idx_ecat_id (ecat_id)",
    "ALTER TABLE tbl_product ADD INDEX idx_p_current_price (p_current_price)",
    "ALTER TABLE tbl_product ADD INDEX idx_p_qty (p_qty)",
    "ALTER TABLE tbl_product ADD FULLTEXT INDEX ft_product_search (p_name, p_description, p_featured_photo)",
    // tbl_customer
    "ALTER TABLE tbl_customer ADD INDEX idx_cust_name (cust_name)",
    "ALTER TABLE tbl_customer ADD INDEX idx_wilaya (wilaya)",
    "ALTER TABLE tbl_customer ADD INDEX idx_commune (commune)",
    "ALTER TABLE tbl_customer ADD FULLTEXT INDEX ft_customer_search (cust_name, cust_phone, cust_address)",
    // tbl_user
    "ALTER TABLE tbl_user ADD INDEX idx_email (email)",
    "ALTER TABLE tbl_user ADD INDEX idx_username (username)",
    "ALTER TABLE tbl_user ADD INDEX idx_role (role)",
    "ALTER TABLE tbl_user ADD INDEX idx_status (status)",
    // tbl_employee
    "ALTER TABLE tbl_employee ADD INDEX idx_full_name (full_name)",
    "ALTER TABLE tbl_employee ADD INDEX idx_is_active (is_active)",
    "ALTER TABLE tbl_employee ADD INDEX idx_telegram_chat_id (telegram_chat_id)",
    // tbl_store_user
    "ALTER TABLE tbl_store_user ADD INDEX idx_email (email)",
    "ALTER TABLE tbl_store_user ADD INDEX idx_role (role)",
    "ALTER TABLE tbl_store_user ADD INDEX idx_status (status)",
    // tbl_audit_log
    "ALTER TABLE tbl_audit_log ADD INDEX idx_created_at (created_at)",
    // tbl_order_call_log
    "ALTER TABLE tbl_order_call_log ADD INDEX idx_call_status (call_status)",
    "ALTER TABLE tbl_order_call_log ADD INDEX idx_called_by (created_by)",
    // tbl_order_status_log
    "ALTER TABLE tbl_order_status_log ADD INDEX idx_to_status (to_status)",
    "ALTER TABLE tbl_order_status_log ADD INDEX idx_from_status (from_status)",
    "ALTER TABLE tbl_order_status_log ADD INDEX idx_changed_by (changed_by)",
    // tbl_order_trash
    "ALTER TABLE tbl_order_trash ADD INDEX idx_original_id (original_id)",
    "ALTER TABLE tbl_order_trash ADD INDEX idx_deleted_at (deleted_at)",
    "ALTER TABLE tbl_order_trash ADD INDEX idx_customer_phone (customer_phone)",
    // Engine conversions
    "ALTER TABLE tbl_country ENGINE=InnoDB",
    "ALTER TABLE tbl_language ENGINE=InnoDB",
    "ALTER TABLE tbl_customer_message ENGINE=InnoDB",
];

$passed = 0; $failed = 0; $skipped = 0;
foreach ($indexes as $stmt) {
    try {
        $pdo->exec($stmt);
        echo 'PASS: ' . $stmt . PHP_EOL;
        $passed++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate key name') || str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
            $skipped++;
        } else {
            echo 'FAIL: ' . $stmt . ' -> ' . $msg . PHP_EOL;
            $failed++;
        }
    }
}
echo "---\nIndexes: {$passed} added, {$skipped} existed, {$failed} failed\n";
