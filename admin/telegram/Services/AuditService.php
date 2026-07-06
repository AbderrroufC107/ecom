<?php
/**
 * AuditService Class
 *
 * Records administrative system actions inside tbl_telegram_audit.
 */

declare(strict_types=1);

class AuditService
{
    /**
     * Record an audit log entry for administrative changes.
     */
    public static function logAudit(
        PDO $pdo,
        int $adminId,
        string $actionName,
        ?string $previousValue = null,
        ?string $newValue = null
    ): int { global $dbRepo;
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ipAddress === '::1') {
                $ipAddress = '127.0.0.1';
            }

            $stmt = $dbRepo->prepare("
                INSERT INTO `tbl_telegram_audit` (
                    `admin_id`, `action_name`, `previous_value`, `new_value`, 
                    `ip_address`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $adminId,
                $actionName,
                $previousValue,
                $newValue,
                $ipAddress
            ]);

            return (int) $dbRepo->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to write Telegram audit log: " . $e->getMessage());
            return 0;
        }
    }
}
