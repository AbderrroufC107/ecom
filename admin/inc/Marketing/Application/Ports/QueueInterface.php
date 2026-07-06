<?php
namespace Marketing\Application\Ports;

interface QueueInterface
{
    /**
     * Push a new job onto the queue.
     */
    public function push(string $queueName, array $payload, int $delay = 0, int $priority = 0): string;
    
    /**
     * Pop a job from the queue.
     */
    public function pop(string $queueName): ?array;
    
    /**
     * Mark a job as completed.
     */
    public function complete(string $jobId): void;
    
    /**
     * Mark a job as failed and move to DLQ if max retries exceeded.
     */
    public function fail(string $jobId, \Throwable $exception): void;
}
