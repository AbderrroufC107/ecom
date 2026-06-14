<?php
$employee_bootstrap_loaded = function () use ($pdo) {
    if (!isset($pdo)) {
        return;
    }
    require_once __DIR__ . '/employee_functions.php';
    employee_ensure_tables($pdo);
    employee_auto_assign_unassigned($pdo, 100);
};
$employee_bootstrap_loaded();
