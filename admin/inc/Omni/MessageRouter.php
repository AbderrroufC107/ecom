<?php
namespace Omni;

use PDO;
use Omni\EventLogger;

class MessageRouter {
    private $pdo;
    private $eventLogger;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->eventLogger = new EventLogger($pdo);
    }

    public function routeIncoming(UnifiedMessage $msg) {
        // Anti-spam & Auto-ignore checks for comments
        if ($msg->messageType === 'COMMENT') {
            $metadata = json_decode($msg->metadata, true);
            // Example: verb remove/hide -> ignored
            if (isset($metadata['verb']) && in_array($metadata['verb'], ['remove', 'hide'])) {
                $this->eventLogger->log('Message Ignored', ['status' => 'IGNORED', 'metadata' => ['reason' => 'hidden/deleted comment']]);
                return;
            }
        }

        $this->pdo->beginTransaction();

        try {
            // 1. Resolve Customer Identity
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT customer_id FROM tbl_omni_customer_identities WHERE provider = ? AND platform_user_id = ? FOR UPDATE");
            $stmt->execute([$msg->provider, $msg->platformUserId]);
            $customerId = $stmt->fetchColumn();

            if (!$customerId) {
                // Create new customer
                $stmtC = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("INSERT INTO tbl_omni_customers (journey_stage) VALUES ('NEW')");
                $stmtC->execute();
                $customerId = $this->pdo->lastInsertId();

                $stmtI = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("INSERT INTO tbl_omni_customer_identities (customer_id, provider, platform_user_id) VALUES (?, ?, ?)");
                $stmtI->execute([$customerId, $msg->provider, $msg->platformUserId]);
                
                $this->eventLogger->log('Customer Created', ['customer_id' => $customerId, 'channel' => $msg->provider]);
            }

            // 2. Resolve Conversation
            // Find active conversation for this customer on this channel
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                SELECT id, ai_status, assigned_agent 
                FROM tbl_omni_conversations 
                WHERE customer_id = ? AND current_channel_id = ? AND current_status = 'OPEN' 
                FOR UPDATE
            ");
            $stmt->execute([$customerId, $msg->channelId]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conv) {
                $stmtConv = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                    INSERT INTO tbl_omni_conversations 
                    (customer_id, current_channel_id, campaign_id, ad_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmtConv->execute([$customerId, $msg->channelId, $msg->campaignId, $msg->adId]);
                $conversationId = $this->pdo->lastInsertId();
                $aiStatus = 'ACTIVE';
                
                $this->eventLogger->log('Conversation Created', ['conversation_id' => $conversationId, 'customer_id' => $customerId, 'channel' => $msg->provider]);
            } else {
                $conversationId = $conv['id'];
                $aiStatus = $conv['ai_status'];
                
                // Update conversation activity
                $stmtU = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_omni_conversations SET last_activity = NOW() WHERE id = ?");
                $stmtU->execute([$conversationId]);
            }

            // 3. Save to Timeline
            // Avoid duplicates
            $stmtDup = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT id FROM tbl_omni_timeline WHERE conversation_id = ? AND metadata LIKE ?");
            $metaCheck = '%"' . $msg->messageId . '"%';
            $stmtDup->execute([$conversationId, $metaCheck]);
            
            if (!$stmtDup->fetchColumn()) {
                $stmtT = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                    INSERT INTO tbl_omni_timeline 
                    (conversation_id, type, sender_type, sender_id, content, post_id, comment_id, reply_to_id, metadata)
                    VALUES (?, ?, 'CUSTOMER', ?, ?, ?, ?, ?, ?)
                ");
                $stmtT->execute([
                    $conversationId,
                    $msg->messageType, // 'TEXT', 'COMMENT', 'MEDIA'
                    $msg->platformUserId,
                    $msg->text,
                    $msg->postId,
                    $msg->commentId,
                    $msg->replyToId,
                    $msg->metadata
                ]);
            } else {
                // It's a duplicate
                $this->pdo->rollBack();
                $this->eventLogger->log('Message Ignored', ['status' => 'IGNORED', 'metadata' => ['reason' => 'duplicate', 'msg_id' => $msg->messageId]]);
                return;
            }

            $this->pdo->commit();

            // 4. Trigger AI Decision Engine if ACTIVE
            if ($aiStatus === 'ACTIVE') {
                $this->dispatchToAI($conversationId, $msg);
            } else {
                $this->eventLogger->log('Human Handoff Maintained', ['conversation_id' => $conversationId, 'metadata' => ['reason' => 'ai_status is not active']]);
            }

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->eventLogger->log('Routing Error', ['status' => 'FAILED', 'metadata' => ['error' => $e->getMessage()]]);
            error_log("MessageRouter Error: " . $e->getMessage());
        }
    }

    private function dispatchToAI($conversationId, UnifiedMessage $msg) {
        // Comment Decision Engine
        $instruction = 'REPLY_NORMAL';
        if ($msg->messageType === 'COMMENT') {
            $privateKeywords = ['سعر', 'بكم', 'توصيل', 'شحن', 'طلب', 'رقم', 'عنوان', 'دفع', 'خصم', 'كيف اطلب', 'كم السعر'];
            $wantsPrivate = false;
            foreach ($privateKeywords as $kw) {
                if (mb_strpos($msg->text, $kw) !== false) {
                    $wantsPrivate = true;
                    break;
                }
            }
            
            if ($wantsPrivate) {
                $instruction = 'PRIVATE_REPLY_SALES_OPENER';
                $this->eventLogger->log('Smart Private Message Triggered', ['conversation_id' => $conversationId, 'metadata' => ['trigger' => 'keyword match in comment']]);
            } else {
                $instruction = 'PUBLIC_REPLY_SHORT';
            }
        }

        // Prefer the n8n Meta Sales Agent (handles Messenger/Instagram across every connected
        // page using real product knowledge); fall back to the local AI task queue so a reply
        // still goes out if n8n is unreachable or not yet configured.
        if ($this->dispatchToN8nSalesAgent($conversationId, $msg, $instruction)) {
            return;
        }

        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            INSERT INTO tbl_ai_tasks (task_type, entity_type, entity_id, priority, payload, status)
            VALUES ('omni_reply', 'conversation', ?, 'HIGH', ?, 'PENDING')
        ");

        $payload = json_encode([
            'message' => $msg->text,
            'type' => $msg->messageType,
            'channel_id' => $msg->channelId,
            'platform_user_id' => $msg->platformUserId,
            'instruction' => $instruction,
            'comment_id' => $msg->commentId
        ], JSON_UNESCAPED_UNICODE);

        $stmt->execute([$conversationId, $payload]);
    }

    private function dispatchToN8nSalesAgent($conversationId, UnifiedMessage $msg, string $instruction): bool
    {
        try {
            require_once dirname(__DIR__) . '/integration/N8nManager.php';

            $stmtHist = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT sender_type, content FROM tbl_omni_timeline WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 10"
            );
            $stmtHist->execute([$conversationId]);
            $history = array_map(
                static fn($row) => ['role' => $row['sender_type'] === 'AI' ? 'assistant' : 'user', 'content' => $row['content']],
                $stmtHist->fetchAll(PDO::FETCH_ASSOC)
            );

            $n8n = new \Integration\N8nManager($this->pdo);
            $result = $n8n->callWebhook('sales_agent', [
                'conversation_id' => $conversationId,
                'message' => $msg->text,
                'type' => $msg->messageType,
                'channel_id' => $msg->channelId,
                'platform_user_id' => $msg->platformUserId,
                'instruction' => $instruction,
                'comment_id' => $msg->commentId,
                'conversation_history' => $history,
            ], 10);

            return (bool) ($result['success'] ?? false);
        } catch (\Exception $e) {
            error_log('MessageRouter::dispatchToN8nSalesAgent failed, falling back to local AI queue: ' . $e->getMessage());
            return false;
        }
    }
}
