<?php
/**
 * NotificationProviderInterface Interface
 *
 * Contract for all notification delivery channels (Telegram, WhatsApp, Slack, etc.).
 */

declare(strict_types=1);

interface NotificationProviderInterface
{
    /**
     * Send a notification to an assigned employee.
     */
    public function sendTaskNotification(array $employee, array $order, string $templateKey, array $data = []): bool;

    /**
     * Send a notification to a manager.
     */
    public function sendAdminNotification(array $manager, string $templateKey, array $data = []): bool;

    /**
     * Broadcast a notification to multiple managers.
     */
    public function broadcastNotification(array $managers, string $templateKey, array $data = []): bool;
}
