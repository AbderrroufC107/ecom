<?php
/**
 * Performance Audit
 * Measures response times of key admin endpoints and checks DB query performance
 */

require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

echo "=== PERFORMANCE AUDIT ===\n\n";

// 1. Check for DB query performance on critical tables
$queries = [
    'Orders count'   => "EXPLAIN SELECT * FROM tbl_order WHERE tenant_id = 1 ORDER BY id DESC LIMIT 50",
    'Products list'  => "EXPLAIN SELECT * FROM tbl_product WHERE tenant_id = 1 AND p_is_active = 1 ORDER BY p_id DESC LIMIT 20",
    'Settings'       => "EXPLAIN SELECT * FROM tbl_settings WHERE tenant_id = 1 LIMIT 1",
    'Omni channels'  => "EXPLAIN SELECT * FROM tbl_omni_channels WHERE tenant_id = 1 AND status = 'ACTIVE'",
    'Conversations'  => "EXPLAIN SELECT * FROM tbl_omni_conversations WHERE tenant_id = 1 ORDER BY updated_at DESC LIMIT 20",
];

echo "--- Query Plan Analysis ---\n";
foreach ($queries as $label => $sql) {
    try {
        $start = microtime(true);
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $ms = round((microtime(true) - $start) * 1000, 2);
        echo "\n[$label] ({$ms}ms)\n";
        foreach ($rows as $row) {
            $keyUsed  = $row['key'] ?? 'NONE';
            $rowsExam = $row['rows'] ?? 'N/A';
            $type     = $row['type'] ?? 'N/A';
            echo "  Type: $type | Key: $keyUsed | Rows examined: $rowsExam\n";
            if ($keyUsed === null || $keyUsed === 'NONE') {
                echo "  ⚠ WARNING: Full table scan! No index used.\n";
            }
        }
    } catch (PDOException $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}

// 2. Check table sizes
echo "\n--- Table Sizes (rows & data size) ---\n";
$tables = ['tbl_order', 'tbl_product', 'tbl_customer', 'tbl_omni_conversations', 'tbl_omni_timeline'];
foreach ($tables as $table) {
    try {
        $row = $pdo->query("SELECT COUNT(*) AS cnt FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        $info = $pdo->query("SELECT DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'")->fetch(PDO::FETCH_ASSOC);
        $dataMB = round(($info['DATA_LENGTH'] ?? 0) / 1024 / 1024, 2);
        $idxMB  = round(($info['INDEX_LENGTH'] ?? 0) / 1024 / 1024, 2);
        echo "  $table: " . $row['cnt'] . " rows | Data: {$dataMB}MB | Index: {$idxMB}MB\n";
    } catch (PDOException $e) {
        echo "  $table: ERROR - " . $e->getMessage() . "\n";
    }
}

// 3. Check for missing composite indexes
echo "\n--- Composite Index Recommendations ---\n";
$recommendations = [
    ['tbl_order',            ['tenant_id', 'order_status', 'order_date'], 'Filtering orders by status+date per tenant'],
    ['tbl_product',          ['tenant_id', 'p_is_active', 'p_id'],        'Active product listing per tenant'],
    ['tbl_omni_conversations',['tenant_id', 'status', 'updated_at'],       'Active conversation listing'],
    ['tbl_ai_tasks',         ['status', 'priority', 'created_at'],         'Task queue ordering'],
];

foreach ($recommendations as [$table, $cols, $reason]) {
    $idx_name = 'idx_perf_' . implode('_', $cols);
    $col_str = '`' . implode('`, `', $cols) . '`';
    try {
        // Check if index exists
        $existing = $pdo->query("SHOW INDEXES FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $existing_names = array_column($existing, 'Key_name');
        if (!in_array($idx_name, $existing_names)) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$idx_name` ($col_str)");
            echo "  ✓ Created $idx_name on $table — $reason\n";
        } else {
            echo "  ✓ Already exists: $idx_name on $table\n";
        }
    } catch (PDOException $e) {
        echo "  ✗ $idx_name on $table: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Performance Audit Complete ===\n";
