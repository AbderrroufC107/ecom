<?php
require_once(__DIR__ . '/inc/config.php');

try {
    // 1. Add dashboard_prefs to tbl_user
    $dbRepo->executeCommand("ALTER TABLE tbl_user ADD COLUMN dashboard_prefs TEXT NULL DEFAULT NULL");
    echo "Added dashboard_prefs to tbl_user.\n";
} catch (Exception $e) {
    echo "dashboard_prefs might already exist: " . $e->getMessage() . "\n";
}

try {
    // 2. Create tbl_order_timeline
    $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_order_timeline (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        old_status VARCHAR(50) NULL,
        new_status VARCHAR(50) NOT NULL,
        changed_by INT NULL,
        changed_at DATETIME NOT NULL,
        INDEX(order_id),
        INDEX(changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created tbl_order_timeline.\n";
} catch (Exception $e) {
    echo "Error creating tbl_order_timeline: " . $e->getMessage() . "\n";
}

echo "DB modifications completed.";
