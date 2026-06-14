<?php
require('inc/config.php');
$s = $pdo->query("SELECT ecotrack_remote_status, count(*) as c FROM tbl_order WHERE ecotrack_tracking IS NOT NULL AND ecotrack_tracking != '' GROUP BY ecotrack_remote_status");
foreach($s as $r) echo ($r['ecotrack_remote_status'] === null ? 'NULL' : $r['ecotrack_remote_status']).' => '.$r['c']."\n";
