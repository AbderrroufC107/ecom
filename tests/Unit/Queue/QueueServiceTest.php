<?php

namespace Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use Ecom\Queue\QueueService;
use Ecom\Queue\QueueWorker;
use Ecom\Queue\QueueHealth;

class QueueServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        // Use in-memory SQLite for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE tbl_queue_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER DEFAULT NULL,
            type TEXT NOT NULL,
            payload TEXT DEFAULT NULL,
            priority TEXT DEFAULT 'normal',
            status TEXT DEFAULT 'pending',
            attempts INTEGER DEFAULT 0,
            max_attempts INTEGER DEFAULT 3,
            scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_failed_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            original_job_id INTEGER DEFAULT NULL,
            store_id INTEGER DEFAULT NULL,
            type TEXT NOT NULL,
            payload TEXT DEFAULT NULL,
            priority TEXT DEFAULT 'normal',
            attempts INTEGER DEFAULT 0,
            max_attempts INTEGER DEFAULT 3,
            error_message TEXT DEFAULT NULL,
            failed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function testEnqueueJob(): void
    {
        $id = QueueService::enqueue($this->pdo, 'test_job', 'high', ['key' => 'value'], 1);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testEnqueueDefaultPriority(): void
    {
        $id = QueueService::enqueue($this->pdo, 'default_job');
        $job = QueueService::getJob($this->pdo, $id);
        $this->assertEquals('normal', $job['priority']);
    }

    public function testGetQueueStats(): void
    {
        QueueService::enqueue($this->pdo, 'job_a');
        QueueService::enqueue($this->pdo, 'job_b');
        $stats = QueueService::getStats($this->pdo);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertEquals(2, $stats['pending']);
    }

    public function testUpdateJobStatus(): void
    {
        $id = QueueService::enqueue($this->pdo, 'status_test');
        QueueService::updateStatus($this->pdo, $id, 'completed');
        $job = QueueService::getJob($this->pdo, $id);
        $this->assertEquals('completed', $job['status']);
    }

    public function testDequeueJob(): void
    {
        QueueService::enqueue($this->pdo, 'worker_job');
        $job = QueueService::dequeue($this->pdo, 'worker_job');
        $this->assertNotNull($job);
        $this->assertEquals('processing', $job['status']);
    }

    public function testDequeueEmptyQueue(): void
    {
        $job = QueueService::dequeue($this->pdo, 'nonexistent');
        $this->assertNull($job);
    }

    public function testCancelJob(): void
    {
        $id = QueueService::enqueue($this->pdo, 'cancel_me');
        QueueService::cancel($this->pdo, $id);
        $job = QueueService::getJob($this->pdo, $id);
        $this->assertEquals('cancelled', $job['status']);
    }

    public function testRetryJob(): void
    {
        $id = QueueService::enqueue($this->pdo, 'retry_me');
        QueueService::updateStatus($this->pdo, $id, 'failed', 'test error');
        QueueService::retry($this->pdo, $id);
        $job = QueueService::getJob($this->pdo, $id);
        $this->assertEquals('pending', $job['status']);
        $this->assertNull($job['error_message']);
    }

    public function testRequeueJobWithDelay(): void
    {
        $id = QueueService::enqueue($this->pdo, 'requeue_test');
        QueueService::requeue($this->pdo, $id, 5);
        $job = QueueService::getJob($this->pdo, $id);
        $this->assertEquals('pending', $job['status']);
    }

    public function testBulkEnqueue(): void
    {
        $jobs = [
            ['type' => 'bulk_a', 'priority' => 'high'],
            ['type' => 'bulk_b', 'priority' => 'normal'],
            ['type' => 'bulk_c', 'priority' => 'low', 'store_id' => 1],
        ];
        $count = QueueService::bulkEnqueue($this->pdo, $jobs);
        $this->assertEquals(3, $count);
    }

    public function testMoveToFailed(): void
    {
        $id = QueueService::enqueue($this->pdo, 'fail_test');
        QueueService::moveToFailed($this->pdo, $id, 'fail_test', ['data' => 1], 'normal', 3, 3, 'Failed intentionally');
        $this->assertEquals(1, QueueService::getFailedJobCount($this->pdo));
    }

    public function testFailedJobPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            QueueService::moveToFailed($this->pdo, $i, "fail_{$i}", null, 'normal', 1, 3, 'error');
        }
        $failed = QueueService::getFailedJobs($this->pdo, 1, 2);
        $this->assertCount(2, $failed);
    }

    public function testJobTypesList(): void
    {
        $types = QueueWorker::getJobTypes();
        $this->assertIsArray($types);
        $this->assertContains('telegram_send', $types);
        $this->assertContains('backup_database', $types);
    }

    public function testPriorityList(): void
    {
        $priorities = QueueWorker::getPriorities();
        $this->assertCount(3, $priorities);
        $this->assertContains('high', $priorities);
    }

    public function testGetBackoffMinutes(): void
    {
        $this->assertEquals(0, QueueWorker::getBackoffMinutes(0));
        $this->assertEquals(5, QueueWorker::getBackoffMinutes(1));
        $this->assertEquals(120, QueueWorker::getBackoffMinutes(10));
    }

    public function testQueueHealth(): void
    {
        $health = QueueHealth::getHealth($this->pdo);
        $this->assertArrayHasKey('stats', $health);
        $this->assertArrayHasKey('success_rate', $health);
        $this->assertEquals(0, $health['stuck_jobs']);
    }

    public function testByTypeOverTime(): void
    {
        QueueService::enqueue($this->pdo, 'type_a');
        QueueService::enqueue($this->pdo, 'type_b');
        $data = QueueHealth::getByTypeOverTime($this->pdo, 24);
        $this->assertArrayHasKey('type_a', $data);
        $this->assertArrayHasKey('type_b', $data);
    }

    public function testCleanupStuckJobs(): void
    {
        $id = QueueService::enqueue($this->pdo, 'stuck_test');
        $this->pdo->exec("UPDATE tbl_queue_jobs SET status = 'processing', started_at = datetime('now', '-1 hour') WHERE id = {$id}");
        $cleaned = QueueWorker::cleanupStuck($this->pdo, 30);
        $this->assertEquals(1, $cleaned);
    }
}
