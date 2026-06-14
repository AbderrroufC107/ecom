<?php

namespace Tests\Unit\Audit;

use PHPUnit\Framework\TestCase;
use Ecom\Audit\AuditService;
use Ecom\Audit\AuditRepository;

class AuditServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE tbl_stores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ''
        )");

        $this->pdo->exec("CREATE TABLE tbl_audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER DEFAULT NULL,
            staff_id INTEGER DEFAULT NULL,
            action TEXT NOT NULL,
            entity_type TEXT DEFAULT NULL,
            entity_id INTEGER DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip_address TEXT DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function testLogAction(): void
    {
        $id = AuditService::log($this->pdo, 1, 'store.created', 'store', 1, null, ['name' => 'Test']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testGetLogs(): void
    {
        AuditService::log($this->pdo, 1, 'action.a', 'store', 1);
        AuditService::log($this->pdo, 1, 'action.b', 'store', 2);
        $logs = AuditService::getLogs($this->pdo, 1);
        $this->assertCount(2, $logs);
    }

    public function testGetLogsFilteredByAction(): void
    {
        AuditService::log($this->pdo, 1, 'create', 'order', 1);
        AuditService::log($this->pdo, 1, 'update', 'order', 1);
        AuditService::log($this->pdo, 1, 'delete', 'order', 1);

        $logs = AuditService::getLogs($this->pdo, 1, 1, 50, 'create');
        $this->assertCount(1, $logs);
        $this->assertEquals('create', $logs[0]['action']);
    }

    public function testGetLogCount(): void
    {
        AuditService::log($this->pdo, 1, 'action.x', 'store', 1);
        AuditService::log($this->pdo, 1, 'action.y', 'store', 2);
        $count = AuditService::getLogCount($this->pdo, 1);
        $this->assertEquals(2, $count);
    }

    public function testGetRecentActions(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AuditService::log($this->pdo, 1, "action_{$i}", 'store', $i);
        }
        $recent = AuditService::getRecentActions($this->pdo, 1, 3);
        $this->assertCount(3, $recent);
    }

    public function testLogWithOldAndNewValues(): void
    {
        $id = AuditService::log($this->pdo, 1, 'store.updated', 'store', 1,
            ['name' => 'Old Name'], ['name' => 'New Name'], 5);

        $logs = AuditService::getLogs($this->pdo, 1);
        $this->assertCount(1, $logs);
        $this->assertEquals(5, $logs[0]['staff_id']);
    }

    public function testActionsByStoreGrouping(): void
    {
        AuditService::log($this->pdo, 1, 'create', 'product', 1);
        AuditService::log($this->pdo, 1, 'create', 'product', 2);
        AuditService::log($this->pdo, 1, 'delete', 'product', 3);

        $actions = AuditRepository::getActionsByStore($this->pdo, 1);
        $this->assertCount(2, $actions);
    }

    public function testTimeline(): void
    {
        AuditService::log($this->pdo, 1, 'order.placed', 'order', 1);
        AuditService::log($this->pdo, 1, 'order.placed', 'order', 2);
        $timeline = AuditRepository::getTimeline($this->pdo, 7);
        $this->assertNotEmpty($timeline);
    }

    public function testPagination(): void
    {
        for ($i = 0; $i < 10; $i++) {
            AuditService::log($this->pdo, 1, "action_{$i}", 'store', $i);
        }
        $page1 = AuditService::getLogs($this->pdo, 1, 1, 3);
        $page2 = AuditService::getLogs($this->pdo, 1, 2, 3);
        $this->assertCount(3, $page1);
        $this->assertCount(3, $page2);
        $this->assertNotEquals($page1[0]['id'], $page2[0]['id']);
    }

    public function testCleanup(): void
    {
        AuditService::log($this->pdo, 1, 'old', 'store', 1);
        $deleted = AuditRepository::cleanup($this->pdo, 0);
        $this->assertGreaterThanOrEqual(1, $deleted);
    }
}
