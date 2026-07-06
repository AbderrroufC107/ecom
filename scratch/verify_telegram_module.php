<?php
/**
 * Telegram Module Automated Audit & Verification Script
 */

declare(strict_types=1);

require_once __DIR__ . '/../admin/inc/config.php';

echo "=============================================\n";
echo "TELEGRAM MODULE AUTOMATED AUDIT & VERIFICATION\n";
echo "=============================================\n\n";

$errors = [];
$newFiles = [
    'admin/telegram/bootstrap.php',
    'admin/telegram/Config/installer.php',
    'admin/telegram/Config/version.php',
    'admin/telegram/Providers/NotificationProviderInterface.php',
    'admin/telegram/Providers/TelegramNotificationProvider.php',
    'admin/telegram/Services/EventManager.php',
    'admin/telegram/Services/ProviderManager.php',
    'admin/telegram/Services/TelegramService.php',
    'admin/telegram/Services/TokenService.php',
    'admin/telegram/Services/LoggerService.php',
    'admin/telegram/Services/QueueService.php',
    'admin/telegram/Services/StateService.php',
    'admin/telegram/Services/HealthService.php',
    'admin/telegram/Services/AuditService.php',
    'admin/telegram/Services/NotificationService.php',
    'admin/telegram/Handlers/WebhookHandler.php',
    'admin/telegram/Handlers/CallbackHandler.php',
    'admin/telegram/Templates/ar.php',
    'admin/telegram/Templates/en.php',
    'admin/telegram/Templates/fr.php',
    'admin/telegram/Workers/telegram-queue-worker.php',
    'admin/telegram/Workers/telegram-scheduler.php',
    'admin/telegram-webhook.php',
    'admin/telegram-link-action.php',
    'admin/telegram-settings.php',
    'admin/telegram-dashboard.php'
];

$modifiedFiles = [
    'admin/inc/config.php',
    'admin/inc/functions.php',
    'admin/inc/employee_functions.php',
    'staff/complaints.php',
    'staff/profile.php',
    'admin/profile-edit.php',
    'buy-now.php',
    'landing_page.php',
    'landing_page_2.php',
    'admin/order-add-from-incomplete.php',
    'admin/header.php',
    'admin/inc/lang.php',
    'admin/react-src/src/lib/pageMeta.js'
];

// 1. Check file existences & syntax
echo "1. Checking New Files Existences & Syntax:\n";
foreach ($newFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (!file_exists($fullPath)) {
        $errors[] = "Missing new file: {$file}";
        echo "[FAIL] {$file} (Does not exist)\n";
    } else {
        // Run PHP syntax check if it's a php file
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $output = [];
            $retval = 0;
            exec("C:\\xampp\\php\\php.exe -l " . escapeshellarg($fullPath) . " 2>&1", $output, $retval);
            if ($retval !== 0) {
                $errors[] = "Lint failure in {$file}: " . implode("\n", $output);
                echo "[LINT FAIL] {$file}\n";
            } else {
                echo "[OK] {$file}\n";
            }
        } else {
            echo "[OK] {$file}\n";
        }
    }
}
echo "\n";

// 2. Check modified files
echo "2. Checking Modified Files Existences & Syntax:\n";
foreach ($modifiedFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (!file_exists($fullPath)) {
        $errors[] = "Missing modified file: {$file}";
        echo "[FAIL] {$file} (Does not exist)\n";
    } else {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $output = [];
            $retval = 0;
            exec("C:\\xampp\\php\\php.exe -l " . escapeshellarg($fullPath) . " 2>&1", $output, $retval);
            if ($retval !== 0) {
                $errors[] = "Lint failure in {$file}: " . implode("\n", $output);
                echo "[LINT FAIL] {$file}\n";
            } else {
                echo "[OK] {$file}\n";
            }
        } else {
            echo "[OK] {$file}\n";
        }
    }
}
echo "\n";

// 3. Verify classes exist
echo "3. Verifying Class Definitions:\n";
require_once __DIR__ . '/../admin/telegram/Handlers/WebhookHandler.php';
require_once __DIR__ . '/../admin/telegram/Handlers/CallbackHandler.php';
$classesToCheck = [
    'EventManager',
    'ProviderManager',
    'TelegramService',
    'TokenService',
    'LoggerService',
    'QueueService',
    'StateService',
    'HealthService',
    'AuditService',
    'NotificationService',
    'WebhookHandler',
    'CallbackHandler',
    'TelegramNotificationProvider'
];

foreach ($classesToCheck as $className) {
    if (!class_exists($className)) {
        $errors[] = "Class '{$className}' is not defined or failed to load.";
        echo "[FAIL] Class {$className} (Missing)\n";
    } else {
        echo "[OK] Class {$className} is successfully loaded.\n";
    }
}
echo "\n";

// 4. Verify Database Schema
echo "4. Verifying Database Schema:\n";
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

$expectedTables = [
    'tbl_telegram_logs',
    'tbl_telegram_queue',
    'tbl_telegram_conversation_state',
    'tbl_telegram_audit'
];

foreach ($expectedTables as $table) {
    if (!tableExists($pdo, $table)) {
        $errors[] = "Missing expected table: {$table}";
        echo "[FAIL] Table {$table} (Missing)\n";
    } else {
        echo "[OK] Table {$table} exists.\n";
    }
}

$settingsCols = ['telegram_webhook_url', 'telegram_is_enabled', 'telegram_secret_token'];
foreach ($settingsCols as $col) {
    if (!columnExists($pdo, 'tbl_settings', $col)) {
        $errors[] = "Missing column '{$col}' in 'tbl_settings'";
        echo "[FAIL] Column tbl_settings.{$col} (Missing)\n";
    } else {
        echo "[OK] Column tbl_settings.{$col} exists.\n";
    }
}

$employeeCols = ['telegram_username', 'telegram_is_linked', 'telegram_lang', 'telegram_chat_id'];
foreach ($employeeCols as $col) {
    if (!columnExists($pdo, 'tbl_employee', $col)) {
        $errors[] = "Missing column '{$col}' in 'tbl_employee'";
        echo "[FAIL] Column tbl_employee.{$col} (Missing)\n";
    } else {
        echo "[OK] Column tbl_employee.{$col} exists.\n";
    }
}

$userCols = ['telegram_username', 'telegram_is_linked', 'telegram_lang', 'telegram_chat_id', 'telegram_notifications'];
foreach ($userCols as $col) {
    if (!columnExists($pdo, 'tbl_user', $col)) {
        $errors[] = "Missing column '{$col}' in 'tbl_user'";
        echo "[FAIL] Column tbl_user.{$col} (Missing)\n";
    } else {
        echo "[OK] Column tbl_user.{$col} exists.\n";
    }
}
echo "\n";

// 5. Verify Event Subscriptions
echo "5. Verifying Active Event Subscriptions:\n";
if (class_exists('NotificationService')) {
    NotificationService::bootstrap();
}
if (class_exists('EventManager')) {
    $events = ['OrderCreated', 'OrderAssigned', 'OrderUpdated', 'ComplaintCreated', 'EmployeeCreated'];
    foreach ($events as $event) {
        $listeners = EventManager::getListeners($event);
        if (empty($listeners)) {
            $errors[] = "No active event listeners registered for: {$event}";
            echo "[FAIL] Event {$event} has 0 listeners.\n";
        } else {
            echo "[OK] Event {$event} has " . count($listeners) . " active listener(s).\n";
        }
    }
} else {
    echo "[FAIL] EventManager is not loaded, cannot verify subscriptions.\n";
}
echo "\n";

// 6. Summary
echo "=============================================\n";
if (empty($errors)) {
    echo "AUDIT COMPLETED: 100% SUCCESS. NO ERRORS FOUND.\n";
} else {
    echo "AUDIT COMPLETED WITH ERRORS:\n";
    foreach ($errors as $err) {
        echo "- {$err}\n";
    }
}
echo "=============================================\n";
