<?php

namespace Ecom\Backup;

use PDO;

class RetentionService
{
    private static array $defaultPolicy = [
        'daily_keep'   => 7,
        'weekly_keep'  => 4,
        'monthly_keep' => 12,
    ];

    public static function getPolicy(PDO $pdo): array
    { global $dbRepo;
        $config = BackupService::getAllConfigs($pdo);
        return [
            'daily_keep'   => (int) ($config['retention_daily'] ?? self::$defaultPolicy['daily_keep']),
            'weekly_keep'  => (int) ($config['retention_weekly'] ?? self::$defaultPolicy['weekly_keep']),
            'monthly_keep' => (int) ($config['retention_monthly'] ?? self::$defaultPolicy['monthly_keep']),
        ];
    }

    public static function apply(PDO $pdo): int
    { global $dbRepo;
        $policy = self::getPolicy($pdo);
        $deleted = 0;

        $deleted += self::pruneDaily($pdo, $policy['daily_keep']);
        $deleted += self::pruneWeekly($pdo, $policy['weekly_keep']);
        $deleted += self::pruneMonthly($pdo, $policy['monthly_keep']);

        return $deleted;
    }

    private static function pruneDaily(PDO $pdo, int $keep): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("
            DELETE FROM tbl_backup_job WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM tbl_backup_job
                    WHERE status = 'completed' AND DATE(completed_at) = CURDATE()
                    ORDER BY completed_at DESC
                    LIMIT 18446744073709551615 OFFSET ?
                ) AS tmp
            )");
        $stmt->execute([$keep]);
        $count = $stmt->rowCount();

        if ($count > 0) {
            $stmt = $dbRepo->prepare("DELETE FROM tbl_backup_job WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM tbl_backup_job
                    WHERE status = 'completed' AND DATE(completed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    ORDER BY completed_at DESC
                    LIMIT 18446744073709551615 OFFSET ?
                ) AS tmp
            )");
            $stmt->execute([$keep]);
            $count += $stmt->rowCount();
        }

        return $count;
    }

    private static function pruneWeekly(PDO $pdo, int $keep): int
    { global $dbRepo;
        $count = 0;
        for ($w = 0; $w < 4; $w++) {
            $weekStart = date('Y-m-d', strtotime("-{$w} week monday"));
            $weekEnd = date('Y-m-d', strtotime("-{$w} week sunday"));
            $stmt = $dbRepo->prepare("
                DELETE FROM tbl_backup_job WHERE id IN (
                    SELECT id FROM (
                        SELECT id FROM tbl_backup_job
                        WHERE status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?
                        ORDER BY completed_at DESC
                        LIMIT 18446744073709551615 OFFSET ?
                    ) AS tmp
                )");
            $stmt->execute([$weekStart, $weekEnd, $keep]);
            $count += $stmt->rowCount();
        }
        return $count;
    }

    private static function pruneMonthly(PDO $pdo, int $keep): int
    { global $dbRepo;
        $count = 0;
        for ($m = 0; $m < 12; $m++) {
            $monthStart = date('Y-m-01', strtotime("-{$m} month"));
            $monthEnd = date('Y-m-t', strtotime("-{$m} month"));
            $stmt = $dbRepo->prepare("
                DELETE FROM tbl_backup_job WHERE id IN (
                    SELECT id FROM (
                        SELECT id FROM tbl_backup_job
                        WHERE status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?
                        ORDER BY completed_at DESC
                        LIMIT 18446744073709551615 OFFSET ?
                    ) AS tmp
                )");
            $stmt->execute([$monthStart, $monthEnd, $keep]);
            $count += $stmt->rowCount();
        }
        return $count;
    }
}
