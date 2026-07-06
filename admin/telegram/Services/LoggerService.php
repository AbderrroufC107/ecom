<?php
/**
 * LoggerService Class
 *
 * Centralized logging helper for bot activities, API calls, and errors.
 */

declare(strict_types=1);

class LoggerService
{
    /**
     * Record a bot activity log entry inside tbl_telegram_logs.
     */
    public static function logAction(
        PDO $pdo,
        string $chatId,
        ?string $userType,
        ?int $userId,
        string $messageType,
        string $actionName,
        $payload,
        string $status,
        ?string $errorMessage = null,
        ?int $telegramMessageId = null,
        int $latencyMs = 0
    ): int { global $dbRepo;
        try {
            $payloadStr = null;
            if ($payload !== null) {
                $payloadStr = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
            }

            // Fetch IP Address safely
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ipAddress === '::1') {
                $ipAddress = '127.0.0.1';
            }

            $stmt = $dbRepo->prepare("
                INSERT INTO `tbl_telegram_logs` (
                    `chat_id`, `user_type`, `user_id`, `telegram_message_id`, 
                    `message_type`, `action_name`, `payload`, `status`, 
                    `ip_address`, `error_message`, `latency_ms`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $chatId,
                $userType,
                $userId,
                $telegramMessageId,
                $messageType,
                $actionName,
                $payloadStr,
                $status,
                $ipAddress,
                $errorMessage,
                $latencyMs
            ]);

            return (int) $dbRepo->lastInsertId();
        } catch (Exception $e) {
            // Keep error logging safe and non-blocking
            error_log("Failed to write Telegram log: " . $e->getMessage());
            return 0;
        }
    }
}
