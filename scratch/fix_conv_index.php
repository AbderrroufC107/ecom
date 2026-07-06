<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

// The conversations table uses `current_status` not `status`
// Add compound index using the correct column names
try {
    $pdo->exec("ALTER TABLE `tbl_omni_conversations` ADD INDEX `idx_perf_tenant_status_activity` (`tenant_id`, `current_status`, `last_activity`)");
    echo "Created compound index on tbl_omni_conversations\n";
} catch (PDOException $e) {
    echo "Index error: " . $e->getMessage() . "\n";
}

// Also add index for tbl_omni_channels which uses a text status field
try {
    $pdo->exec("ALTER TABLE `tbl_omni_channels` ADD INDEX `idx_perf_tenant_status` (`tenant_id`, `status`)");
    echo "Created compound index on tbl_omni_channels\n";
} catch (PDOException $e) {
    echo "Index error: " . $e->getMessage() . "\n";
}

echo "Done!\n";
