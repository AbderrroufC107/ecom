<?php
/**
 * HealthService Class
 *
 * Verifies systems integration integrity, bot API latency, queue states, and cache directories.
 */

declare(strict_types=1);

class HealthService
{
    /**
     * Executes health checks on all Telegram Bot dependencies.
     * Returns a structured report array.
     */
    public static function checkHealth(PDO $pdo): array
    { global $dbRepo;
        $report = [
            'api_status' => 'unknown',
            'api_latency_ms' => 0,
            'webhook_status' => 'unknown',
            'webhook_url' => '',
            'queue_pending' => 0,
            'queue_failed' => 0,
            'queue_dead_letter' => 0,
            'cache_writable' => false,
            'db_connected' => false,
            'is_enabled' => false,
            'errors' => []
        ];

        // 1. Verify Database
        try {
            $stmt = $dbRepo->query("SELECT 1");
            if ($stmt !== false) {
                $report['db_connected'] = true;
            }
        } catch (Exception $e) {
            $report['db_connected'] = false;
            $report['errors'][] = "Database connectivity failure: " . $e->getMessage();
            return $report;
        }

        // 2. Load Settings
        try {
            $stmt = $dbRepo->query("
                SELECT telegram_is_enabled, telegram_webhook_url, telegram_bot_token 
                FROM tbl_settings 
                WHERE id = 1 
                LIMIT 1
            ");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($settings) {
                $report['is_enabled'] = (int) ($settings['telegram_is_enabled'] ?? 0) === 1;
                $report['webhook_url'] = trim((string) ($settings['telegram_webhook_url'] ?? ''));
            }
        } catch (Exception $e) {
            $report['errors'][] = "Failed to load Telegram settings: " . $e->getMessage();
        }

        // 3. Verify Cache Directory Writable
        $cacheDir = __DIR__ . '/../../cache';
        if (is_writable($cacheDir)) {
            $report['cache_writable'] = true;
            // Test writing a temp file
            $tempFile = $cacheDir . '/telegram_health_test.tmp';
            if (@file_put_contents($tempFile, '1') !== false) {
                @unlink($tempFile);
            } else {
                $report['cache_writable'] = false;
                $report['errors'][] = "Cache directory exists but file writing is blocked.";
            }
        } else {
            $report['cache_writable'] = false;
            $report['errors'][] = "Cache directory '{$cacheDir}' is not writable.";
        }

        // 4. Verify Bot API Connectivity
        $telegramService = TelegramService::getInstance($pdo);
        if ($report['is_enabled'] && !empty($settings['telegram_bot_token'])) {
            $startTime = microtime(true);
            $res = $telegramService->apiCall('getMe');
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            $report['api_latency_ms'] = $latency;

            if (!empty($res['ok'])) {
                $report['api_status'] = 'connected';
            } else {
                $report['api_status'] = 'error';
                $report['errors'][] = "Bot API Error: " . ($res['description'] ?? 'No connection response.');
            }

            // Check Webhook Status from API
            $webhookRes = $telegramService->apiCall('getWebhookInfo');
            if (!empty($webhookRes['ok']) && isset($webhookRes['result'])) {
                $info = $webhookRes['result'];
                if (empty($info['url'])) {
                    $report['webhook_status'] = 'not_set';
                } else {
                    $report['webhook_status'] = 'set';
                    if (!empty($info['last_error_message'])) {
                        $report['webhook_status'] = 'error';
                        $report['errors'][] = "Telegram Webhook Error: " . $info['last_error_message'];
                    }
                }
            } else {
                $report['webhook_status'] = 'error';
            }
        } else {
            $report['api_status'] = 'disabled';
            $report['webhook_status'] = 'disabled';
        }

        // 5. Gather Queue Metrics
        try {
            $stmt = $dbRepo->query("
                SELECT 
                    SUM(CASE WHEN `status` = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN `status` = 'dead_letter' THEN 1 ELSE 0 END) AS dead_letter
                FROM `tbl_telegram_queue`
            ");
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($counts) {
                $report['queue_pending'] = (int) $counts['pending'];
                $report['queue_failed'] = (int) $counts['failed'];
                $report['queue_dead_letter'] = (int) $counts['dead_letter'];
            }
        } catch (Exception $e) {
            $report['errors'][] = "Failed to query queue metrics: " . $e->getMessage();
        }

        return $report;
    }
}
