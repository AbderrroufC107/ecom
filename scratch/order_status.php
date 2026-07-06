<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SELECT DISTINCT order_status FROM tbl_order');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    echo $row['order_status'] . "\n";
}
