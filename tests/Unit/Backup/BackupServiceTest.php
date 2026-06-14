<?php

namespace Tests\Unit\Backup;

use PHPUnit\Framework\TestCase;
use Ecom\Backup\BackupService;
use Ecom\Backup\RestoreService;
use Ecom\Backup\RetentionService;

class BackupServiceTest extends TestCase
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
            scope TEXT DEFAULT 'global',
            selected_tables TEXT DEFAULT NULL,
            status TEXT DEFAULT 'pending',
            file_path TEXT DEFAULT NULL,
            file_size INTEGER DEFAULT 0,
            checksum TEXT DEFAULT NULL,
            storage_location TEXT DEFAULT 'local',
            s3_key TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
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
            approved_at DATETIME DEFAULT NULL,
            executed_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_backup_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            config_key TEXT NOT NULL UNIQUE,
            config_value TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_backup_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            backup_id INTEGER NOT NULL,
            store_id INTEGER DEFAULT NULL,
            log_level TEXT DEFAULT 'info',
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function testCreateBackupJob(): void
    {
        $id = BackupService::createJob($this->pdo, 'database', 'global');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateBackupJobWithStore(): void
    {
        $id = BackupService::createJob($this->pdo, 'files', 'store', 1);
        $job = BackupService::getJob($this->pdo, $id);
        $this->assertEquals(1, $job['store_id']);
        $this->assertEquals('files', $job['type']);
    }

    public function testGetBackupJobReturnsNull(): void
    {
        $job = BackupService::getJob($this->pdo, 999);
        $this->assertNull($job);
    }

    public function testUpdateBackupJob(): void
    {
        $id = BackupService::createJob($this->pdo, 'full', 'global');
        BackupService::updateJob($this->pdo, $id, ['status' => 'running']);
        $job = BackupService::getJob($this->pdo, $id);
        $this->assertEquals('running', $job['status']);
    }

    public function testBackupConfig(): void
    {
        BackupService::setConfig($this->pdo, 'retention_daily', '7');
        $this->assertEquals('7', BackupService::getConfig($this->pdo, 'retention_daily'));
    }

    public function testGetAllConfigs(): void
    {
        BackupService::setConfig($this->pdo, 'key_a', 'val_a');
        BackupService::setConfig($this->pdo, 'key_b', 'val_b');
        $configs = BackupService::getAllConfigs($this->pdo);
        $this->assertCount(2, $configs);
        $this->assertEquals('val_a', $configs['key_a']);
    }

    public function testBackupLog(): void
    {
        $id = BackupService::createJob($this->pdo, 'database', 'global');
        BackupService::writeLog($this->pdo, $id, 'Test log message', null, 'info');
        $logs = BackupService::getLogs($this->pdo, $id);
        $this->assertCount(1, $logs);
        $this->assertEquals('Test log message', $logs[0]['message']);
    }

    public function testTypeLabels(): void
    {
        $labels = BackupService::getTypeLabels();
        $this->assertArrayHasKey('database', $labels);
        $this->assertArrayHasKey('files', $labels);
    }

    public function testScopeLabels(): void
    {
        $labels = BackupService::getScopeLabels();
        $this->assertArrayHasKey('global', $labels);
    }

    public function testStatusLabels(): void
    {
        $labels = BackupService::getStatusLabels();
        $this->assertArrayHasKey('pending', $labels);
    }

    public function testRestoreCreateRequest(): void
    {
        $backupId = BackupService::createJob($this->pdo, 'database', 'global');
        $requestId = RestoreService::createRequest($this->pdo, $backupId, 1, null, 'Test restore');
        $this->assertGreaterThan(0, $requestId);
    }

    public function testRestoreApproveRejectFlow(): void
    {
        $backupId = BackupService::createJob($this->pdo, 'database', 'global');
        $requestId = RestoreService::createRequest($this->pdo, $backupId, 1);

        RestoreService::approve($this->pdo, $requestId, 2);
        $request = RestoreService::getRequest($this->pdo, $requestId);
        $this->assertEquals('approved', $request['status']);
        $this->assertEquals(2, $request['approved_by']);

        RestoreService::reject($this->pdo, $requestId);
        $request = RestoreService::getRequest($this->pdo, $requestId);
        $this->assertEquals('rejected', $request['status']);
    }

    public function testGetBackupHealth(): void
    {
        $health = BackupService::getHealth($this->pdo);
        $this->assertArrayHasKey('total_backups', $health);
        $this->assertArrayHasKey('successful', $health);
        $this->assertArrayHasKey('failed', $health);
        $this->assertEquals(100, $health['success_rate']);
    }

    public function testStorageSummary(): void
    {
        $summary = BackupService::getStorageSummary($this->pdo);
        $this->assertArrayHasKey('total_local', $summary);
        $this->assertArrayHasKey('total_s3', $summary);
    }

    public function testRetentionPolicy(): void
    {
        $policy = RetentionService::getPolicy($this->pdo);
        $this->assertArrayHasKey('daily_keep', $policy);
        $this->assertArrayHasKey('weekly_keep', $policy);
        $this->assertArrayHasKey('monthly_keep', $policy);
    }

    public function testBackupPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            BackupService::createJob($this->pdo, 'database', 'global');
        }
        $jobs = BackupService::getJobs($this->pdo, 1, 2);
        $this->assertCount(2, $jobs);
        $this->assertEquals(5, BackupService::getJobCount($this->pdo));
    }
}
