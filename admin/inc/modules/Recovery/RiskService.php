<?php

namespace Ecom\Recovery;

use PDO;

class RiskService
{
    public static function calculateBackupRisk(PDO $pdo): array
    {
        $risk = [
            'level'      => 'low',
            'score'      => 0,
            'factors'    => [],
            'recommendations' => [],
        ];

        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job WHERE status = 'completed'
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $recent = (int) $stmt->fetchColumn();

            if ($recent === 0) {
                $risk['score'] += 30;
                $risk['factors'][] = 'No backup in the last 24 hours';
                $risk['recommendations'][] = 'Schedule automatic daily backups';
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job WHERE status = 'failed'
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $recentFailures = (int) $stmt->fetchColumn();

            if ($recentFailures > 3) {
                $risk['score'] += 20;
                $risk['factors'][] = "{$recentFailures} backup failures in the last 7 days";
                $risk['recommendations'][] = 'Investigate backup failures and fix underlying issues';
            } elseif ($recentFailures > 0) {
                $risk['score'] += 10;
                $risk['factors'][] = "{$recentFailures} recent backup failures";
            }

            $stmt = $pdo->query("SELECT COALESCE(SUM(file_size), 0) FROM tbl_backup_job WHERE status = 'completed'");
            $totalSize = (int) $stmt->fetchColumn();

            if ($totalSize > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job
                    WHERE status = 'completed' AND storage_location = 'local'");
                $local = (int) $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job
                    WHERE status = 'completed' AND storage_location = 's3'");
                $s3 = (int) $stmt->fetchColumn();

                if ($local > 0 && $s3 === 0) {
                    $risk['score'] += 15;
                    $risk['factors'][] = 'Backups are stored locally only (no off-site)';
                    $risk['recommendations'][] = 'Configure S3 storage for off-site backup copies';
                }

                if ($s3 > 0) {
                    $risk['score'] -= 10;
                }
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_restore_request WHERE status = 'executed'");
            $restoreTested = (int) $stmt->fetchColumn();

            if ($restoreTested === 0) {
                $risk['score'] += 10;
                $risk['factors'][] = 'No restore has ever been tested';
                $risk['recommendations'][] = 'Perform a test restore to validate backup integrity';
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_backup_job WHERE status = 'completed'");
            $totalBackups = (int) $stmt->fetchColumn();

            if ($totalBackups === 0) {
                $risk['score'] = 100;
                $risk['factors'][] = 'No backups exist';
                $risk['recommendations'][] = 'Create your first backup immediately';
            }
        } catch (\PDOException $e) {
            $risk['score'] = 100;
            $risk['factors'][] = 'Database error during risk calculation';
        }

        $risk['score'] = min(100, max(0, $risk['score']));

        if ($risk['score'] >= 70) {
            $risk['level'] = 'high';
        } elseif ($risk['score'] >= 40) {
            $risk['level'] = 'medium';
        } else {
            $risk['level'] = 'low';
        }

        return $risk;
    }

    public static function calculateSystemRisk(PDO $pdo): array
    {
        $risk = [
            'level'   => 'low',
            'score'   => 0,
            'factors' => [],
        ];

        $health = RecoveryService::performFullHealthCheck($pdo);

        if ($health['database']['status'] === 'critical') {
            $risk['score'] += 50;
            $risk['factors'][] = 'Database connection is critical';
        }

        if (!empty($health['queue']['issues'])) {
            $risk['score'] += 15;
            $risk['factors'][] = 'Queue system has issues';
        }

        if (!$health['system']['all_passed']) {
            $failCount = 0;
            foreach ($health['system']['checks'] as $check) {
                if ($check['status'] === 'failed') {
                    $failCount++;
                }
            }
            $risk['score'] += $failCount * 10;
            $risk['factors'][] = "{$failCount} system requirements not met";
        }

        if (isset($health['storage']['backup_dir']['writable']) && !$health['storage']['backup_dir']['writable']) {
            $risk['score'] += 20;
            $risk['factors'][] = 'Backup directory is not writable';
        }

        $risk['score'] = min(100, max(0, $risk['score']));

        if ($risk['score'] >= 70) {
            $risk['level'] = 'high';
        } elseif ($risk['score'] >= 40) {
            $risk['level'] = 'medium';
        }

        return $risk;
    }

    public static function getCombinedRisk(PDO $pdo): array
    {
        $backupRisk = self::calculateBackupRisk($pdo);
        $systemRisk = self::calculateSystemRisk($pdo);

        $combinedScore = (int) round(($backupRisk['score'] + $systemRisk['score']) / 2);
        $combinedLevel = 'low';

        if ($combinedScore >= 70) {
            $combinedLevel = 'high';
        } elseif ($combinedScore >= 40) {
            $combinedLevel = 'medium';
        }

        return [
            'overall_score' => $combinedScore,
            'overall_level' => $combinedLevel,
            'backup'        => $backupRisk,
            'system'        => $systemRisk,
        ];
    }
}
