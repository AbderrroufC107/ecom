<?php
/**
 * SecondaryBotLinkService
 *
 * Personal Telegram linking for the *secondary* bots (order-status,
 * incomplete-orders) configured on admin/settings.php - separate from the
 * main bot's existing tbl_user/tbl_employee flat-column linking, which stays
 * untouched. Each (owner, purpose) pair gets its own row here, so a manager
 * or employee can link their personal chat to each secondary bot
 * independently.
 */

declare(strict_types=1);

class SecondaryBotLinkService
{
    public const PURPOSES = ['incomplete', 'order_status'];

    public static function ensureTable(PDO $pdo): void
    { global $dbRepo;
        $lockFile = __DIR__ . '/../../cache/secondary_bot_links.lock';
        if (file_exists($lockFile)) {
            return;
        }

        $dbRepo->executeCommand("
            CREATE TABLE IF NOT EXISTS `tbl_telegram_secondary_bot_links` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `owner_type` ENUM('employee','manager') NOT NULL,
                `owner_id` INT NOT NULL,
                `bot_purpose` VARCHAR(32) NOT NULL,
                `chat_id` VARCHAR(255) DEFAULT NULL,
                `telegram_username` VARCHAR(255) DEFAULT NULL,
                `telegram_first_name` VARCHAR(255) DEFAULT NULL,
                `link_token` VARCHAR(64) DEFAULT NULL,
                `link_expires_at` DATETIME DEFAULT NULL,
                `linked_at` DATETIME DEFAULT NULL,
                `is_linked` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_owner_purpose` (`owner_type`, `owner_id`, `bot_purpose`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        @file_put_contents($lockFile, '1');
    }

    public static function isValidPurpose(string $purpose): bool
    { global $dbRepo;
        return in_array($purpose, self::PURPOSES, true);
    }

    /** Which tbl_settings bot token to use for a purpose, falling back to the main bot. */
    public static function getBotToken(PDO $pdo, string $purpose): string
    { global $dbRepo;
        $column = $purpose === 'order_status' ? 'telegram_order_status_bot_token' : 'telegram_incomplete_bot_token';
        $stmt = $dbRepo->query("SELECT `{$column}` AS purpose_token, telegram_bot_token AS main_token FROM tbl_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }
        $token = trim((string) ($row['purpose_token'] ?? ''));
        return $token !== '' ? $token : trim((string) ($row['main_token'] ?? ''));
    }

    /** True only when a genuinely separate token is configured for this purpose. */
    public static function hasDedicatedBot(PDO $pdo, string $purpose): bool
    { global $dbRepo;
        $column = $purpose === 'order_status' ? 'telegram_order_status_bot_token' : 'telegram_incomplete_bot_token';
        $stmt = $dbRepo->query("SELECT `{$column}` AS purpose_token FROM tbl_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && trim((string) ($row['purpose_token'] ?? '')) !== '';
    }

    public static function getBotUsername(PDO $pdo, string $purpose): string
    { global $dbRepo;
        $token = self::getBotToken($pdo, $purpose);
        if ($token === '') {
            return '';
        }

        $cacheFile = __DIR__ . '/../../cache/telegram_bot_username_' . $purpose . '.cache';
        if (file_exists($cacheFile)) {
            $cached = trim((string) @file_get_contents($cacheFile));
            if ($cached !== '') {
                return $cached;
            }
        }

        $response = @file_get_contents("https://api.telegram.org/bot{$token}/getMe");
        $decoded = $response !== false ? json_decode($response, true) : null;
        $username = trim((string) ($decoded['result']['username'] ?? ''));
        if ($username !== '') {
            @file_put_contents($cacheFile, $username);
        }
        return $username;
    }

    public static function getLinkStatus(PDO $pdo, string $ownerType, int $ownerId, string $purpose): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_telegram_secondary_bot_links WHERE owner_type = ? AND owner_id = ? AND bot_purpose = ? LIMIT 1");
        $stmt->execute([$ownerType, $ownerId, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function generateLinkToken(PDO $pdo, string $ownerType, int $ownerId, string $purpose): string
    { global $dbRepo;
        if (!self::isValidPurpose($purpose)) {
            throw new InvalidArgumentException('Invalid bot purpose.');
        }
        $token = bin2hex(random_bytes(32));

        $stmt = $dbRepo->prepare("
            INSERT INTO tbl_telegram_secondary_bot_links (owner_type, owner_id, bot_purpose, link_token, link_expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
            ON DUPLICATE KEY UPDATE link_token = VALUES(link_token), link_expires_at = VALUES(link_expires_at)
        ");
        $stmt->execute([$ownerType, $ownerId, $purpose, $token]);

        return $token;
    }

    /** @return array{success:bool, error?:string, owner_type?:string, owner_id?:int, purpose?:string} */
    public static function verifyAndLink(PDO $pdo, string $token, string $chatId, ?string $username, ?string $firstName): array
    { global $dbRepo;
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'الرمز فارغ أو غير صحيح.'];
        }

        $stmt = $dbRepo->prepare("SELECT * FROM tbl_telegram_secondary_bot_links WHERE link_token = ? AND link_expires_at >= NOW() LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'الرمز غير صالح أو منتهي الصلاحية (صلاحيته 15 دقيقة).'];
        }

        $dup = $dbRepo->prepare("SELECT id FROM tbl_telegram_secondary_bot_links WHERE chat_id = ? AND bot_purpose = ? AND is_linked = 1 AND id != ? LIMIT 1");
        $dup->execute([$chatId, $row['bot_purpose'], $row['id']]);
        if ($dup->fetch()) {
            return ['success' => false, 'error' => 'حساب Telegram هذا مرتبط بالفعل بحساب آخر لنفس هذا البوت.'];
        }

        $update = $dbRepo->prepare("
            UPDATE tbl_telegram_secondary_bot_links
            SET chat_id = ?, telegram_username = ?, telegram_first_name = ?, is_linked = 1,
                linked_at = NOW(), link_token = NULL, link_expires_at = NULL
            WHERE id = ?
        ");
        $update->execute([$chatId, $username, $firstName, $row['id']]);

        return [
            'success' => true,
            'owner_type' => $row['owner_type'],
            'owner_id' => (int) $row['owner_id'],
            'purpose' => $row['bot_purpose'],
        ];
    }

    public static function unlink(PDO $pdo, string $ownerType, int $ownerId, string $purpose): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("DELETE FROM tbl_telegram_secondary_bot_links WHERE owner_type = ? AND owner_id = ? AND bot_purpose = ?");
        $stmt->execute([$ownerType, $ownerId, $purpose]);
    }

    /** All linked chat_ids for a purpose - used when broadcasting a personal notification. */
    public static function getLinkedChatId(PDO $pdo, string $ownerType, int $ownerId, string $purpose): string
    { global $dbRepo;
        $row = self::getLinkStatus($pdo, $ownerType, $ownerId, $purpose);
        return ($row && !empty($row['is_linked'])) ? (string) $row['chat_id'] : '';
    }
}
