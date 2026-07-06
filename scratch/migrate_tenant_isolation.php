<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

echo "Starting Database Migration for SaaS Isolation...\n";

// 1. Create tbl_tenants if not exists
$pdo->exec("
CREATE TABLE IF NOT EXISTS tbl_tenants (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Insert default tenant if table is empty
$stmt = $pdo->query("SELECT COUNT(*) FROM tbl_tenants");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO tbl_tenants (id, name, domain) VALUES (1, 'Default Store', 'localhost')");
}

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$exclude_tables = [
    'tbl_wilaya', 'tbl_commune', 'tbl_language', 'tbl_tenants', 
    'tbl_store', 'tbl_stores', 'tbl_country', 'tbl_delivery_company' // often global
];

$target_tables = array_diff($tables, $exclude_tables);

$added_count = 0;
$indexed_count = 0;
$fk_count = 0;

foreach ($target_tables as $table) {
    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($columns, 'Field');
    
    // Add column
    if (!in_array('tenant_id', $colNames)) {
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `tenant_id` INT(11) NOT NULL DEFAULT 1 AFTER `id`");
            $added_count++;
            echo "Added tenant_id to $table\n";
        } catch (PDOException $e) {
            // Table might not have an `id` column, add at the end
            try {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `tenant_id` INT(11) NOT NULL DEFAULT 1");
                $added_count++;
                echo "Added tenant_id to $table (at end)\n";
            } catch (PDOException $e2) {
                echo "Failed to add to $table: " . $e2->getMessage() . "\n";
            }
        }
    }
    
    // Check indexes
    $indexes = $pdo->query("SHOW INDEXES FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $indexCols = array_column($indexes, 'Column_name');
    
    if (!in_array('tenant_id', $indexCols)) {
        try {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_tenant_id` (`tenant_id`)");
            $indexed_count++;
            echo "Indexed tenant_id on $table\n";
        } catch (PDOException $e) {
            echo "Failed to index $table: " . $e->getMessage() . "\n";
        }
    }

    // Check FKs
    $fks = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = 'tenant_id' AND REFERENCED_TABLE_NAME = 'tbl_tenants'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (count($fks) == 0) {
        try {
            // Make sure engine is InnoDB
            $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'")->fetchColumn();
            if ($engine == 'InnoDB') {
                $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `fk_{$table}_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tbl_tenants`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
                $fk_count++;
                echo "Added FK to $table\n";
            }
        } catch (PDOException $e) {
            echo "Failed FK on $table: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration Complete!\n";
echo "Added tenant_id to: $added_count tables\n";
echo "Indexed tenant_id on: $indexed_count tables\n";
echo "Added Foreign Keys to: $fk_count tables\n";

