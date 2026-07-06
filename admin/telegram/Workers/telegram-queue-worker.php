<?php
/**
 * Telegram Queue Worker
 *
 * Runs via cron to process pending message queues.
 * Cron pattern: * * * * * php /path/to/admin/telegram/Workers/telegram-queue-worker.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../Services/TelegramService.php';
require_once __DIR__ . '/../Services/LoggerService.php';
require_once __DIR__ . '/../Services/QueueService.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed.");
}

$processed = QueueService::processQueue($pdo);
echo "[" . date('Y-m-d H:i:s') . "] Processed {$processed} queued Telegram messages.\n";
