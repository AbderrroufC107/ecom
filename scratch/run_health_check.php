<?php
require_once __DIR__ . '/../admin/inc/config.php';

// Bootstrap is already required in config.php, so HealthService is loaded if enabled.
// If not, we can load it manually.
require_once __DIR__ . '/../admin/telegram/Services/HealthService.php';
require_once __DIR__ . '/../admin/telegram/Services/TelegramService.php';

$report = HealthService::checkHealth($pdo);
print_r($report);
?>
