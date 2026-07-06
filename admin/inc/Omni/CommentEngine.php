<?php
namespace Omni;

use PDO;

/**
 * CommentEngine — Decision Engine for Facebook & Instagram Comments.
 *
 * Evaluates incoming COMMENT UnifiedMessages and decides:
 *   public_reply  → Reply publicly on the post/comment
 *   private_reply → Send a private DM to the commenter
 *   ignore        → Take no action
 *   escalate      → Flag for human review
 *
 * Rules are tenant-configurable via tbl_meta_comment_rules.
 */
class CommentEngine
{
    private PDO $pdo;
    private int $tenantId;
    private array $rules = [];
    private bool $rulesLoaded = false;

    public function __construct(PDO $pdo, int $tenantId)
    {
        $this->pdo      = $pdo;
        $this->tenantId = $tenantId;
    }

    /**
     * Main decision method.
     *
     * @return array{action: string, confidence: float, reason: string, reply_template: string|null, rule_id: int|null}
     */
    public function decide(UnifiedMessage $msg): array
    {
        $this->loadRules();
        $text  = $msg->text;
        $score = $this->getLeadScore($msg->platformUserId);

        foreach ($this->rules as $rule) {
            $matched = false;

            switch ($rule['condition_type']) {
                case 'keyword':
                    $matched = $this->evaluateKeyword($text, $rule['condition_value'] ?? '');
                    break;
                case 'lead_score':
                    $matched = $this->evaluateLeadScore($score, $rule['condition_value'] ?? '>0');
                    break;
                case 'intent':
                    $intent  = $this->evaluateIntent($text);
                    $matched = ($intent === ($rule['condition_value'] ?? ''));
                    break;
                case 'always':
                    $matched = true;
                    break;
            }

            if ($matched) {
                $this->logDecision($msg, $rule['action'], $rule['id'], $rule['condition_type']);
                return [
                    'action'         => $rule['action'],
                    'confidence'     => 0.92,
                    'reason'         => 'rule_match:' . $rule['condition_type'],
                    'reply_template' => $rule['reply_template'] ?? null,
                    'rule_id'        => (int) $rule['id'],
                ];
            }
        }

        // Default fallback
        $this->logDecision($msg, 'public_reply', null, 'default');
        return [
            'action'         => 'public_reply',
            'confidence'     => 0.5,
            'reason'         => 'default',
            'reply_template' => null,
            'rule_id'        => null,
        ];
    }

    // ─── Evaluation Helpers ──────────────────────────────────────────────────

    /**
     * Check if any comma-separated keyword appears in the text (case-insensitive).
     */
    public function evaluateKeyword(string $text, string $keywords): bool
    {
        if (empty($keywords)) return false;
        $list = array_map('trim', explode(',', $keywords));
        $text = mb_strtolower($text, 'UTF-8');
        foreach ($list as $kw) {
            if (!empty($kw) && mb_strpos($text, mb_strtolower($kw, 'UTF-8')) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Classify comment intent using keyword heuristics (Arabic + English).
     */
    public function evaluateIntent(string $text): string
    {
        $t = mb_strtolower($text, 'UTF-8');

        // Buying intent signals
        $buying = ['سعر', 'كم', 'بكام', 'كمية', 'اشتري', 'طلب', 'متاح', 'price', 'how much', 'buy', 'order', 'available', 'stock'];
        foreach ($buying as $kw) {
            if (mb_strpos($t, $kw) !== false) return 'buying_intent';
        }

        // Question / inquiry
        $question = ['?', '؟', 'كيف', 'متى', 'أين', 'هل', 'where', 'when', 'how', 'what'];
        foreach ($question as $kw) {
            if (mb_strpos($t, $kw) !== false) return 'question';
        }

        // Complaint
        $complaint = ['مشكلة', 'سيء', 'خطأ', 'وصل مكسور', 'غير راضي', 'complaint', 'broken', 'issue', 'problem', 'bad'];
        foreach ($complaint as $kw) {
            if (mb_strpos($t, $kw) !== false) return 'complaint';
        }

        // Praise
        $praise = ['ممتاز', 'رائع', 'شكرا', 'أحسنت', 'great', 'amazing', 'thank', 'love', 'excellent'];
        foreach ($praise as $kw) {
            if (mb_strpos($t, $kw) !== false) return 'praise';
        }

        // Spam signals
        $spam = ['follow me', 'check my', 'dm me', 'www.', 'http'];
        foreach ($spam as $kw) {
            if (mb_strpos($t, $kw) !== false) return 'spam';
        }

        return 'unknown';
    }

    /**
     * Evaluate a lead score against a condition string like ">50", "<=20", "=100".
     */
    public function evaluateLeadScore(int $score, string $condition): bool
    {
        if (preg_match('/^(>=|<=|>|<|=)\s*(\d+)$/', trim($condition), $m)) {
            $threshold = (int) $m[2];
            switch ($m[1]) {
                case '>':  return $score >  $threshold;
                case '<':  return $score <  $threshold;
                case '>=': return $score >= $threshold;
                case '<=': return $score <= $threshold;
                case '=':  return $score === $threshold;
            }
        }
        return false;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function loadRules(): void
    {
        if ($this->rulesLoaded) return;
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            SELECT * FROM tbl_meta_comment_rules
            WHERE tenant_id = ? AND is_active = 1
            ORDER BY priority ASC
        ");
        $stmt->execute([$this->tenantId]);
        $this->rules      = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->rulesLoaded = true;
    }

    private function getLeadScore(string $platformUserId): int
    {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            SELECT lead_score FROM tbl_omni_customers
            WHERE platform_user_id = ? AND tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$platformUserId, $this->tenantId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function logDecision(UnifiedMessage $msg, string $action, ?int $ruleId, string $reason): void
    {
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                INSERT INTO tbl_omni_events
                    (tenant_id, event_type, channel, status, metadata)
                VALUES (?, 'COMMENT_DECISION', ?, 'SUCCESS', ?)
            ");
            $stmt->execute([
                $this->tenantId,
                $msg->provider,
                json_encode([
                    'action'   => $action,
                    'rule_id'  => $ruleId,
                    'reason'   => $reason,
                    'msg_id'   => $msg->messageId,
                    'user'     => $msg->platformUserId,
                    'text_len' => strlen($msg->text),
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            error_log('CommentEngine logDecision error: ' . $e->getMessage());
        }
    }
}
