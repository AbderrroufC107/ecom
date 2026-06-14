<?php

namespace Tests\Unit\Recovery;

use PHPUnit\Framework\TestCase;
use Ecom\Recovery\RecoveryService;
use Ecom\Recovery\RiskService;

class RiskServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE tbl_backup_job (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER DEFAULT NULL,
            type TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            file_path TEXT DEFAULT NULL,
            file_size INTEGER DEFAULT 0,
            storage_location TEXT DEFAULT 'local',
            s3_key TEXT DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_restore_request (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            backup_id INTEGER NOT NULL,
            store_id INTEGER DEFAULT NULL,
            requested_by INTEGER NOT NULL,
            status TEXT DEFAULT 'pending',
            approved_by INTEGER DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_queue_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT DEFAULT 'pending',
            started_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_failed_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            failed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function testBackupRiskLowWithRecentBackup(): void
    {
        $this->pdo->exec("INSERT INTO tbl_backup_job (type, status, storage_location, file_size, completed_at)
            VALUES ('database', 'completed', 's3', 1024, datetime('now', '-1 hour'))");
        $risk = RiskService::calculateBackupRisk($this->pdo);
        $this->assertEquals('low', $risk['level']);
    }

    public function testBackupRiskHighWithNoBackup(): void
    {
        $risk = RiskService::calculateBackupRisk($this->pdo);
        $this->assertEquals('high', $risk['level']);
        $this->assertEquals(100, $risk['score']);
    }

    public function testBackupRiskMediumWithLocalOnly(): void
    {
        $this->pdo->exec("INSERT INTO tbl_backup_job (type, status, storage_location, file_size, completed_at)
            VALUES ('database', 'completed', 'local', 2048, datetime('now', '-1 hour'))");
        $risk = RiskService::calculateBackupRisk($this->pdo);
        $this->assertGreaterThan(0, $risk['score']);
        $this->assertNotEmpty($risk['factors']);
    }

    public function testSystemRiskWithDatabaseCheck(): void
    {
        $this->pdo->exec("INSERT INTO tbl_queue_jobs (status) VALUES ('pending')");
        $risk = RiskService::calculateSystemRisk($this->pdo);
        $this->assertArrayHasKey('level', $risk);
        $this->assertArrayHasKey('score', $risk);
    }

    public function testCombinedRisk(): void
    {
        $combined = RiskService::getCombinedRisk($this->pdo);
        $this->assertArrayHasKey('overall_score', $combined);
        $this->assertArrayHasKey('overall_level', $combined);
        $this->assertArrayHasKey('backup', $combined);
        $this->assertArrayHasKey('system', $combined);
    }

    public function testSystemRequirementsCheck(): void
    {
        $result = RecoveryService::checkSystemRequirements();
        $this->assertArrayHasKey('all_passed', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('php_version', $result);
    }

    public function testQueueHealthCheck(): void
    {
        $health = RecoveryService::checkQueueHealth($this->pdo);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('pending', $health);
        $this->assertArrayHasKey('failed', $health);
    }
}
