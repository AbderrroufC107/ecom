<?php

namespace Ecom\Api;

use PDO;

class WebhookService
{
    private static array $events = [
        'order.created',
        'order.updated',
        'order.deleted',
        'order.paid',
        'product.created',
        'product.updated',
        'product.deleted',
        'customer.created',
        'customer.updated',
        'store.updated',
        'backup.completed',
        'backup.failed',
    ];

    public static function getEventsList(): array
    {
        return self::$events;
    }

    public static function create(PDO $pdo, int $storeId, string $url, array $events,
        ?string $secret = null): int
    {
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
        }
        $stmt = $pdo->prepare("INSERT INTO tbl_webhooks (store_id, url, events, secret, status)
            VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$storeId, $url, json_encode($events), $secret]);
        return (int) $pdo->lastInsertId();
    }

    public static function getWebhooks(PDO $pdo, int $storeId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_webhooks WHERE store_id = ? ORDER BY id DESC");
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $webhookId, int $storeId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_webhooks WHERE id = ? AND store_id = ?");
        $stmt->execute([$webhookId, $storeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(PDO $pdo, int $webhookId, int $storeId, array $data): void
    {
        $allowed = ['url', 'events', 'status', 'secret'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($field === 'events' && is_array($value)) {
                    $value = json_encode($value);
                }
                $sets[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $webhookId;
        $params[] = $storeId;
        $stmt = $pdo->prepare("UPDATE tbl_webhooks SET " . implode(', ', $sets) . " WHERE id = ? AND store_id = ?");
        $stmt->execute($params);
    }

    public static function delete(PDO $pdo, int $webhookId, int $storeId): void
    {
        $stmt = $pdo->prepare("DELETE FROM tbl_webhooks WHERE id = ? AND store_id = ?");
        $stmt->execute([$webhookId, $storeId]);
    }

    public static function getForEvent(PDO $pdo, string $event, ?int $storeId = null): array
    {
        if ($storeId) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_webhooks
                WHERE store_id = ? AND status = 'active' AND JSON_CONTAINS(events, ?)");
            $stmt->execute([$storeId, json_encode($event)]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tbl_webhooks
                WHERE status = 'active' AND JSON_CONTAINS(events, ?)");
            $stmt->execute([json_encode($event)]);
        }
        return $stmt->fetchAll();
    }

    public static function deliver(PDO $pdo, array $webhook, string $event, array $payload): bool
    {
        $body = json_encode([
            'event'   => $event,
            'payload' => $payload,
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
                ->execute([$webhook['id']]);
            return true;
        }

        $pdo->prepare("UPDATE tbl_webhooks SET failure_count = failure_count + 1 WHERE id = ?")
            ->execute([$webhook['id']]);

        if ((int) $webhook['failure_count'] + 1 >= 10) {
            $pdo->prepare("UPDATE tbl_webhooks SET status = 'failed' WHERE id = ?")
                ->execute([$webhook['id']]);
        }

        return false;
    }

    public static function trigger(PDO $pdo, string $event, array $payload, ?int $storeId = null): array
    {
        $results = [];
        $webhooks = self::getForEvent($pdo, $event, $storeId);

        foreach ($webhooks as $webhook) {
            $success = self::deliver($pdo, $webhook, $event, $payload);
            $results[] = [
                'webhook_id' => $webhook['id'],
                'url'        => $webhook['url'],
                'success'    => $success,
            ];
        }

        return $results;
    }
}
