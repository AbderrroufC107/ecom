<?php
require __DIR__ . '/../admin/inc/config.php';
require __DIR__ . '/../admin/telegram/Services/TelegramService.php';
require __DIR__ . '/../admin/telegram/Services/HealthService.php';
$report = HealthService::checkHealth($pdo);
echo json_encode($report, JSON_UNESCAPED_UNICODE) . PHP_EOL;
