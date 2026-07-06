<?php
/**
 * StateService Class
 *
 * Manages temporary multi-step conversation states in the database.
 */

declare(strict_types=1);

class StateService
{
    /**
     * Store temporary conversation state for a chat session.
     */
    public static function setState(PDO $pdo, string $chatId, string $stateKey, array $payload = [], int $ttlSeconds = 900): void
    { global $dbRepo;
        $payloadStr = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        $pdo->beginTransaction();
        try {
            $stmt = $dbRepo->prepare("
                INSERT INTO `tbl_telegram_conversation_state` (`chat_id`, `state_key`, `payload`, `expires_at`)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE 
                    `state_key` = VALUES(`state_key`), 
                    `payload` = VALUES(`payload`), 
                    `expires_at` = VALUES(`expires_at`)
            ");
            $stmt->execute([$chatId, $stateKey, $payloadStr, $ttlSeconds]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to set Telegram conversation state: " . $e->getMessage());
        }
    }

    /**
     * Fetch active conversation state. Returns null if missing or expired.
     */
    public static function getState(PDO $pdo, string $chatId): ?array
    { global $dbRepo;
        try {
            // Automatically clean up expired states when fetching to keep DB clean
            self::cleanupExpiredStates($pdo);

            $stmt = $dbRepo->prepare("
                SELECT * FROM `tbl_telegram_conversation_state`
                WHERE `chat_id` = ? AND `expires_at` >= NOW()
                LIMIT 1
            ");
            $stmt->execute([$chatId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'state_key' => $row['state_key'],
                    'payload' => json_decode($row['payload'], true) ?: []
                ];
            }
        } catch (Exception $e) {
            error_log("Failed to fetch Telegram state: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Clear the conversation state for a chat session.
     */
    public static function clearState(PDO $pdo, string $chatId): void
    { global $dbRepo;
        $pdo->beginTransaction();
        try {
            $stmt = $dbRepo->prepare("DELETE FROM `tbl_telegram_conversation_state` WHERE `chat_id` = ?");
            $stmt->execute([$chatId]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }

    /**
     * Remove all expired conversation states from the database.
     */
    public static function cleanupExpiredStates(PDO $pdo): void
    { global $dbRepo;
        try {
            $dbRepo->executeCommand("DELETE FROM `tbl_telegram_conversation_state` WHERE `expires_at` < NOW()");
        } catch (Exception $e) {
            error_log("Failed to clean up expired Telegram states: " . $e->getMessage());
        }
    }
}
