<?php
require 'admin/inc/config.php';
$cols = $pdo->query("DESCRIBE tbl_omni_channels")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . $c['Default'] . PHP_EOL;
}
?>
