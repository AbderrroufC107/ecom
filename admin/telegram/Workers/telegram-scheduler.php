<?php
/**
 * Telegram Scheduler Worker
 *
 * Runs once a minute to process scheduled tasks, reminders, and daily summaries.
 * Cron pattern: * * * * * php /path/to/admin/telegram/Workers/telegram-scheduler.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../Services/TelegramService.php';
require_once __DIR__ . '/../Services/LoggerService.php';
require_once __DIR__ . '/../Services/QueueService.php';
require_once __DIR__ . '/../Providers/TelegramNotificationProvider.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed.");
}

// 1. Fetch Global Settings
try {
    $stmt = $dbRepo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings || (int) ($settings['telegram_is_enabled'] ?? 0) !== 1) {
        exit("Telegram bot is disabled.\n");
    }
} catch (Exception $e) {
    exit("Failed to load settings: " . $e->getMessage() . "\n");
}

$provider = new TelegramNotificationProvider($pdo);

// Helper to fetch linked managers
function getManagers(PDO $pdo): array
{ global $dbRepo;
    $stmt = $dbRepo->query("SELECT * FROM tbl_user WHERE telegram_is_linked = 1 AND status = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$managers = getManagers($pdo);

// 2. Dispatch Daily Summary Report
if ((int) ($settings['telegram_enable_daily_reports'] ?? 0) === 1 && !empty($managers)) {
    $targetTime = $settings['telegram_daily_report_time'] ?? '08:00:00';
    $currentTime = date('H:i');
    $targetHourMin = date('H:i', strtotime($targetTime));

    if ($currentTime === $targetHourMin) {
        // Double-check if we already ran it today
        $check = $dbRepo->query("
            SELECT id FROM `tbl_telegram_logs` 
            WHERE `action_name` = 'daily_summary_report' 
              AND `created_at` >= DATE(NOW()) 
            LIMIT 1
        ")->fetch();

        if (!$check) {
            try {
                // Compile store summary
                $stmt = $dbRepo->query("
                    SELECT 
                        COUNT(*) AS total,
                        SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                        SUM(CASE WHEN order_status = 'Completed' THEN total_price ELSE 0 END) AS revenue
                    FROM tbl_order
                    WHERE order_date >= DATE(NOW())
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                $data = [
                    'date' => date('Y-m-d'),
                    'total_today' => $stats['total'] ?? 0,
                    'confirmed_today' => $stats['completed'] ?? 0,
                    'cancelled_today' => $stats['cancelled'] ?? 0,
                    'revenue_today' => number_format((float) ($stats['revenue'] ?? 0), 0, '.', ' ')
                ];

                $provider->broadcastNotification($managers, 'daily_summary', $data);
                
                LoggerService::logAction(
                    $pdo,
                    'broadcast',
                    'system',
                    0,
                    'outgoing',
                    'daily_summary_report',
                    $data,
                    'success'
                );
                echo "Daily summary report queued for managers.\n";

            } catch (Exception $e) {
                error_log("Failed to process daily summary: " . $e->getMessage());
            }
        }
    }
}

// 3. Dispatch Pending Task Reminders to Employees
$reminderHours = (int) ($settings['telegram_reminder_hours'] ?? 24);
if ($reminderHours > 0) {
    try {
        // Fetch active pending tasks older than threshold hours
        $stmt = $dbRepo->prepare("
            SELECT o.*, e.id AS emp_id, e.telegram_chat_id, e.telegram_lang, e.telegram_is_linked
            FROM tbl_order o
            INNER JOIN tbl_order_assignment oa ON oa.order_id = o.id AND oa.status = 'active'
            INNER JOIN tbl_employee e ON e.id = oa.employee_id
            WHERE o.order_status IN ('Pending', 'Confirmed')
              AND o.order_date <= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$reminderHours]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($tasks as $task) {
            $orderId = (int) $task['id'];
            $chatId = trim((string) $task['telegram_chat_id']);
            
            if ($chatId === '' || empty($task['telegram_is_linked'])) {
                continue;
            }

            // Check if reminder was sent in the last 24 hours
            $check = $dbRepo->prepare("
                SELECT id FROM `tbl_telegram_logs` 
                WHERE `action_name` = 'task_deadline_reminder' 
                  AND `chat_id` = ? 
                  AND `payload` LIKE ? 
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                LIMIT 1
            ");
            $check->execute([$chatId, '%"order_id":' . $orderId . '%']);
            
            if (!$check->fetch()) {
                $employee = [
                    'telegram_chat_id' => $chatId,
                    'telegram_is_linked' => $task['telegram_is_linked'],
                    'telegram_lang' => $task['telegram_lang']
                ];
                
                $ok = $provider->sendTaskNotification($employee, $task, 'deadline_reminder');
                if ($ok) {
                    LoggerService::logAction(
                        $pdo,
                        $chatId,
                        'employee',
                        (int) $task['emp_id'],
                        'outgoing',
                        'task_deadline_reminder',
                        ['order_id' => $orderId],
                        'success'
                    );
                    echo "Deadline reminder queued for employee {$task['emp_id']} on order #{$orderId}.\n";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Failed to process task reminders: " . $e->getMessage());
    }
}

// 4. Dispatch Unanswered Complaint Reminders to Managers
if (!empty($managers)) {
    try {
        // Check if there are unanswered complaints older than 4 hours
        $stmt = $dbRepo->query("
            SELECT COUNT(*) FROM `tbl_complaints` 
            WHERE `telegram_status` = 'sent' 
              AND `created_at` <= DATE_SUB(NOW(), INTERVAL 4 HOUR)
        ");
        $pendingComplaintsCount = (int) $stmt->fetchColumn();

        if ($pendingComplaintsCount > 0) {
            // Rate limit check: limit reminder to once per 12 hours
            $check = $dbRepo->query("
                SELECT id FROM `tbl_telegram_logs` 
                WHERE `action_name` = 'unanswered_complaints_reminder' 
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL 12 HOUR) 
                LIMIT 1
            ")->fetch();

            if (!$check) {
                $msg = "⚠️ <b>تنبيه للمدراء:</b>\n\nيوجد <b>{$pendingComplaintsCount}</b> شكاوى وملاحظات معلقة من الموظفين بانتظار مراجعتكم.";
                foreach ($managers as $m) {
                    $mChat = trim((string) ($m['telegram_chat_id'] ?? ''));
                    if ($mChat !== '' && !empty($m['telegram_is_linked'])) {
                        // Queue message
                        $payload = [
                            'chat_id' => $mChat,
                            'text' => $msg,
                            'parse_mode' => 'HTML'
                        ];
                        QueueService::push($pdo, $mChat, 'sendMessage', $payload);
                    }
                }

                LoggerService::logAction(
                    $pdo,
                    'broadcast',
                    'system',
                    0,
                    'outgoing',
                    'unanswered_complaints_reminder',
                    ['count' => $pendingComplaintsCount],
                    'success'
                );
                echo "Unanswered complaints reminder broadcasted to managers.\n";
            }
        }
    } catch (Exception $e) {
        error_log("Failed to process complaint reminders: " . $e->getMessage());
    }
}
