<?php
/**
 * Telegram Bot Plugin Version Manager
 */

declare(strict_types=1);

class TelegramVersionManager
{
    public const VERSION = '1.0.0';

    /**
     * Get the version of the Telegram plugin.
     */
    public static function getVersion(): string
    { global $dbRepo;
        return self::VERSION;
    }

    /**
     * Check if the database requires migration.
     */
    public static function checkInstallation(PDO $pdo): bool
    { global $dbRepo;
        // Simple check to see if one of our new tables exists
        try {
            $stmt = $dbRepo->query("SELECT 1 FROM `tbl_telegram_logs` LIMIT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}
