<?php
require __DIR__ . '/../admin/inc/config.php';

$stmt = $dbRepo->query('SELECT 1 AS test_value');
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (($row['test_value'] ?? null) !== 1) {
    fwrite(STDERR, "Repository query() did not execute the SQL statement as expected.\n");
    exit(1);
}

echo "Repository query regression test passed.\n";
