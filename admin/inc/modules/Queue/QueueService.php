<?php

namespace Ecom\Queue;

use PDO;

class QueueService
{
    public static function enqueue(PDO $pdo, string $type, string $priority = 'normal',
        ?array $payload = null, ?int $storeId = null, ?string $scheduledAt = null): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_queue_jobs (store_id, type, payload, priority, scheduled_at)
            VALUES (?, ?, ?, ?, COALESCE(?, NOW()))");
        $stmt->execute([$storeId, $type, $payload ? json_encode($payload) : null, $priority, $scheduledAt]);
        return (int) $dbRepo->lastInsertId();
    }

    public static function bulkEnqueue(PDO $pdo, array $jobs): int
    { global $dbRepo;
        $count = 0;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_queue_jobs (store_id, type, payload, priority, scheduled_at)
            VALUES (?, ?, ?, ?, COALESCE(?, NOW()))");
        foreach ($jobs as $job) {
            $stmt->execute([
                $job['store_id'] ?? null,
                $job['type'],
                isset($job['payload']) ? json_encode($job['payload']) : null,
                $job['priority'] ?? 'normal',
                $job['scheduled_at'] ?? null,
            ]);
            $count++;
        }
        return $count;
    }

    public static function dequeue(PDO $pdo, string $type): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_queue_jobs
            WHERE type = ? AND status = 'pending' AND scheduled_at <= NOW()
            ORDER BY FIELD(priority, 'high', 'normal', 'low'), id ASC
            LIMIT 1 FOR UPDATE");
        $stmt->execute([$type]);
        $job = $stmt->fetch();
        if (!$job) {
            return null;
        }

        $update = $dbRepo->prepare("UPDATE tbl_queue_jobs SET status = 'processing', started_at = NOW(),
            attempts = attempts + 1 WHERE id = ?");
        $update->execute([$job['id']]);
        return $job;
    }

    public static function updateStatus(PDO $pdo, int $jobId, string $status,
        ?string $errorMessage = null): void
    { global $dbRepo;
        $sets = ['status = ?'];
        $params = [$status];

        if ($status === 'completed') {
            $sets[] = 'completed_at = NOW()';
        }
        if ($errorMessage !== null) {
            $sets[] = 'error_message = ?';
            $params[] = $errorMessage;
        }

        $params[] = $jobId;
        $stmt = $dbRepo->prepare("UPDATE tbl_queue_jobs SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    }

    public static function getStats(PDO $pdo): array
    { global $dbRepo;
        $stats = [
            'pending'    => 0,
            'processing' => 0,
            'completed'  => 0,
            'failed'     => 0,
            'cancelled'  => 0,
            'total'      => 0,
        ];

        try {
            $result = $dbRepo->query("SELECT status, COUNT(*) AS cnt FROM tbl_queue_jobs GROUP BY status");
            while ($row = $result->fetch()) {
                $stats[$row['status']] = (int) $row['cnt'];
            }
            $stats['total'] = array_sum($stats);
        } catch (\PDOException $e) {
        }

        return $stats;
    }

    public static function getJobs(PDO $pdo, int $page = 1, int $perPage = 50,
        ?string $statusFilter = null, ?string $typeFilter = null): array
    { global $dbRepo;
        $where = '1=1';
        $params = [];
        if ($statusFilter) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
        }
        if ($typeFilter) {
            $where .= ' AND type = ?';
            $params[] = $typeFilter;
        }
        $offset = ($page - 1) * $perPage;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_queue_jobs WHERE {$where}
            ORDER BY FIELD(priority, 'high', 'normal', 'low'), id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function getJobCount(PDO $pdo, ?string $statusFilter = null, ?string $typeFilter = null): int
    { global $dbRepo;
        $where = '1=1';
        $params = [];
        if ($statusFilter) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
        }
        if ($typeFilter) {
            $where .= ' AND type = ?';
            $params[] = $typeFilter;
        }
        $stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_queue_jobs WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function getJob(PDO $pdo, int $jobId): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_queue_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function retry(PDO $pdo, int $jobId): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("UPDATE tbl_queue_jobs SET status = 'pending', error_message = NULL,
            completed_at = NULL WHERE id = ?");
        $stmt->execute([$jobId]);
    }

    public static function cancel(PDO $pdo, int $jobId): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("UPDATE tbl_queue_jobs SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$jobId]);
    }

    public static function requeue(PDO $pdo, int $jobId, int $delayMinutes = 0): void
    { global $dbRepo;
        $scheduled = $delayMinutes > 0 ? date('Y-m-d H:i:s', time() + $delayMinutes * 60) : date('Y-m-d H:i:s');
        $stmt = $dbRepo->prepare("UPDATE tbl_queue_jobs SET status = 'pending',
            scheduled_at = ?, error_message = NULL, completed_at = NULL WHERE id = ?");
        $stmt->execute([$scheduled, $jobId]);
    }

    public static function getFailedJobs(PDO $pdo, int $page = 1, int $perPage = 50): array
    { global $dbRepo;
        $offset = ($page - 1) * $perPage;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_failed_jobs ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function getFailedJobCount(PDO $pdo): int
    { global $dbRepo;
        $stmt = $dbRepo->query("SELECT COUNT(*) FROM tbl_failed_jobs");
        return (int) $stmt->fetchColumn();
    }

    public static function moveToFailed(PDO $pdo, int $originalJobId, string $type,
        ?array $payload, string $priority, int $attempts, int $maxAttempts, string $errorMessage): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_failed_jobs (original_job_id, type, payload, priority,
            attempts, max_attempts, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$originalJobId, $type, $payload ? json_encode($payload) : null, $priority,
            $attempts, $maxAttempts, $errorMessage]);
    }

    public static function deleteFailedJob(PDO $pdo, int $failedJobId): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("DELETE FROM tbl_failed_jobs WHERE id = ?");
        $stmt->execute([$failedJobId]);
    }

    public static function requeueFailedJobs(PDO $pdo): int
    { global $dbRepo;
        $failed = $dbRepo->query("SELECT * FROM tbl_failed_jobs")->fetchAll();
        $count = 0;
        foreach ($failed as $job) {
            $stmt = $dbRepo->prepare("INSERT INTO tbl_queue_jobs (store_id, type, payload, priority, scheduled_at)
                VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $job['store_id'],
                $job['type'],
                $job['payload'],
                $job['priority'],
            ]);
            $del = $dbRepo->prepare("DELETE FROM tbl_failed_jobs WHERE id = ?");
            $del->execute([$job['id']]);
            $count++;
        }
        return $count;
    }

    public static function purgeCompleted(PDO $pdo, int $olderThanDays = 7): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("DELETE FROM tbl_queue_jobs WHERE status = 'completed'
            AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$olderThanDays]);
        return $stmt->rowCount();
    }

    public static function purgeFailedJobs(PDO $pdo, int $olderThanDays = 30): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("DELETE FROM tbl_failed_jobs WHERE failed_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$olderThanDays]);
        return $stmt->rowCount();
    }

    public static function enqueueTelegram(PDO $pdo, string $chatId, string $message): int
    { global $dbRepo;
        return self::enqueue($pdo, 'telegram_send', 'normal', [
            'chat_id' => $chatId,
            'message' => $message,
        ]);
    }

    public static function enqueueWebhook(PDO $pdo, int $webhookId, string $event, array $payload): int
    { global $dbRepo;
        return self::enqueue($pdo, 'webhook_delivery', 'high', [
            'webhook_id' => $webhookId,
            'event'      => $event,
            'payload'    => $payload,
        ]);
    }

    public static function enqueueEcotrackSync(PDO $pdo, string $action, array $data,
        int $storeId, string $priority = 'normal'): int
    { global $dbRepo;
        return self::enqueue($pdo, 'ecotrack_sync', $priority, [
            'action'   => $action,
            'data'     => $data,
        ], $storeId);
    }

    public static function enqueueAiReport(PDO $pdo, int $storeId, string $reportType): int
    { global $dbRepo;
        return self::enqueue($pdo, 'ai_report', 'low', [
            'report_type' => $reportType,
        ], $storeId);
    }

    public static function enqueueProductExport(PDO $pdo, int $storeId, string $format, array $filters = []): int
    { global $dbRepo;
        return self::enqueue($pdo, 'product_export', 'normal', [
            'format'  => $format,
            'filters' => $filters,
        ], $storeId);
    }

    public static function enqueueDataSync(PDO $pdo, int $storeId, string $target): int
    { global $dbRepo;
        return self::enqueue($pdo, 'data_sync', 'normal', [
            'target' => $target,
        ], $storeId);
    }

    public static function enqueueStoreBackup(PDO $pdo, int $storeId, string $scope = 'store'): int
    { global $dbRepo;
        return self::enqueue($pdo, 'backup_store', 'low', [
            'scope' => $scope,
        ], $storeId);
    }
}
