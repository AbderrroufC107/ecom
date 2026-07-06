<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

$large_tables = [
    'tbl_order' => ['tenant_id', 'order_status'],
    'tbl_customer' => ['tenant_id', 'cust_phone'],
    'tbl_omni_conversations' => ['tenant_id', 'status'],
    'tbl_omni_events' => ['tenant_id', 'created_at'],
    'tbl_api_log' => ['tenant_id', 'created_at']
];

foreach ($large_tables as $table => $cols) {
    $idx_name = 'idx_' . implode('_', $cols);
    try {
        $cols_str = implode('`, `', $cols);
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$idx_name` (`$cols_str`)");
        echo "Added compound index $idx_name to $table\n";
    } catch (PDOException $e) {
        echo "Index may already exist or error on $table: " . $e->getMessage() . "\n";
    }
}
