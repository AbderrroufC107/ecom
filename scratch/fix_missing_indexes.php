<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

echo "Starting Missing Indexes Fix...\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$indexed_count = 0;

foreach ($tables as $table) {
    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($columns, 'Field');
    
    $indexes = $pdo->query("SHOW INDEXES FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $indexCols = array_column($indexes, 'Column_name');
    
    foreach ($colNames as $colName) {
        if (substr($colName, -3) === '_id' && $colName !== 'id') {
            if (!in_array($colName, $indexCols)) {
                try {
                    $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_{$colName}` (`$colName`)");
                    $indexed_count++;
                    echo "Indexed $colName on $table\n";
                } catch (PDOException $e) {
                    echo "Failed to index $colName on $table: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

echo "\nIndex Fix Complete. Added $indexed_count missing indexes.\n";
