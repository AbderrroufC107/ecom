<?php
/**
 * TokenService Class
 *
 * Handles deep-link token generation, verification, and unlinking.
 */

declare(strict_types=1);

class TokenService
{
    /**
     * Generate a secure 64-character link token for a user.
     */
    public static function generateLinkToken(PDO $pdo, int $userId, string $userType): string
    { global $dbRepo;
        if (!in_array($userType, ['employee', 'manager'], true)) {
            throw new InvalidArgumentException("Invalid user type.");
        }

        $token = bin2hex(random_bytes(32)); // 64 hex chars
        
        $pdo->beginTransaction();
        try {
            if ($userType === 'employee') {
                $stmt = $dbRepo->prepare("
                    UPDATE `tbl_employee` 
                    SET `telegram_link_token` = ?, 
                        `telegram_link_expires_at` = DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
                    WHERE `id` = ?
                ");
            } else {
                $stmt = $dbRepo->prepare("
                    UPDATE `tbl_user` 
                    SET `telegram_link_token` = ?, 
                        `telegram_link_expires_at` = DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
                    WHERE `id` = ?
                ");
            }
            $stmt->execute([$token, $userId]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $token;
    }

    /**
     * Verify the deep-link token and bind the Telegram account details.
     * Returns an array with status (bool), role ('employee' or 'manager'), name, and user ID on success.
     */
    public static function verifyAndLink(PDO $pdo, string $token, string $chatId, ?string $username, ?string $firstName): array
    { global $dbRepo;
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'الرمز فارغ أو غير صحيح.'];
        }

        $pdo->beginTransaction();
        try {
            // Check uniqueness of chat_id (prevent linking same chat ID to multiple accounts)
            $checkEmployee = $dbRepo->prepare("SELECT id FROM `tbl_employee` WHERE `telegram_chat_id` = ? AND `telegram_is_linked` = 1 LIMIT 1");
            $checkEmployee->execute([$chatId]);
            if ($checkEmployee->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'حساب Telegram هذا مرتبط بالفعل بموظف آخر.'];
            }

            $checkUser = $dbRepo->prepare("SELECT id FROM `tbl_user` WHERE `telegram_chat_id` = ? AND `telegram_is_linked` = 1 LIMIT 1");
            $checkUser->execute([$chatId]);
            if ($checkUser->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'حساب Telegram هذا مرتبط بالفعل بمدير آخر.'];
            }

            // 1. Search in Employees
            $stmt = $dbRepo->prepare("
                SELECT id, full_name, is_active FROM `tbl_employee` 
                WHERE `telegram_link_token` = ? 
                  AND `telegram_link_expires_at` >= NOW() 
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($employee) {
                if ((int) $employee['is_active'] !== 1) {
                    $pdo->rollBack();
                    return ['success' => false, 'error' => 'حساب الموظف غير نشط. يرجى مراجعة الإدارة.'];
                }

                $update = $dbRepo->prepare("
                    UPDATE `tbl_employee` 
                    SET `telegram_chat_id` = ?, 
                        `telegram_username` = ?, 
                        `telegram_first_name` = ?, 
                        `telegram_is_linked` = 1, 
                        `telegram_linked_at` = NOW(), 
                        `telegram_link_token` = NULL, 
                        `telegram_link_expires_at` = NULL 
                    WHERE `id` = ?
                ");
                $update->execute([$chatId, $username, $firstName, $employee['id']]);
                
                $pdo->commit();
                return [
                    'success' => true,
                    'role' => 'employee',
                    'id' => (int) $employee['id'],
                    'name' => $employee['full_name']
                ];
            }

            // 2. Search in Managers/Users
            $stmt = $dbRepo->prepare("
                SELECT id, full_name, status FROM `tbl_user` 
                WHERE `telegram_link_token` = ? 
                  AND `telegram_link_expires_at` >= NOW() 
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $manager = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($manager) {
                if ((int) $manager['status'] !== 1) {
                    $pdo->rollBack();
                    return ['success' => false, 'error' => 'حساب المدير غير نشط. يرجى مراجعة المسؤول.'];
                }

                $update = $dbRepo->prepare("
                    UPDATE `tbl_user` 
                    SET `telegram_chat_id` = ?, 
                        `telegram_username` = ?, 
                        `telegram_first_name` = ?, 
                        `telegram_is_linked` = 1, 
                        `telegram_linked_at` = NOW(), 
                        `telegram_link_token` = NULL, 
                        `telegram_link_expires_at` = NULL 
                    WHERE `id` = ?
                ");
                $update->execute([$chatId, $username, $firstName, $manager['id']]);
                
                $pdo->commit();
                return [
                    'success' => true,
                    'role' => 'manager',
                    'id' => (int) $manager['id'],
                    'name' => $manager['full_name']
                ];
            }

            $pdo->rollBack();
            return ['success' => false, 'error' => 'الرمز غير صالح أو منتهي الصلاحية (صلاحيته 15 دقيقة).'];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Unlink a user's Telegram integration.
     */
    public static function unlink(PDO $pdo, int $userId, string $userType): bool
    { global $dbRepo;
        if (!in_array($userType, ['employee', 'manager'], true)) {
            throw new InvalidArgumentException("Invalid user type.");
        }

        $pdo->beginTransaction();
        try {
            if ($userType === 'employee') {
                $stmt = $dbRepo->prepare("
                    UPDATE `tbl_employee` 
                    SET `telegram_chat_id` = '', 
                        `telegram_username` = NULL, 
                        `telegram_first_name` = NULL, 
                        `telegram_is_linked` = 0, 
                        `telegram_linked_at` = NULL, 
                        `telegram_link_token` = NULL, 
                        `telegram_link_expires_at` = NULL 
                    WHERE `id` = ?
                ");
            } else {
                $stmt = $dbRepo->prepare("
                    UPDATE `tbl_user` 
                    SET `telegram_chat_id` = NULL, 
                        `telegram_username` = NULL, 
                        `telegram_first_name` = NULL, 
                        `telegram_is_linked` = 0, 
                        `telegram_linked_at` = NULL, 
                        `telegram_link_token` = NULL, 
                        `telegram_link_expires_at` = NULL 
                    WHERE `id` = ?
                ");
            }
            $stmt->execute([$userId]);
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
