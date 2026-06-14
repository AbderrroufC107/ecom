<?php

namespace Ecom\Api;

use PDO;
use Ecom\Common\Helpers;

class ApiKeyService
{
    private static array $availablePermissions = [
        'products.read', 'products.write',
        'orders.read', 'orders.write',
        'customers.read', 'customers.write',
        'analytics.read',
        'settings.read', 'settings.write',
        'webhooks.read', 'webhooks.write',
    ];

    public static function getAvailablePermissions(): array
    {
        return self::$availablePermissions;
    }

    public static function generateKey(): string
    {
        return 'ecom_' . Helpers::generateToken(32);
    }

    public static function create(PDO $pdo, int $storeId, string $label, array $permissions = [],
        ?array $ipWhitelist = null, ?string $expiresAt = null): int
    {
        $apiKey = self::generateKey();
        $stmt = $pdo->prepare("INSERT INTO tbl_api_keys (store_id, label, api_key, permissions,
            ip_whitelist, status, expires_at) VALUES (?, ?, ?, ?, ?, 'active', ?)");
        $stmt->execute([
            $storeId,
            $label,
            $apiKey,
            json_encode($permissions),
            $ipWhitelist ? json_encode($ipWhitelist) : null,
            $expiresAt,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function getKeys(PDO $pdo, int $storeId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_api_keys WHERE store_id = ? ORDER BY id DESC");
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $keyId, int $storeId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_api_keys WHERE id = ? AND store_id = ?");
        $stmt->execute([$keyId, $storeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function revoke(PDO $pdo, int $keyId, int $storeId): void
    {
        $stmt = $pdo->prepare("UPDATE tbl_api_keys SET status = 'revoked' WHERE id = ? AND store_id = ?");
        $stmt->execute([$keyId, $storeId]);
    }

    public static function rotate(PDO $pdo, int $keyId, int $storeId): string
    {
        $newKey = self::generateKey();
        $stmt = $pdo->prepare("UPDATE tbl_api_keys SET api_key = ? WHERE id = ? AND store_id = ?");
        $stmt->execute([$newKey, $keyId, $storeId]);
        return $newKey;
    }

    public static function getPermissions(PDO $pdo, string $apiKey): array
    {
        $stmt = $pdo->prepare("SELECT permissions FROM tbl_api_keys WHERE api_key = ? AND status = 'active'");
        $stmt->execute([$apiKey]);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }
        return json_decode($row['permissions'], true) ?? [];
    }

    public static function validate(PDO $pdo, string $apiKey): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_api_keys WHERE api_key = ? AND status = 'active'
            AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$apiKey]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipWhitelist = $row['ip_whitelist'] ? json_decode($row['ip_whitelist'], true) : [];
        if (!empty($ipWhitelist) && !in_array($ipAddress, $ipWhitelist)) {
            return null;
        }

        $pdo->prepare("UPDATE tbl_api_keys SET last_used_at = NOW() WHERE id = ?")
            ->execute([$row['id']]);

        return $row;
    }

    public static function logCall(PDO $pdo, int $storeId, string $endpoint, string $method,
        int $statusCode, ?int $apiKeyId = null, int $responseTimeMs = 0): void
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_api_logs (store_id, api_key_id, endpoint, method,
            status_code, ip_address, user_agent, response_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $storeId,
            $apiKeyId,
            $endpoint,
            $method,
            $statusCode,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $responseTimeMs,
        ]);
    }

    public static function getUsageStats(PDO $pdo, int $storeId): array
    {
        $stats = [
            'total_calls'  => 0,
            'by_endpoint'  => [],
            'by_method'    => [],
            'recent_calls' => [],
        ];

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_api_logs WHERE store_id = ?");
            $stmt->execute([$storeId]);
            $stats['total_calls'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT endpoint, COUNT(*) AS cnt
                FROM tbl_api_logs WHERE store_id = ? GROUP BY endpoint ORDER BY cnt DESC");
            $stmt->execute([$storeId]);
            $stats['by_endpoint'] = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT method, COUNT(*) AS cnt
                FROM tbl_api_logs WHERE store_id = ? GROUP BY method");
            $stmt->execute([$storeId]);
            $stats['by_method'] = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT * FROM tbl_api_logs WHERE store_id = ? ORDER BY id DESC LIMIT 10");
            $stmt->execute([$storeId]);
            $stats['recent_calls'] = $stmt->fetchAll();
        } catch (\PDOException $e) {
        }

        return $stats;
    }

    public static function getLogs(PDO $pdo, int $storeId, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT * FROM tbl_api_logs WHERE store_id = ?
            ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$storeId, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function hasPermission(string $required, array $permissions): bool
    {
        if (in_array('*', $permissions)) {
            return true;
        }
        return in_array($required, $permissions);
    }
}
