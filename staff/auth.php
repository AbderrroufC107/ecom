<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['staff_employee_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/employee_functions.php';
require_once __DIR__ . '/../admin/inc/telegram_bot.php';
require_once __DIR__ . '/../admin/inc/performance_functions.php';

$employee = employee_get_by_id($pdo, (int) $_SESSION['staff_employee_id']);
if (!$employee || empty($employee['is_active'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

performance_ensure_tables($pdo);
