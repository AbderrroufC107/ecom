<?php

namespace Ecom\Api;

use PDO;

class RateLimitService
{
    private static array $planLimits = [
        'free'     => ['requests_per_hour' => 100, 'requests_per_day' => 1000],
        'starter'  => ['requests_per_hour' => 500, 'requests_per_day' => 5000],
        'business' => ['requests_per_hour' => 2000, 'requests_per_day' => 50000],
        'enterprise' => ['requests_per_hour' => 10000, 'requests_per_day' => 250000],
    ];

    public static function setPlanLimit(string $planSlug, int $perHour, int $perDay): void
    {
        self::$planLimits[$planSlug] = [
            'requests_per_hour' => $perHour,
            'requests_per_day'  => $perDay,
        ];
    }

    public static function getLimit(PDO $pdo, int $storeId): array
    {
        $stmt = $pdo->prepare("SELECT p.slug AS plan_slug
            FROM tbl_stores s LEFT JOIN tbl_plans p ON s.plan_id = p.id WHERE s.id = ?");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();

        $planSlug = $row['plan_slug'] ?? 'free';
        return self::$planLimits[$planSlug] ?? self::$planLimits['free'];
    }

    public static function isRateLimited(PDO $pdo, int $storeId): bool
    {
        $limits = self::getLimit($pdo, $storeId);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_api_logs
            WHERE store_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$storeId]);
        $hourly = (int) $stmt->fetchColumn();

        if ($hourly >= $limits['requests_per_hour']) {
            return true;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_api_logs
            WHERE store_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute([$storeId]);
        $daily = (int) $stmt->fetchColumn();

        if ($daily >= $limits['requests_per_day']) {
            return true;
        }

        return false;
    }

    public static function getRemaining(PDO $pdo, int $storeId): array
    {
        $limits = self::getLimit($pdo, $storeId);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_api_logs
            WHERE store_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$storeId]);
        $hourly = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_api_logs
            WHERE store_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute([$storeId]);
        $daily = (int) $stmt->fetchColumn();

        return [
            'hourly_limit'     => $limits['requests_per_hour'],
            'hourly_used'      => $hourly,
            'hourly_remaining' => max(0, $limits['requests_per_hour'] - $hourly),
            'daily_limit'      => $limits['requests_per_day'],
            'daily_used'       => $daily,
            'daily_remaining'  => max(0, $limits['requests_per_day'] - $daily),
        ];
    }

    public static function resetCounters(PDO $pdo, int $storeId): void
    {
        $stmt = $pdo->prepare("DELETE FROM tbl_api_logs WHERE store_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute([$storeId]);
    }
}
