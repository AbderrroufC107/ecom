<?php

namespace Ecom\Queue;

use PDO;

class QueueHealth
{
    public static function getHealth(PDO $pdo): array
    {
        $stats = QueueService::getStats($pdo);

        $processingTime = self::getAvgProcessingTime($pdo);
        $stuckCount = 0;
        $failedTrend = [];

        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_queue_jobs
                WHERE status = 'processing' AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
            $stuckCount = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
        }

        try {
            $result = $pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                FROM tbl_failed_jobs WHERE failed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at) ORDER BY day ASC");
            while ($row = $result->fetch()) {
                $failedTrend[] = $row;
            }
        } catch (\PDOException $e) {
        }

        $successRate = 0;
        if (($stats['completed'] + $stats['failed']) > 0) {
            $successRate = round(
                ($stats['completed'] / ($stats['completed'] + $stats['failed'])) * 100, 2
            );
        }

        return [
            'stats'           => $stats,
            'avg_processing_s' => round($processingTime, 2),
            'stuck_jobs'       => $stuckCount,
            'success_rate'     => $successRate,
            'failed_trend'     => $failedTrend,
        ];
    }

    public static function getAvgProcessingTime(PDO $pdo): float
    {
        try {
            $stmt = $pdo->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)), 0)
                FROM tbl_queue_jobs WHERE status = 'completed' AND started_at IS NOT NULL
                AND completed_at IS NOT NULL AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            return (float) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    public static function getByTypeOverTime(PDO $pdo, int $hours = 24): array
    {
        $data = [];
        try {
            $stmt = $pdo->prepare("SELECT type, status, COUNT(*) AS cnt
                FROM tbl_queue_jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY type, status ORDER BY type");
            $stmt->execute([$hours]);
            while ($row = $stmt->fetch()) {
                if (!isset($data[$row['type']])) {
                    $data[$row['type']] = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
                }
                $data[$row['type']][$row['status']] = (int) $row['cnt'];
            }
        } catch (\PDOException $e) {
        }
        return $data;
    }

    public static function checkAndAlert(PDO $pdo, ?string $telegramChatId = null): array
    {
        $alerts = [];
        $health = self::getHealth($pdo);

        if ($health['stuck_jobs'] > 5) {
            $alerts[] = "Queue alert: {$health['stuck_jobs']} stuck jobs detected";
        }

        if ($health['success_rate'] < 80 && ($health['stats']['failed'] > 0)) {
            $alerts[] = "Queue alert: Success rate is {$health['success_rate']}%";
        }

        if ($health['stats']['pending'] > 100) {
            $alerts[] = "Queue alert: {$health['stats']['pending']} pending jobs in queue";
        }

        if (!empty($alerts) && $telegramChatId) {
            $message = "🚨 Queue Health Alert:\n" . implode("\n", $alerts);
            QueueService::enqueueTelegram($pdo, $telegramChatId, $message);
        }

        return $alerts;
    }
}
