<?php
/**
 * QueueService Class
 *
 * Handles database-backed Telegram API request queue, exponential backoff, and throttling.
 */

declare(strict_types=1);

class QueueService
{
    /**
     * Push a Telegram Bot API request to the queue.
     */
    public static function push(PDO $pdo, string $chatId, string $method, array $payload): int
    { global $dbRepo;
        try {
            $stmt = $dbRepo->prepare("
                INSERT INTO `tbl_telegram_queue` (
                    `chat_id`, `method`, `payload`, `attempts`, `max_attempts`, 
                    `status`, `next_attempt_at`, `created_at`
                ) VALUES (?, ?, ?, 0, 3, 'pending', NOW(), NOW())
            ");
            
            $stmt->execute([
                $chatId,
                $method,
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);
            
            return (int) $dbRepo->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to push to Telegram queue: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Process pending/failed queue jobs respecting exponential backoff and rate limits.
     * Returns the count of processed jobs.
     */
    public static function processQueue(PDO $pdo): int
    { global $dbRepo;
        // 1. Fetch settings to verify if queue processing is enabled
        try {
            $stmt = $dbRepo->query("SELECT telegram_is_enabled, telegram_enable_queue_processing, telegram_queue_retry_attempts FROM tbl_settings WHERE id = 1 LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$settings || (int) $settings['telegram_is_enabled'] !== 1 || (int) $settings['telegram_enable_queue_processing'] !== 1) {
                return 0;
            }
            $maxAttempts = (int) ($settings['telegram_queue_retry_attempts'] ?? 3);
        } catch (Exception $e) {
            return 0;
        }

        // 2. Fetch pending jobs ready to process
        $stmt = $dbRepo->prepare("
            SELECT * FROM `tbl_telegram_queue`
            WHERE `status` IN ('pending', 'failed')
              AND `next_attempt_at` <= NOW()
            ORDER BY `id` ASC
            LIMIT 15
        ");
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($jobs)) {
            return 0;
        }

        $processedCount = 0;
        $telegramService = TelegramService::getInstance($pdo);

        foreach ($jobs as $job) {
            $jobId = (int) $job['id'];
            
            // Mark job as processing
            $dbRepo->prepare("UPDATE `tbl_telegram_queue` SET `status` = 'processing' WHERE `id` = ?")->execute([$jobId]);
            
            $payload = json_decode($job['payload'], true) ?: [];
            $method = $job['method'];
            $chatId = $job['chat_id'];
            $attempts = (int) $job['attempts'] + 1;

            // Execute API Call
            $res = $telegramService->apiCall($method, $payload);
            $latencyMs = $res['latency_ms'] ?? 0;

            if (!empty($res['ok'])) {
                // Success!
                $dbRepo->prepare("
                    UPDATE `tbl_telegram_queue` 
                    SET `status` = 'completed', `error_message` = NULL 
                    WHERE `id` = ?
                ")->execute([$jobId]);

                LoggerService::logAction(
                    $pdo,
                    $chatId,
                    null,
                    null,
                    'outgoing',
                    $method,
                    $payload,
                    'success',
                    null,
                    (int) ($res['result']['message_id'] ?? null),
                    $latencyMs
                );

                $processedCount++;
                
                // Rate limit spacing: Telegram limits sending to 30 messages per second.
                // Space out requests by 50ms (0.05 seconds) to avoid immediate rate limit spikes.
                usleep(50000); 
            } else {
                // Failure
                $errorMsg = $res['description'] ?? 'خطأ غير معروف في تيليجرام';
                $rateLimited = !empty($res['rate_limited']);
                $retryAfter = isset($res['retry_after']) ? (int) $res['retry_after'] : 0;

                LoggerService::logAction(
                    $pdo,
                    $chatId,
                    null,
                    null,
                    'outgoing',
                    $method,
                    $payload,
                    'failed',
                    $errorMsg,
                    null,
                    $latencyMs
                );

                if ($attempts >= $maxAttempts && !$rateLimited) {
                    // Maximum retry threshold reached -> Move to Dead-Letter
                    $dbRepo->prepare("
                        UPDATE `tbl_telegram_queue` 
                        SET `status` = 'dead_letter', `attempts` = ?, `error_message` = ? 
                        WHERE `id` = ?
                    ")->execute([$attempts, $errorMsg, $jobId]);
                } else {
                    // Exponential Backoff calculation
                    // Backoff delay: 15s * 2^attempts (e.g. 30s, 60s, 120s)
                    $backoffSeconds = 15 * (1 << $attempts);
                    
                    // If rate limited, prioritize the Telegram requested retry-after value
                    if ($rateLimited && $retryAfter > 0) {
                        $backoffSeconds = $retryAfter + 2; // Add safety margin
                    }

                    $dbRepo->prepare("
                        UPDATE `tbl_telegram_queue` 
                        SET `status` = 'failed', 
                            `attempts` = ?, 
                            `error_message` = ?, 
                            `next_attempt_at` = DATE_ADD(NOW(), INTERVAL ? SECOND) 
                        WHERE `id` = ?
                    ")->execute([$attempts, $errorMsg, $backoffSeconds, $jobId]);
                }
            }
        }

        return $processedCount;
    }
}
