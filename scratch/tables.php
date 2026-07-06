<?php
require 'admin/inc/config.php';
$stmt = $pdo->query('SHOW TABLES');
while($row = $stmt->fetch(PDO::FETCH_NUM)){
    echo $row[0] . "\n";
}
