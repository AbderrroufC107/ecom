<?php
namespace Omni;

use PDO;

/**
 * ReplayProtection — prevents duplicate Webhook event processing.
 *
 * Strategy:
 *  1. Generate a deterministic fingerprint from event fields.
 *  2. Check TTL-cached fingerprints in tbl_webhook_replay_cache.
 *  3. Validate event timestamp is within tolerance window.
 *  4. Mark events as processed after successful handling.
 */
class ReplayProtection
{
    private PDO $pdo;
    private static int $callCount = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate a sha256 fingerprint unique to this event.
     *
     * Components:
     *  - eventId   : Meta's mid / comment_id / unique identifier
     *  - senderId  : Sender's platform user ID
     *  - timestamp : floored to second (floor(ms/1000)) to handle sub-second jitter
     *  - payloadHash: sha256 of raw payload to catch replay with modified headers
     */
    public function generateFingerprint(
        string $eventId,
        string $senderId,
        int    $timestampMs,
        string $payload = ''
    ): string {
        $payloadHash = hash('sha256', $payload);
        $ts          = (int) floor($timestampMs / 1000);
        $raw         = "{$eventId}:{$senderId}:{$ts}:{$payloadHash}";
        return hash('sha256', $raw);
    }

    /**
     * Check if this fingerprint was already processed and is still within TTL.
     */
    public function isDuplicate(string $fingerprint, int $tenantId = 1): bool
    {
        // Probabilistic cleanup: every ~100 calls, expire old entries
        self::$callCount++;
        if (self::$callCount % 100 === 0) {
            $this->cleanExpired();
        }

        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            SELECT 1 FROM tbl_webhook_replay_cache
            WHERE fingerprint = ?
              AND (tenant_id = ? OR tenant_id = 0)
              AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$fingerprint, $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Mark this fingerprint as processed for TTL seconds.
     * Uses INSERT IGNORE to be race-condition safe.
     */
    public function markProcessed(
        string $fingerprint,
        string $eventId,
        int    $tenantId    = 1,
        int    $ttlSeconds  = 86400
    ): void {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            INSERT IGNORE INTO tbl_webhook_replay_cache
                (fingerprint, event_id, tenant_id, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$fingerprint, $eventId, $tenantId, $expiresAt]);
    }

    /**
     * Reject events older than $toleranceSeconds (default 5 minutes).
     * Meta can retry for up to 24h, so we rely on fingerprint for older dedup,
     * but fresh events outside the tolerance window are suspicious.
     *
     * @param int $timestampMs  Event timestamp in milliseconds
     */
    public function validateTimestamp(int $timestampMs, int $toleranceSeconds = 300): bool
    {
        $eventTime = (int) floor($timestampMs / 1000);
        $now       = time();
        $age       = abs($now - $eventTime);
        return $age <= $toleranceSeconds;
    }

    /**
     * Delete expired cache entries to keep the table small.
     */
    private function cleanExpired(): void
    {
        try {
            (new \SaaS\Repositories\DatabaseRepository($this->pdo))->executeCommand("DELETE FROM tbl_webhook_replay_cache WHERE expires_at < NOW()");
        } catch (\Exception $e) {
            error_log('ReplayProtection cleanup error: ' . $e->getMessage());
        }
    }
}
