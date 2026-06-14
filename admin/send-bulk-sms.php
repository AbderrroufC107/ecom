<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

admin_set_flash_message('orders', 'warning', 'تم حذف نظام الرسائل من المشروع.');
header('Location: order.php');
exit;
