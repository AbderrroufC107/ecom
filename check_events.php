<?php
require 'admin/inc/config.php';
$stmt = $pdo->query("SELECT * FROM tbl_omni_events ORDER BY id DESC LIMIT 5");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    print_r($row);
}
