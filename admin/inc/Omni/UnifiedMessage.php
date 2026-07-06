<?php
namespace Omni;

/**
 * UnifiedMessage — v2 (Extended for Meta Production Integration)
 * Represents a normalized message from any channel/platform.
 */
class UnifiedMessage
{
    // ── Core Identity ────────────────────────────────────────────────────────
    public string  $messageId       = '';
    public string  $platformUserId  = '';
    public string  $provider        = '';        // meta, whatsapp, telegram, website
    public int     $channelId       = 0;
    public int     $tenantId        = 1;

    // ── Message Type & SubType ───────────────────────────────────────────────
    public string  $messageType     = 'TEXT';    // TEXT, MEDIA, COMMENT, LOCATION, LEAD, EVENT
    /** Precise sub-type for routing decisions */
    public string  $subType         = '';
    // MESSENGER | IG_DIRECT | IG_COMMENT | FB_COMMENT | STORY_MENTION
    // LEAD_AD   | MENTION   | REACTION   | DELIVERY   | READ | ECHO

    // ── Content ──────────────────────────────────────────────────────────────
    public string  $text            = '';
    public ?string $mediaUrl        = null;
    public string  $igMediaType     = '';        // IMAGE, VIDEO, REEL, STORY
    public array   $attachments     = [];        // [{type, url, payload}]
    public array   $quickReplies    = [];        // [{title, payload}]

    // ── Comment / Post Context ───────────────────────────────────────────────
    public ?string $commentId       = null;
    public ?string $parentCommentId = null;
    public ?string $postId          = null;
    public ?string $mediaId         = null;
    public ?string $storyId         = null;

    // ── Reaction ─────────────────────────────────────────────────────────────
    public string  $reactionType    = '';        // like, love, haha, wow, sad, angry

    // ── Delivery / Status Events ─────────────────────────────────────────────
    public string  $deliveryStatus  = '';        // delivered, read
    public bool    $isEcho          = false;
    public bool    $isDeleted       = false;

    // ── Lead Ad Data ─────────────────────────────────────────────────────────
    public string  $leadFormId      = '';
    public array   $leadFields      = [];        // [{name, values[]}]

    // ── Attribution / Ad Referral ────────────────────────────────────────────
    public ?string $adId            = null;
    public ?string $sourceAdId      = null;
    public ?string $sourceCampaignId= null;
    public ?string $sourceAdSetId   = null;
    public ?string $creativeId      = null;
    public string  $utmSource       = '';
    public string  $utmMedium       = '';
    public string  $utmCampaign     = '';
    public string  $utmContent      = '';
    public string  $referralUrl     = '';

    // ── Timestamps & Meta ────────────────────────────────────────────────────
    public string  $timestamp       = '';
    public ?string $metadata        = null;      // raw JSON for debugging

    // ────────────────────────────────────────────────────────────────────────

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        if (empty($this->timestamp)) {
            $this->timestamp = date('Y-m-d H:i:s');
        }
        // Alias adId → sourceAdId for backward compat
        if ($this->adId && !$this->sourceAdId) {
            $this->sourceAdId = $this->adId;
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function hasReferral(): bool
    {
        return !empty($this->sourceAdId) || !empty($this->sourceCampaignId);
    }

    public function isComment(): bool
    {
        return in_array($this->subType, ['IG_COMMENT', 'FB_COMMENT', 'STORY_MENTION', 'MENTION'], true);
    }

    public function isDirectMessage(): bool
    {
        return in_array($this->subType, ['MESSENGER', 'IG_DIRECT'], true);
    }
}
