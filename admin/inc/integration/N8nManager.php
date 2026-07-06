<?php
namespace Integration;

use PDO;
use Exception;

/**
 * N8nManager - Dynamic n8n Integration Manager
 *
 * Single responsibility: All n8n communications go through here.
 * Never write a hardcoded n8n URL anywhere else in the codebase.
 *
 * Usage:
 *   $n8n = new N8nManager($pdo);
 *   $n8n->callWebhook('ai_agent', ['message' => 'hello']);
 */
class N8nManager
{
    private PDO $pdo;
    private ?array $config = null;
    private string $environment;

    // Canonical webhook keys - add new ones here
    private const WEBHOOK_KEYS = [
        'ai_agent'          => '/webhook/ai-sales-agent-v2',
        'product_sync'      => '/webhook/product-sync',
        'provider_manager'  => '/webhook/provider-manager',
        'customer360'       => '/webhook/customer360',
        'order_events'      => '/webhook/order-events',
        'analytics'         => '/webhook/analytics',
        'notifications'     => '/webhook/notifications',
    ];

    public function __construct(PDO $pdo, string $environment = 'production')
    { global $dbRepo;
    global $dbRepo;

        $this->pdo = $pdo;
        $this->environment = $environment;
    }

    // ─────────────────────────────────────────────
    // Config Loading
    // ─────────────────────────────────────────────

    private function loadConfig(): array
    { global $dbRepo;
        if ($this->config !== null) {
            return $this->config;
        }

        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
            "SELECT * FROM tbl_n8n_integrations WHERE environment = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$this->environment]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Fallback: try 'production'
            $stmt2 = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT * FROM tbl_n8n_integrations WHERE is_active = 1 ORDER BY FIELD(environment,'production','staging','development') LIMIT 1"
            );
            $stmt2->execute();
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        }

        if (!$row) {
            throw new Exception('n8n integration is not configured. Please set it up in Settings → n8n Integration.');
        }

        $this->config = $row;
        return $this->config;
    }

    // ─────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────

    /**
     * Get the base URL (e.g., https://thikastore.app.n8n.cloud)
     */
    public function getBaseUrl(): string
    { global $dbRepo;
        return rtrim($this->loadConfig()['base_url'], '/');
    }

    /**
     * Get full webhook URL by name key.
     * e.g. getWebhook('ai_agent') => 'https://xxx.n8n.cloud/webhook/ai-sales-agent-v2'
     */
    public function getWebhook(string $key): string
    { global $dbRepo;
        $config = $this->loadConfig();
        $webhooks = json_decode($config['webhook_paths'] ?? '{}', true) ?: [];

        // Custom path from DB overrides default
        $path = $webhooks[$key] ?? (self::WEBHOOK_KEYS[$key] ?? null);

        if (!$path) {
            throw new Exception("Unknown webhook key: '{$key}'. Register it in tbl_n8n_integrations or N8nManager::WEBHOOK_KEYS.");
        }

        return $this->getBaseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * Call a webhook by key with a JSON payload.
     * Handles retry with exponential backoff.
     */
    public function callWebhook(string $key, array $payload, int $timeoutSeconds = 30): array
    { global $dbRepo;
        $url = $this->getWebhook($key);
        $config = $this->loadConfig();
        $apiKey = $this->decryptApiKey($config['api_key'] ?? '');

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($apiKey) {
            $headers[] = 'X-N8N-API-KEY: ' . $apiKey;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $attempt = 0;
        $maxRetries = 2;
        $delay = 1;
        $lastError = '';

        while ($attempt <= $maxRetries) {
            $startTime = microtime(true);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $duration = round((microtime(true) - $startTime) * 1000);
            $curlError = curl_error($ch);
            curl_close($ch);

            $this->logCall($key, $url, $httpCode, $duration, $curlError ?: null);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success'   => true,
                    'http_code' => $httpCode,
                    'response'  => json_decode($response, true) ?? $response,
                    'duration'  => $duration,
                ];
            }

            $lastError = $curlError ?: "HTTP {$httpCode}";
            $attempt++;

            if ($attempt <= $maxRetries) {
                sleep($delay);
                $delay *= 2;
            }
        }

        return [
            'success'   => false,
            'http_code' => $httpCode ?? 0,
            'error'     => $lastError,
        ];
    }

    /**
     * Ping n8n base URL to verify connectivity.
     */
    public function ping(): array
    { global $dbRepo;
        $startTime = microtime(true);
        try {
            $url = $this->getBaseUrl();
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_NOBODY         => true,
            ]);
            curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            $duration = round((microtime(true) - $startTime) * 1000);

            // n8n returns 302 or 200 on root - both mean it's reachable
            $online = ($httpCode >= 200 && $httpCode < 500) && !$error;

            return [
                'online'    => $online,
                'http_code' => $httpCode,
                'duration'  => $duration,
                'error'     => $error ?: null,
            ];
        } catch (Exception $e) {
            return ['online' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Returns true if n8n is reachable.
     */
    public function isOnline(): bool
    { global $dbRepo;
        return $this->ping()['online'] ?? false;
    }

    // ─────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────

    private function logCall(string $webhookKey, string $url, int $httpCode, int $duration, ?string $error): void
    { global $dbRepo;
        try {
            (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                INSERT INTO tbl_n8n_call_log (webhook_key, url, http_code, duration_ms, error, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$webhookKey, $url, $httpCode, $duration, $error]);
        } catch (Exception $e) {
            error_log('N8nManager::logCall failed: ' . $e->getMessage());
        }
    }

    private function decryptApiKey(string $encrypted): string
    { global $dbRepo;
        if (!$encrypted) return '';
        try {
            // Use the same APP_SECRET_KEY defined in config.php
            $key = defined('APP_SECRET_KEY') ? base64_decode(APP_SECRET_KEY) : '';
            if (!$key) return '';
            $data = base64_decode($encrypted);
            $iv = substr($data, 0, 16);
            $cipher = substr($data, 16);
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv);
            return $plain !== false ? $plain : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public static function encryptApiKey(string $plaintext): string
    { global $dbRepo;
        $key = defined('APP_SECRET_KEY') ? base64_decode(APP_SECRET_KEY) : '';
        if (!$key || !$plaintext) return '';
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $cipher);
    }

    // ─────────────────────────────────────────────
    // DB Table Bootstrap
    // ─────────────────────────────────────────────

    public static function ensureTables(PDO $pdo): void
    { global $dbRepo;
        $dbRepo->executeCommand("
            CREATE TABLE IF NOT EXISTS tbl_n8n_integrations (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                environment   ENUM('production','staging','development') NOT NULL DEFAULT 'production',
                label         VARCHAR(100) NOT NULL DEFAULT 'Default',
                base_url      VARCHAR(500) NOT NULL DEFAULT '',
                webhook_paths LONGTEXT NULL COMMENT 'JSON: {ai_agent: /webhook/..., ...}',
                api_key       VARCHAR(1000) NOT NULL DEFAULT '' COMMENT 'Encrypted',
                is_active     TINYINT(1) NOT NULL DEFAULT 1,
                last_tested   DATETIME NULL,
                last_status   ENUM('online','offline','untested') NOT NULL DEFAULT 'untested',
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_env_label (environment, label)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_n8n_integrations");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        if ($count == 0) {
            $defaultPaths = json_encode([
                'ai_agent' => '/webhook/ai-sales-agent-v2',
                'product_sync' => '/webhook/product-sync',
                'provider_manager' => '/webhook/provider-manager',
                'customer360' => '/webhook/customer360',
                'order_events' => '/webhook/order-events',
                'analytics' => '/webhook/analytics',
                'notifications' => '/webhook/notifications'
            ]);
            $dbRepo->prepare("INSERT IGNORE INTO tbl_n8n_integrations (environment, label, base_url, webhook_paths, is_active) VALUES ('production', 'الخادم الرئيسي (Production)', 'https://n8n.yourdomain.com', ?, 1)")->execute([$defaultPaths]);
        }

        $dbRepo->executeCommand("
            CREATE TABLE IF NOT EXISTS tbl_n8n_call_log (
                id          BIGINT AUTO_INCREMENT PRIMARY KEY,
                webhook_key VARCHAR(80) NOT NULL,
                url         VARCHAR(500) NOT NULL,
                http_code   SMALLINT NOT NULL DEFAULT 0,
                duration_ms INT NOT NULL DEFAULT 0,
                error       TEXT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_webhook_key (webhook_key),
                KEY idx_created_at  (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
