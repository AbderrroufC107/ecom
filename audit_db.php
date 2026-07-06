<?php
require_once('C:/xampp/htdocs/ecom/admin/inc/config.php');

$dbName = 'ecom';
echo "Starting Database Audit for DB: $dbName\n\n";

// 1. Get all tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Total Tables: " . count($tables) . "\n\n";

$auditResults = [
    'missing_tenant_id' => [],
    'missing_primary_key' => [],
    'missing_indexes' => [], // Foreign key like columns (ending in _id) without index
    'no_fk_constraints' => []
];

foreach ($tables as $table) {
    // Check Columns
    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($columns, 'Field');
    
    // Check PK
    $hasPK = false;
    foreach ($columns as $col) {
        if ($col['Key'] === 'PRI') $hasPK = true;
    }
    if (!$hasPK) $auditResults['missing_primary_key'][] = $table;
    
    // Check tenant_id
    // We ignore some base tables if they are global, e.g. tbl_users if it's super admin, but in SaaS typically all data has tenant_id
    if (!in_array('tenant_id', $colNames)) {
        $auditResults['missing_tenant_id'][] = $table;
    }

    // Check Indexes
    $indexes = $pdo->query("SHOW INDEXES FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $indexCols = array_column($indexes, 'Column_name');
    
    foreach ($colNames as $colName) {
        if (substr($colName, -3) === '_id' && $colName !== 'id') {
            if (!in_array($colName, $indexCols)) {
                $auditResults['missing_indexes'][] = "$table.$colName";
            }
        }
    }

    // Check Foreign Keys
    $fks = $pdo->query("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (count($fks) === 0 && count($colNames) > 2) { 
        $hasIdCol = false;
        foreach($colNames as $c) if (substr($c, -3) === '_id' && $c !== 'id') $hasIdCol = true;
        if ($hasIdCol) {
            $auditResults['no_fk_constraints'][] = $table;
        }
    }
}

echo "=== Missing Primary Keys ===\n";
print_r($auditResults['missing_primary_key']);

echo "\n=== Missing tenant_id (SaaS Isolation Risk) ===\n";
print_r($auditResults['missing_tenant_id']);

echo "\n=== Missing Indexes on _id Columns ===\n";
print_r($auditResults['missing_indexes']);

echo "\n=== Tables with _id columns but NO Foreign Key Constraints ===\n";
print_r($auditResults['no_fk_constraints']);

echo "\nAudit Complete.\n";
