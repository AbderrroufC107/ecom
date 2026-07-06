<?php
/**
 * Telegram Bot Plugin Bootstrap
 *
 * Initializes the Telegram Bot plugin, registers providers, and subscribes listeners.
 */

declare(strict_types=1);

require_once __DIR__ . '/Services/EventManager.php';
require_once __DIR__ . '/Services/ProviderManager.php';
require_once __DIR__ . '/Services/TelegramService.php';
require_once __DIR__ . '/Services/TokenService.php';
require_once __DIR__ . '/Services/LoggerService.php';
require_once __DIR__ . '/Services/QueueService.php';
require_once __DIR__ . '/Services/StateService.php';
require_once __DIR__ . '/Services/HealthService.php';
require_once __DIR__ . '/Services/AuditService.php';
require_once __DIR__ . '/Services/NotificationService.php';

require_once __DIR__ . '/Providers/NotificationProviderInterface.php';
require_once __DIR__ . '/Providers/TelegramNotificationProvider.php';

// Only bootstrap if Telegram integration is enabled globally in settings
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $dbRepo->query("SELECT `telegram_is_enabled` FROM `tbl_settings` WHERE `id` = 1 LIMIT 1");
        $enabled = $stmt->fetchColumn();
        
        if ($enabled) {
            // Register Telegram notification channel provider
            ProviderManager::registerProvider('telegram', new TelegramNotificationProvider($pdo));
            
            // Register event listeners
            NotificationService::bootstrap();
        }
    }
} catch (Exception $e) {
    // Fail silently in production to avoid crashing the main application if db tables do not exist yet
    error_log("Telegram Bootstrap Error: " . $e->getMessage());
}
