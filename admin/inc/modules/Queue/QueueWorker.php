<?php

namespace Ecom\Queue;

use PDO;

class QueueWorker
{
    public static function getJobTypes(): array
    {
        return [
            'telegram_send',
            'webhook_delivery',
            'ecotrack_sync',
            'ai_report',
            'product_export',
            'data_sync',
            'backup_database',
            'backup_files',
            'backup_store',
            'audit_cleanup',
            'invoice_reminder',
        ];
    }

    public static function getPriorities(): array
    {
        return ['high', 'normal', 'low'];
    }

    public static function getBackoffMinutes(int $attempt): int
    {
        $backoffs = [0, 5, 15, 30, 60, 120];
        $index = min($attempt, count($backoffs) - 1);
        return $backoffs[$index];
    }

    public static function processJob(PDO $pdo, array $job): array
    {
        $type = $job['type'];
        $payload = $job['payload'] ? json_decode($job['payload'], true) : [];

        $handler = 'handle' . str_replace('_', '', ucwords($type, '_'));
        if (method_exists(self::class, $handler)) {
            try {
                $result = self::$handler($pdo, $payload, $job);
                QueueService::updateStatus($pdo, (int) $job['id'], 'completed');
                return ['success' => true, 'result' => $result];
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $attempts = (int) $job['attempts'];
                $maxAttempts = (int) $job['max_attempts'];

                if ($attempts >= $maxAttempts) {
                    QueueService::moveToFailed(
                        $pdo, (int) $job['id'], $type,
                        $job['payload'] ? json_decode($job['payload'], true) : null,
                        $job['priority'], $attempts, $maxAttempts, $errorMsg
                    );
                    QueueService::updateStatus($pdo, (int) $job['id'], 'failed', $errorMsg);
                } else {
                    $backoff = self::getBackoffMinutes($attempts);
                    QueueService::requeue($pdo, (int) $job['id'], $backoff);
                }
                return ['success' => false, 'error' => $errorMsg];
            }
        }

        QueueService::updateStatus($pdo, (int) $job['id'], 'failed', "No handler for type: {$type}");
        return ['success' => false, 'error' => "No handler for type: {$type}"];
    }

    public static function cleanupStuck(PDO $pdo, int $timeoutMinutes = 30): int
    {
        $stmt = $pdo->prepare("UPDATE tbl_queue_jobs SET status = 'pending',
            error_message = CONCAT(COALESCE(error_message, ''), ' | Auto-retry after stuck timeout')
            WHERE status = 'processing' AND started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$timeoutMinutes]);
        return $stmt->rowCount();
    }

    public static function handleTelegramSend(PDO $pdo, array $payload, array $job): bool
    {
        $chatId = $payload['chat_id'] ?? '';
        $message = $payload['message'] ?? '';
        if (empty($chatId) || empty($message)) {
            return false;
        }
        $token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';
        if (empty($token)) {
            return false;
        }
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $postData = http_build_query(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
    }

    public static function handleWebhookDelivery(PDO $pdo, array $payload, array $job): bool
    {
        $webhookId = $payload['webhook_id'] ?? 0;
        $event = $payload['event'] ?? '';
        $eventPayload = $payload['payload'] ?? [];

        $stmt = $pdo->prepare("SELECT * FROM tbl_webhooks WHERE id = ? AND status = 'active'");
        $stmt->execute([$webhookId]);
        $webhook = $stmt->fetch();
        if (!$webhook) {
            return false;
        }

        $body = json_encode([
            'event'   => $event,
            'payload' => $eventPayload,
            'sent_at' => date('c'),
        ]);

        $signature = hash_hmac('sha256', $body, $webhook['secret'] ?? '');

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'X-Webhook-Event: ' . $event,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $pdo->prepare("UPDATE tbl_webhooks SET last_triggered_at = NOW(), failure_count = 0 WHERE id = ?")
                ->execute([$webhookId]);
            return true;
        }

        $pdo->prepare("UPDATE tbl_webhooks SET failure_count = failure_count + 1 WHERE id = ?")
            ->execute([$webhookId]);

        $stmt = $pdo->prepare("SELECT failure_count FROM tbl_webhooks WHERE id = ?");
        $stmt->execute([$webhookId]);
        $failCount = (int) $stmt->fetchColumn();

        if ($failCount >= 10) {
            $pdo->prepare("UPDATE tbl_webhooks SET status = 'failed' WHERE id = ?")
                ->execute([$webhookId]);
        }

        return false;
    }

    public static function handleEcotrackSync(PDO $pdo, array $payload, array $job): bool
    {
        $action = $payload['action'] ?? '';
        $data = $payload['data'] ?? [];

        if (empty($action)) {
            return false;
        }

        $apiUrl = defined('ECOTRACK_API_URL') ? ECOTRACK_API_URL : '';
        $apiKey = defined('ECOTRACK_API_KEY') ? ECOTRACK_API_KEY : '';

        if (empty($apiUrl) || empty($apiKey)) {
            return false;
        }

        $ch = curl_init(rtrim($apiUrl, '/') . '/api/sync');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['action' => $action, 'data' => $data]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public static function handleAiReport(PDO $pdo, array $payload, array $job): array
    {
        $reportType = $payload['report_type'] ?? 'weekly';
        $storeId = $job['store_id'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_products WHERE store_id = ?");
        $stmt->execute([$storeId]);
        $productCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM tbl_invoices
            WHERE store_id = ? AND status = 'paid'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$storeId]);
        $monthlyRevenue = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(COUNT(*), 0) FROM tbl_invoices
            WHERE store_id = ? AND status = 'paid'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$storeId]);
        $monthlyOrders = (int) $stmt->fetchColumn();

        $report = [
            'generated_at'  => date('c'),
            'report_type'   => $reportType,
            'store_id'      => $storeId,
            'metrics'       => [
                'total_products'  => $productCount,
                'monthly_revenue' => $monthlyRevenue,
                'monthly_orders'  => $monthlyOrders,
            ],
        ];

        $reportJson = json_encode($report);
        $stmt = $pdo->prepare("INSERT INTO tbl_ai_reports (store_id, report_type, report_data)
            VALUES (?, ?, ?)");
        $stmt->execute([$storeId, $reportType, $reportJson]);

        return $report;
    }

    public static function handleProductExport(PDO $pdo, array $payload, array $job): string
    {
        $storeId = $job['store_id'];
        $format = $payload['format'] ?? 'csv';
        $filters = $payload['filters'] ?? [];

        $sql = "SELECT * FROM tbl_products WHERE store_id = ?";
        $params = [$storeId];

        if (!empty($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        $exportDir = __DIR__ . '/../../../../exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filename = "products_store_{$storeId}_" . date('Ymd_His') . ".{$format}";
        $filepath = $exportDir . '/' . $filename;

        if ($format === 'csv') {
            $handle = fopen($filepath, 'w');
            if (!empty($products)) {
                fputcsv($handle, array_keys($products[0]));
            }
            foreach ($products as $product) {
                fputcsv($handle, $product);
            }
            fclose($handle);
        } else {
            file_put_contents($filepath, json_encode($products, JSON_PRETTY_PRINT));
        }

        return $filepath;
    }

    public static function handleDataSync(PDO $pdo, array $payload, array $job): bool
    {
        $target = $payload['target'] ?? '';
        $storeId = $job['store_id'];

        $stmt = $pdo->prepare("SELECT * FROM tbl_products WHERE store_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$storeId]);
        $products = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT setting_value FROM tbl_store_settings WHERE store_id = ? AND setting_key = 'sync_url'");
        $stmt->execute([$storeId]);
        $syncUrl = $stmt->fetchColumn();

        if (empty($syncUrl)) {
            return false;
        }

        $ch = curl_init($syncUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['target' => $target, 'products' => $products]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public static function handleAuditCleanup(PDO $pdo, array $payload, array $job): int
    {
        $stmt = $pdo->prepare("DELETE FROM tbl_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function handleInvoiceReminder(PDO $pdo, array $payload, array $job): int
    {
        $stmt = $pdo->prepare("SELECT i.*, s.name AS store_name, s.email
            FROM tbl_invoices i
            LEFT JOIN tbl_stores s ON i.store_id = s.id
            WHERE i.status = 'pending' AND i.due_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)");
        $stmt->execute();
        $overdueInvoices = $stmt->fetchAll();
        return count($overdueInvoices);
    }
}
