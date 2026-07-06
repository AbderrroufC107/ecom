<?php
require_once(__DIR__ . '/inc/config.php');

try {
    // Drop existing trigger if exists
    $dbRepo->executeCommand("DROP TRIGGER IF EXISTS after_order_status_update");
    
    // Create new trigger
    $dbRepo->executeCommand("
        CREATE TRIGGER after_order_status_update
        AFTER UPDATE ON tbl_order
        FOR EACH ROW
        BEGIN
            IF NEW.order_status <> OLD.order_status THEN
                INSERT INTO tbl_order_timeline (order_id, action, description, user_id, created_at)
                VALUES (NEW.id, 'تحديث الحالة', CONCAT('من ', OLD.order_status, ' إلى ', NEW.order_status), NULL, NOW());
            END IF;
        END
    ");
    echo "Trigger created successfully.\n";
} catch (Exception $e) {
    echo "Error creating trigger: " . $e->getMessage() . "\n";
}
