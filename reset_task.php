<?php
require 'admin/inc/config.php';
$pdo->query("UPDATE tbl_ai_tasks SET status = 'PENDING', retries = 0 WHERE id = 1");
echo 'Task 1 reset with 0 retries';
