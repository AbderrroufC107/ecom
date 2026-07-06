<?php
namespace Omni;

use PDO;
use Exception;

class EventLogger {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Logs an event to the Event Store.
     * 
     * @param string $eventType e.g., 'Incoming Webhook', 'AI Response', 'Meta Error'
     * @param array $data Associative array of optional fields
     * @return int The ID of the inserted event
     */
    public function log(string $eventType, array $data = []): int {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            INSERT INTO tbl_omni_events 
            (event_type, entity_type, entity_id, conversation_id, customer_id, channel, user_id, ai_agent_id, status, duration_ms, metadata) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $metadata = isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null;

        $stmt->execute([
            $eventType,
            $data['entity_type'] ?? null,
            $data['entity_id'] ?? null,
            $data['conversation_id'] ?? null,
            $data['customer_id'] ?? null,
            $data['channel'] ?? null,
            $data['user_id'] ?? null,
            $data['ai_agent_id'] ?? null,
            $data['status'] ?? 'SUCCESS',
            $data['duration_ms'] ?? 0,
            $metadata
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Replays a specific event (primarily for failed webhooks)
     */
    public function replay(int $eventId): bool {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT * FROM tbl_omni_events WHERE id = ? AND event_type = 'Incoming Webhook'");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            throw new Exception("Event not found or cannot be replayed.");
        }

        $metadata = json_decode($event['metadata'], true);
        if (!$metadata || !isset($metadata['payload'])) {
            throw new Exception("No payload found to replay.");
        }

        // Logic to dispatch the payload to the router again
        require_once __DIR__ . '/MessageRouter.php';
        $router = new MessageRouter($this->pdo);
        
        $startTime = microtime(true);
        try {
            // Process the payload again using the router
            // Note: We need the channel_id to map this.
            $channelId = $metadata['channel_id'] ?? null;
            if (!$channelId) throw new Exception("Missing channel_id in metadata.");
            
            $router->routeIncoming($metadata['payload'], $channelId);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            // Mark the old event as SUCCESS or insert a new one?
            // Usually we insert a new 'Replay' event and update the old one.
            (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_omni_events SET status = 'SUCCESS', duration_ms = ? WHERE id = ?")
                      ->execute([$duration, $eventId]);
                      
            $this->log('Webhook Replayed', [
                'entity_type' => 'event',
                'entity_id' => $eventId,
                'status' => 'SUCCESS',
                'duration_ms' => $duration
            ]);

            return true;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_omni_events SET status = 'FAILED', duration_ms = ? WHERE id = ?")
                      ->execute([$duration, $eventId]);
            throw $e;
        }
    }
}
