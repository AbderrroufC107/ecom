<?php
namespace Omni;

use PDO;

/**
 * WebhookEventRouter — Factory/Dispatch-table pattern.
 *
 * Routes raw Meta Webhook payload entries into typed UnifiedMessage objects.
 * Supports: Messenger, IG Direct, IG/FB Comments, Story Mentions,
 *           Lead Ads, Mentions, Reactions, Delivery, Read, Echo.
 */
class WebhookEventRouter
{
    private PDO $pdo;
    private int $tenantId;
    private int $channelId;

    /** Dispatch table: detected event type → handler method name */
    private array $handlers = [
        'MESSENGER'      => 'handleMessenger',
        'IG_DIRECT'      => 'handleIgDirect',
        'IG_COMMENT'     => 'handleIgComment',
        'FB_COMMENT'     => 'handleFbComment',
        'STORY_MENTION'  => 'handleStoryMention',
        'LEAD_AD'        => 'handleLeadAd',
        'REACTION'       => 'handleReaction',
        'DELIVERY'       => 'handleDelivery',
        'READ'           => 'handleRead',
        'ECHO'           => 'handleEcho',
        'MENTION'        => 'handleMention',
        'PAGE_EVENT'     => 'handlePageEvent',
    ];

    public function __construct(PDO $pdo, int $tenantId, int $channelId)
    {
        $this->pdo       = $pdo;
        $this->tenantId  = $tenantId;
        $this->channelId = $channelId;
    }

    /**
     * Parse full Meta Webhook payload into an array of UnifiedMessage objects.
     */
    public function route(array $payload): array
    {
        $messages = [];
        $entries  = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            // ── Messaging events (Messenger / IG Direct) ──────────────────
            foreach ($entry['messaging'] ?? [] as $event) {
                $type = $this->detectMessagingEventType($event);
                if (!$type || !isset($this->handlers[$type])) continue;
                $msg = $this->{$this->handlers[$type]}($event, $entry);
                if ($msg) $messages[] = $msg;
            }

            // ── Change/feed events (Comments, Leads, Mentions, etc.) ──────
            foreach ($entry['changes'] ?? [] as $change) {
                $type = $this->detectChangeEventType($change, $payload['object'] ?? '');
                if (!$type || !isset($this->handlers[$type])) continue;
                $msg = $this->{$this->handlers[$type]}($change, $entry);
                if ($msg) $messages[] = $msg;
            }
        }

        return $messages;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Detection Methods
    // ════════════════════════════════════════════════════════════════════════

    private function detectMessagingEventType(array $event): ?string
    {
        if (isset($event['message']['is_echo']) && $event['message']['is_echo']) return 'ECHO';
        if (isset($event['read']))     return 'READ';
        if (isset($event['delivery'])) return 'DELIVERY';
        if (isset($event['reaction'])) return 'REACTION';
        if (isset($event['message'])) {
            // Distinguish IG Direct vs Messenger by page object context
            return 'MESSENGER'; // IG Direct handled at payload object level
        }
        if (isset($event['postback'])) return 'MESSENGER';
        return null;
    }

    private function detectChangeEventType(array $change, string $object): ?string
    {
        $field = $change['field'] ?? '';
        $val   = $change['value'] ?? [];

        if ($field === 'leadgen') return 'LEAD_AD';
        if ($field === 'feed' || $field === 'comments') {
            if (($val['item'] ?? '') === 'comment' && ($val['verb'] ?? '') === 'add') {
                return $object === 'instagram' ? 'IG_COMMENT' : 'FB_COMMENT';
            }
        }
        if ($field === 'story_insights' || ($val['item'] ?? '') === 'story') return 'STORY_MENTION';
        if ($field === 'mention') return 'MENTION';
        if ($field === 'messages' && $object === 'instagram') return 'IG_DIRECT';
        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Handlers
    // ════════════════════════════════════════════════════════════════════════

    private function handleMessenger(array $event, array $entry): ?UnifiedMessage
    {
        $senderId = $event['sender']['id'] ?? null;
        if (!$senderId) return null;

        $msg = new UnifiedMessage([
            'messageId'      => $event['message']['mid'] ?? uniqid('msg_'),
            'platformUserId' => $senderId,
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'MESSENGER',
            'messageType'    => 'TEXT',
            'text'           => $event['message']['text'] ?? ($event['postback']['title'] ?? ''),
            'timestamp'      => date('Y-m-d H:i:s', (int)(($event['timestamp'] ?? time() * 1000) / 1000)),
            'metadata'       => json_encode($event),
        ]);

        // Quick Replies
        if (isset($event['message']['quick_reply'])) {
            $msg->quickReplies = [['payload' => $event['message']['quick_reply']['payload'] ?? '']];
        }

        // Attachments
        if (isset($event['message']['attachments'])) {
            $msg->messageType  = 'MEDIA';
            $msg->attachments  = $event['message']['attachments'];
            $att = $event['message']['attachments'][0];
            if (($att['type'] ?? '') === 'location') {
                $msg->messageType = 'LOCATION';
                $lat  = $att['payload']['coordinates']['lat'] ?? '';
                $long = $att['payload']['coordinates']['long'] ?? '';
                $msg->text = "Location: {$lat},{$long}";
            } else {
                $msg->mediaUrl = $att['payload']['url'] ?? null;
            }
        }

        // Referral / Ad Attribution
        $referral = $event['message']['referral'] ?? $event['postback']['referral'] ?? null;
        if ($referral) {
            $msg->sourceAdId      = $referral['ad_id'] ?? null;
            $msg->sourceCampaignId= $referral['source'] ?? null;
            $msg->referralUrl     = $referral['ref'] ?? '';
        }

        return $msg;
    }

    private function handleIgDirect(array $change, array $entry): ?UnifiedMessage
    {
        $val      = $change['value'] ?? $change;
        $senderId = $val['sender_id'] ?? ($val['from']['id'] ?? null);
        if (!$senderId) return null;

        return new UnifiedMessage([
            'messageId'      => $val['message_id'] ?? uniqid('ig_dm_'),
            'platformUserId' => (string) $senderId,
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'IG_DIRECT',
            'messageType'    => 'TEXT',
            'text'           => $val['text'] ?? '',
            'mediaUrl'       => $val['attachments'][0]['payload']['url'] ?? null,
            'timestamp'      => date('Y-m-d H:i:s'),
            'metadata'       => json_encode($change),
        ]);
    }

    private function handleIgComment(array $change, array $entry): ?UnifiedMessage
    {
        $val = $change['value'] ?? [];
        $item = $val['item'] ?? '';
        if ($item !== 'comment') return null;
        if (in_array($val['verb'] ?? '', ['remove', 'hide', 'block'], true)) return null;

        $senderId = $val['from']['id'] ?? null;
        if (!$senderId) return null;

        return new UnifiedMessage([
            'messageId'       => $val['id'] ?? $val['comment_id'] ?? uniqid('igc_'),
            'platformUserId'  => (string) $senderId,
            'provider'        => 'meta',
            'channelId'       => $this->channelId,
            'tenantId'        => $this->tenantId,
            'subType'         => 'IG_COMMENT',
            'messageType'     => 'COMMENT',
            'text'            => $val['text'] ?? '',
            'commentId'       => $val['id'] ?? $val['comment_id'] ?? null,
            'parentCommentId' => $val['parent_id'] ?? null,
            'mediaId'         => $val['media']['id'] ?? $val['media_id'] ?? null,
            'postId'          => $val['media']['id'] ?? null,
            'timestamp'       => date('Y-m-d H:i:s', $val['created_time'] ?? time()),
            'metadata'        => json_encode($change),
        ]);
    }

    private function handleFbComment(array $change, array $entry): ?UnifiedMessage
    {
        $val = $change['value'] ?? [];
        if (($val['item'] ?? '') !== 'comment' || ($val['verb'] ?? '') !== 'add') return null;
        if (in_array($val['verb'] ?? '', ['remove', 'hide', 'block'], true)) return null;

        // Ignore self-comments (page commenting on its own post)
        if (isset($val['from']['id']) && $val['from']['id'] === ($entry['id'] ?? '')) return null;

        $msg = new UnifiedMessage([
            'messageId'      => $val['comment_id'] ?? uniqid('fbc_'),
            'platformUserId' => $val['from']['id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'FB_COMMENT',
            'messageType'    => 'COMMENT',
            'text'           => $val['message'] ?? '',
            'commentId'      => $val['comment_id'] ?? null,
            'postId'         => $val['post_id'] ?? null,
            'parentCommentId'=> $val['parent_id'] ?? null,
            'timestamp'      => date('Y-m-d H:i:s', $val['created_time'] ?? time()),
            'metadata'       => json_encode($change),
        ]);

        if (isset($val['photo'])) { $msg->messageType = 'MEDIA'; $msg->mediaUrl = $val['photo']; }
        if (isset($val['video'])) { $msg->messageType = 'MEDIA'; $msg->mediaUrl = $val['video']; }

        return $msg;
    }

    private function handleStoryMention(array $change, array $entry): ?UnifiedMessage
    {
        $val = $change['value'] ?? [];
        return new UnifiedMessage([
            'messageId'      => $val['story_id'] ?? uniqid('story_'),
            'platformUserId' => $val['sender_id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'STORY_MENTION',
            'messageType'    => 'MEDIA',
            'storyId'        => $val['story_id'] ?? null,
            'mediaUrl'       => $val['media_url'] ?? null,
            'igMediaType'    => 'STORY',
            'timestamp'      => date('Y-m-d H:i:s'),
            'metadata'       => json_encode($change),
        ]);
    }

    private function handleLeadAd(array $change, array $entry): ?UnifiedMessage
    {
        $val = $change['value'] ?? [];
        return new UnifiedMessage([
            'messageId'      => (string)($val['leadgen_id'] ?? uniqid('lead_')),
            'platformUserId' => 'lead_' . ($val['leadgen_id'] ?? ''),
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'LEAD_AD',
            'messageType'    => 'LEAD',
            'text'           => 'New Lead Ad submission',
            'leadFormId'     => (string)($val['form_id'] ?? ''),
            'sourceAdId'     => (string)($val['ad_id'] ?? ''),
            'sourceCampaignId' => (string)($val['ad_group_id'] ?? ''),
            'timestamp'      => date('Y-m-d H:i:s', $val['created_time'] ?? time()),
            'metadata'       => json_encode($change),
        ]);
    }

    private function handleReaction(array $event, array $entry): ?UnifiedMessage
    {
        $reaction = $event['reaction'] ?? [];
        return new UnifiedMessage([
            'messageId'      => $reaction['mid'] ?? uniqid('react_'),
            'platformUserId' => $event['sender']['id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'REACTION',
            'messageType'    => 'EVENT',
            'reactionType'   => $reaction['reaction'] ?? 'like',
            'text'           => 'Reaction: ' . ($reaction['reaction'] ?? 'like'),
            'timestamp'      => date('Y-m-d H:i:s', (int)(($event['timestamp'] ?? time() * 1000) / 1000)),
            'metadata'       => json_encode($event),
        ]);
    }

    private function handleDelivery(array $event, array $entry): ?UnifiedMessage
    {
        $delivery = $event['delivery'] ?? [];
        $mids = $delivery['mids'] ?? [];
        return new UnifiedMessage([
            'messageId'      => $mids[0] ?? uniqid('del_'),
            'platformUserId' => $event['sender']['id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'DELIVERY',
            'messageType'    => 'EVENT',
            'deliveryStatus' => 'delivered',
            'text'           => '',
            'timestamp'      => date('Y-m-d H:i:s', (int)(($delivery['watermark'] ?? time() * 1000) / 1000)),
            'metadata'       => json_encode($event),
        ]);
    }

    private function handleRead(array $event, array $entry): ?UnifiedMessage
    {
        $read = $event['read'] ?? [];
        return new UnifiedMessage([
            'messageId'      => uniqid('read_'),
            'platformUserId' => $event['sender']['id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'READ',
            'messageType'    => 'EVENT',
            'deliveryStatus' => 'read',
            'text'           => '',
            'timestamp'      => date('Y-m-d H:i:s', (int)(($read['watermark'] ?? time() * 1000) / 1000)),
            'metadata'       => json_encode($event),
        ]);
    }

    private function handleEcho(array $event, array $entry): ?UnifiedMessage
    {
        return new UnifiedMessage([
            'messageId'      => $event['message']['mid'] ?? uniqid('echo_'),
            'platformUserId' => $event['sender']['id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'ECHO',
            'messageType'    => 'EVENT',
            'isEcho'         => true,
            'text'           => $event['message']['text'] ?? '',
            'timestamp'      => date('Y-m-d H:i:s', (int)(($event['timestamp'] ?? time() * 1000) / 1000)),
            'metadata'       => json_encode($event),
        ]);
    }

    private function handleMention(array $change, array $entry): ?UnifiedMessage
    {
        $val = $change['value'] ?? [];
        return new UnifiedMessage([
            'messageId'      => $val['post_id'] ?? uniqid('mention_'),
            'platformUserId' => $val['sender_id'] ?? 'unknown',
            'provider'       => 'meta',
            'channelId'      => $this->channelId,
            'tenantId'       => $this->tenantId,
            'subType'        => 'MENTION',
            'messageType'    => 'COMMENT',
            'text'           => $val['message'] ?? '',
            'postId'         => $val['post_id'] ?? null,
            'timestamp'      => date('Y-m-d H:i:s'),
            'metadata'       => json_encode($change),
        ]);
    }

    private function handlePageEvent(array $change, array $entry): ?UnifiedMessage
    {
        return new UnifiedMessage([
            'messageId'   => uniqid('page_'),
            'provider'    => 'meta',
            'channelId'   => $this->channelId,
            'tenantId'    => $this->tenantId,
            'subType'     => 'PAGE_EVENT',
            'messageType' => 'EVENT',
            'text'        => json_encode($change['value'] ?? []),
            'timestamp'   => date('Y-m-d H:i:s'),
            'metadata'    => json_encode($change),
        ]);
    }
}
