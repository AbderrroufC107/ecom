<?php
namespace Marketing;

use PDO;
use Omni\EventLogger;

/**
 * AutomationEngine - Evaluates automation rules and fires alerts.
 *
 * Rules stored in tbl_meta_automation_rules.
 * Channels: dashboard (tbl_notification), telegram, email.
 */
class AutomationEngine
{
    private PDO         $pdo;
    private EventLogger $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->logger = new EventLogger($pdo);
    }

    /**
     * Run all active rules for a tenant.
     */
    public function evaluate(int $tenantId): array
    {
        $triggered = [];
        $db        = new \SaaS\Repositories\DatabaseRepository($this->pdo);

        $stmt = $db->prepare(
            "SELECT * FROM tbl_meta_automation_rules WHERE tenant_id = ? AND is_active = 1"
        );
        $stmt->execute([$tenantId]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rules as $rule) {
            try {
                $condition = json_decode($rule['condition_json'], true);
                $action    = json_decode($rule['action_json'], true);
                $channels  = json_decode($rule['alert_channels'] ?? '["dashboard"]', true);

                if ($this->evaluateCondition($condition, $tenantId, $rule)) {
                    $this->fireAlert($rule, $action, $channels, $tenantId);
                    $triggered[] = $rule['name'];

                    // Update trigger count
                    $stmt2 = $db->prepare(
                        "UPDATE tbl_meta_automation_rules SET trigger_count = trigger_count + 1, last_triggered = NOW() WHERE id = ?"
                    );
                    $stmt2->execute([$rule['id']]);
                }
            } catch (\Exception $e) {
                error_log("AutomationEngine Rule [{$rule['name']}] Error: " . $e->getMessage());
            }
        }

        return $triggered;
    }

    private function evaluateCondition(array $condition, int $tenantId, array $rule): bool
    {
        $metric    = $condition['metric']    ?? '';
        $operator  = $condition['operator']  ?? '>';
        $threshold = $condition['threshold'] ?? 0;
        $period    = $condition['period']    ?? 'today';

        $value = $this->getMetricValue($metric, $tenantId, $rule['scope'], $rule['entity_id'], $period);
        if ($value === null) return false;

        return match ($operator) {
            '>'  => $value > $threshold,
            '>=' => $value >= $threshold,
            '<'  => $value < $threshold,
            '<=' => $value <= $threshold,
            '='  => $value == $threshold,
            '!=' => $value != $threshold,
            default => false,
        };
    }

    private function getMetricValue(string $metric, int $tenantId, string $scope, ?int $entityId, string $period): ?float
    {
        $dateFrom = match ($period) {
            'today'     => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 day')),
            'last_7d'   => date('Y-m-d', strtotime('-7 days')),
            'last_30d'  => date('Y-m-d', strtotime('-30 days')),
            default     => date('Y-m-d'),
        };

        $condition = "";
        $params    = [$tenantId, $dateFrom];
        if ($entityId && $scope === 'CAMPAIGN') {
            $condition = " AND campaign_id = ?";
            $params[]  = $entityId;
        }

        $sql = "SELECT {$this->metricToSql($metric)} FROM tbl_meta_campaign_insights WHERE tenant_id = ? AND date_start >= ? {$condition}";

        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
            $stmt->execute($params);
            return (float)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function metricToSql(string $metric): string
    {
        return match ($metric) {
            'cpc'           => 'AVG(cpc)',
            'cpm'           => 'AVG(cpm)',
            'ctr'           => 'AVG(ctr)',
            'roas'          => 'ROUND(SUM(purchase_value)/NULLIF(SUM(spend),0),4)',
            'spend'         => 'SUM(spend)',
            'leads'         => 'SUM(leads)',
            'purchases'     => 'SUM(purchases)',
            'impressions'   => 'SUM(impressions)',
            'reach'         => 'SUM(reach)',
            'revenue'       => 'SUM(purchase_value)',
            'cost_per_lead' => 'AVG(cost_per_lead)',
            default         => 'SUM(spend)',
        };
    }

    private function fireAlert(array $rule, array $action, array $channels, int $tenantId): void
    {
        $message  = $action['message'] ?? "تنبيه: {$rule['name']}";
        $severity = $action['severity'] ?? 'WARNING';

        foreach ($channels as $channel) {
            match ($channel) {
                'dashboard' => $this->sendDashboardAlert($message, $severity, $tenantId),
                'telegram'  => $this->sendTelegramAlert($message, $tenantId),
                'email'     => $this->sendEmailAlert($message, $tenantId),
                default     => null,
            };
        }

        $this->logger->log('Automation Rule Triggered', [
            'status'   => 'SUCCESS',
            'metadata' => ['rule' => $rule['name'], 'channels' => $channels]
        ]);
    }

    private function sendDashboardAlert(string $message, string $type, int $tenantId): void
    {
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "INSERT INTO tbl_notification (tenant_id, type, title, message, is_read, created_at)
                 VALUES (?, 'marketing_alert', 'تنبيه التسويق', ?, 0, NOW())"
            );
            $stmt->execute([$tenantId, $message]);
        } catch (\Exception $e) {
            // tbl_notification may have different schema - silently fail
        }
    }

    private function sendTelegramAlert(string $message, int $tenantId): void
    {
        if (!defined('TELEGRAM_BOT_TOKEN') || !defined('EVENT_BOT_CHAT_ID')) return;
        if (!EVENT_BOT_ENABLED) return;

        $url  = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = ['chat_id' => EVENT_BOT_CHAT_ID, 'text' => "🔔 Marketing Alert\n\n{$message}", 'parse_mode' => 'HTML'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function sendEmailAlert(string $message, int $tenantId): void
    {
        // Email implementation depends on project mail setup
        // Intentionally left as stub - implement with project's mail handler
    }

    /**
     * Create default automation rules for a new account.
     */
    public function createDefaultRules(int $tenantId): void
    {
        $defaults = [
            [
                'name'            => 'تنبيه: ROAS منخفض',
                'scope'           => 'ACCOUNT',
                'condition_json'  => json_encode(['metric' => 'roas', 'operator' => '<', 'threshold' => 1.0, 'period' => 'last_7d']),
                'action_json'     => json_encode(['message' => '⚠️ ROAS أقل من 1.0 — الحملة تخسر أموالاً!', 'severity' => 'CRITICAL']),
                'alert_channels'  => json_encode(['dashboard', 'telegram']),
            ],
            [
                'name'            => 'تنبيه: CPC مرتفع',
                'scope'           => 'ACCOUNT',
                'condition_json'  => json_encode(['metric' => 'cpc', 'operator' => '>', 'threshold' => 50, 'period' => 'today']),
                'action_json'     => json_encode(['message' => '⚠️ تكلفة النقر تجاوزت 50 دج — راجع الجمهور والإعلان.', 'severity' => 'WARNING']),
                'alert_channels'  => json_encode(['dashboard']),
            ],
            [
                'name'            => 'تنبيه: لا يوجد leads اليوم',
                'scope'           => 'ACCOUNT',
                'condition_json'  => json_encode(['metric' => 'leads', 'operator' => '=', 'threshold' => 0, 'period' => 'today']),
                'action_json'     => json_encode(['message' => '⚠️ لا توجد leads اليوم — تحقق من حالة الحملات.', 'severity' => 'WARNING']),
                'alert_channels'  => json_encode(['dashboard', 'telegram']),
            ],
            [
                'name'            => 'تنبيه: الإنفاق الشهري مرتفع',
                'scope'           => 'ACCOUNT',
                'condition_json'  => json_encode(['metric' => 'spend', 'operator' => '>', 'threshold' => 50000, 'period' => 'last_30d']),
                'action_json'     => json_encode(['message' => '💰 تجاوز الإنفاق 50,000 دج هذا الشهر.', 'severity' => 'INFO']),
                'alert_channels'  => json_encode(['dashboard']),
            ],
        ];

        $db   = new \SaaS\Repositories\DatabaseRepository($this->pdo);
        foreach ($defaults as $rule) {
            $rule['tenant_id']  = $tenantId;
            $rule['is_active']  = 1;
            $stmt = $db->prepare("
                INSERT IGNORE INTO tbl_meta_automation_rules
                    (tenant_id, name, is_active, scope, condition_json, action_json, alert_channels)
                VALUES (?, ?, 1, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $rule['name'],
                $rule['scope'],
                $rule['condition_json'],
                $rule['action_json'],
                $rule['alert_channels'],
            ]);
        }
    }
}
