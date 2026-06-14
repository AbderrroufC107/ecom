<?php
session_start();
unset($_SESSION['user']);
unset($_SESSION['store_user']);
unset($_SESSION['store_id']);
session_destroy();
header("location: login.php");
exit;
?>