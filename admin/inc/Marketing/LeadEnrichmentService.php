<?php
namespace Marketing;

use PDO;
use Exception;
use Marketing\MarketingApiClient;
use Omni\EventLogger;

/**
 * LeadEnrichmentService
 *
 * When a Lead arrives from Meta Lead Ads:
 * 1. Fetch full lead data from Meta Graph API
 * 2. Create/update customer in tbl_omni_customers + tbl_omni_customer_identities
 * 3. Link: campaign, adset, ad, creative, lead_form, product
 * 4. Set journey_stage = 'LEAD', lead_source, lead_score
 * 5. Record in tbl_meta_attribution
 * 6. Create AI task for follow-up (tbl_ai_tasks)
 * 7. Send Conversions API event to Meta
 */
class LeadEnrichmentService
{
    private PDO         $pdo;
    private EventLogger $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->logger = new EventLogger($pdo);
    }

    /**
     * Entry point: process a lead from WebhookEventRouter::handleLeadAd()
     *
     * @param array $webhookData  Raw Meta webhook change value
     * @param int   $channelId    tbl_omni_channels.id
     * @param int   $tenantId
     */
    public function process(array $webhookData, int $channelId, int $tenantId): void
    {
        try {
            $leadgenId = (string)($webhookData['leadgen_id'] ?? '');
            $formId    = (string)($webhookData['form_id']    ?? '');
            $adId      = (string)($webhookData['ad_id']      ?? '');
            $campaignId = (string)($webhookData['ad_group_id'] ?? '');

            // 1. Get lead details from Meta
            $leadData = $this->fetchLeadFromMeta($leadgenId, $channelId, $tenantId);

            // 2. Extract contact info from lead fields
            $fields   = $this->extractFields($leadData['field_data'] ?? []);
            $name     = $fields['full_name'] ?? ($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? '');
            $email    = $fields['email'] ?? null;
            $phone    = $fields['phone_number'] ?? $fields['phone'] ?? null;

            $this->pdo->beginTransaction();

            // 3. Resolve or create omni_customer
            $customerId = $this->resolveCustomer($leadgenId, $name, $email, $phone, $tenantId);

            // 4. Resolve local campaign/adset/ad IDs
            $localIds  = $this->resolveLocalIds($leadData, $campaignId, $adId, $formId);

            // 5. Update conversation context
            $convId = $this->createOrUpdateConversation($customerId, $channelId, $leadgenId, $leadData, $localIds, $tenantId);

            // 6. Update Customer360 journey
            $this->updateCustomer360($customerId, $leadData, $localIds, $fields, $tenantId);

            // 7. Record Attribution
            $this->recordAttribution($customerId, $leadgenId, $leadData, $localIds, $tenantId);

            $this->pdo->commit();

            // 8. Create AI task (outside transaction - non-critical)
            $this->dispatchAiTask($convId, $customerId, $leadData, $localIds, $fields, $tenantId);

            // 9. Send Conversions API event (fire-and-forget)
            $this->sendConversionsApiEvent($leadgenId, $formId, $phone, $email, $channelId, $tenantId);

            $this->logger->log('Lead Enriched', [
                'status'   => 'SUCCESS',
                'metadata' => ['leadgen_id' => $leadgenId, 'customer_id' => $customerId]
            ]);

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->log('Lead Enrichment Failed', [
                'status'   => 'FAILED',
                'metadata' => ['error' => $e->getMessage(), 'leadgen_id' => $webhookData['leadgen_id'] ?? '']
            ]);
            error_log("LeadEnrichmentService Error: " . $e->getMessage());
        }
    }

    private function fetchLeadFromMeta(string $leadgenId, int $channelId, int $tenantId): array
    {
        try {
            // Get channel account info to initialize API
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT account_id FROM tbl_meta_ad_accounts WHERE tenant_id = ? AND status = 'ACTIVE' LIMIT 1"
            );
            $stmt->execute([$tenantId]);
            $account = $stmt->fetchColumn();

            if (!$account) return ['field_data' => [], 'id' => $leadgenId];

            $api = new MarketingApiClient($this->pdo, $account, $tenantId);
            return $api->getLeadDetails($leadgenId);
        } catch (Exception $e) {
            return ['field_data' => [], 'id' => $leadgenId];
        }
    }

    private function extractFields(array $fieldData): array
    {
        $out = [];
        foreach ($fieldData as $f) {
            $key       = strtolower(str_replace([' ', '-'], '_', $f['name'] ?? ''));
            $out[$key] = $f['values'][0] ?? null;
        }
        return $out;
    }

    private function resolveCustomer(string $leadgenId, string $name, ?string $email, ?string $phone, int $tenantId): int
    {
        $db = new \SaaS\Repositories\DatabaseRepository($this->pdo);

        // Check by leadgen_id identity first
        $stmt = $db->prepare(
            "SELECT customer_id FROM tbl_omni_customer_identities WHERE provider = 'meta_lead' AND platform_user_id = ? LIMIT 1"
        );
        $stmt->execute([$leadgenId]);
        $customerId = $stmt->fetchColumn();

        if (!$customerId) {
            // Create new omni customer
            $stmt = $db->prepare(
                "INSERT INTO tbl_omni_customers (name, email, phone, journey_stage, tenant_id) VALUES (?, ?, ?, 'LEAD', ?) ON DUPLICATE KEY UPDATE name = VALUES(name)"
            );
            // Try to handle if tenant_id column exists
            try {
                $stmt->execute([trim($name), $email, $phone, $tenantId]);
            } catch (Exception $e) {
                $stmt2 = $db->prepare("INSERT INTO tbl_omni_customers (name, email, phone, journey_stage) VALUES (?, ?, ?, 'LEAD')");
                $stmt2->execute([trim($name), $email, $phone]);
            }
            $customerId = $this->pdo->lastInsertId();

            // Register identity
            $stmt2 = $db->prepare(
                "INSERT IGNORE INTO tbl_omni_customer_identities (customer_id, provider, platform_user_id) VALUES (?, 'meta_lead', ?)"
            );
            $stmt2->execute([$customerId, $leadgenId]);
        } else {
            // Update existing customer with new info
            $stmt = $db->prepare(
                "UPDATE tbl_omni_customers SET journey_stage = 'LEAD', name = COALESCE(NULLIF(?, ''), name), email = COALESCE(?, email), phone = COALESCE(?, phone) WHERE id = ?"
            );
            $stmt->execute([trim($name), $email, $phone, $customerId]);
        }

        return (int)$customerId;
    }

    private function resolveLocalIds(array $leadData, string $campaignId, string $adId, string $formId): array
    {
        $db = new \SaaS\Repositories\DatabaseRepository($this->pdo);
        $ids = [
            'campaign_row_id'  => null,
            'adset_row_id'     => null,
            'ad_row_id'        => null,
            'creative_row_id'  => null,
            'lead_form_row_id' => null,
            'product_id'       => null,
            'meta_campaign_id' => $leadData['campaign_id'] ?? $campaignId,
            'meta_adset_id'    => $leadData['adset_id'] ?? '',
            'meta_ad_id'       => $leadData['ad_id'] ?? $adId,
            'meta_form_id'     => $formId,
        ];

        if ($ids['meta_campaign_id']) {
            $stmt = $db->prepare("SELECT id, product_ids FROM tbl_meta_campaigns WHERE meta_campaign_id = ? LIMIT 1");
            $stmt->execute([$ids['meta_campaign_id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $ids['campaign_row_id'] = $row['id'];
                $productIds = json_decode($row['product_ids'] ?? '[]', true);
                $ids['product_id'] = $productIds[0] ?? null;
            }
        }

        if ($ids['meta_adset_id']) {
            $stmt = $db->prepare("SELECT id FROM tbl_meta_ad_sets WHERE meta_adset_id = ? LIMIT 1");
            $stmt->execute([$ids['meta_adset_id']]);
            $ids['adset_row_id'] = $stmt->fetchColumn() ?: null;
        }

        if ($ids['meta_ad_id']) {
            $stmt = $db->prepare("SELECT id, creative_id FROM tbl_meta_ads WHERE meta_ad_id = ? LIMIT 1");
            $stmt->execute([$ids['meta_ad_id']]);
            $adRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($adRow) {
                $ids['ad_row_id']      = $adRow['id'];
                $ids['creative_row_id'] = $adRow['creative_id'];
            }
        }

        if ($formId) {
            $stmt = $db->prepare("SELECT id FROM tbl_meta_lead_forms WHERE meta_form_id = ? LIMIT 1");
            $stmt->execute([$formId]);
            $ids['lead_form_row_id'] = $stmt->fetchColumn() ?: null;
        }

        return $ids;
    }

    private function createOrUpdateConversation(int $customerId, int $channelId, string $leadgenId, array $leadData, array $localIds, int $tenantId): int
    {
        $db   = new \SaaS\Repositories\DatabaseRepository($this->pdo);
        $stmt = $db->prepare(
            "SELECT id FROM tbl_omni_conversations WHERE customer_id = ? AND current_channel_id = ? AND current_status = 'OPEN' LIMIT 1"
        );
        $stmt->execute([$customerId, $channelId]);
        $convId = $stmt->fetchColumn();

        if (!$convId) {
            $stmt = $db->prepare("
                INSERT INTO tbl_omni_conversations
                    (customer_id, current_channel_id, campaign_id, ad_id,
                     meta_campaign_id, meta_adset_id, meta_creative_id, lead_form_id,
                     leadgen_id, lead_source, lead_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'META_LEAD_AD', 50)
            ");
            $stmt->execute([
                $customerId,
                $channelId,
                $localIds['meta_campaign_id'],
                $localIds['meta_ad_id'],
                $localIds['meta_campaign_id'],
                $localIds['meta_adset_id'],
                $localIds['creative_row_id'],
                $localIds['meta_form_id'],
                $leadgenId,
            ]);
            $convId = (int)$this->pdo->lastInsertId();
        } else {
            $stmt = $db->prepare("
                UPDATE tbl_omni_conversations
                SET meta_campaign_id = ?, meta_adset_id = ?, lead_form_id = ?, leadgen_id = ?, lead_source = 'META_LEAD_AD', last_activity = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $localIds['meta_campaign_id'],
                $localIds['meta_adset_id'],
                $localIds['meta_form_id'],
                $leadgenId,
                $convId,
            ]);
        }

        return (int)$convId;
    }

    private function updateCustomer360(int $customerId, array $leadData, array $localIds, array $fields, int $tenantId): void
    {
        // Update omni_customers with campaign attribution
        $db   = new \SaaS\Repositories\DatabaseRepository($this->pdo);
        $stmt = $db->prepare("
            UPDATE tbl_omni_customers
            SET journey_stage = 'LEAD'
            WHERE id = ?
        ");
        $stmt->execute([$customerId]);
    }

    private function recordAttribution(int $customerId, string $leadgenId, array $leadData, array $localIds, int $tenantId): void
    {
        $db   = new \SaaS\Repositories\DatabaseRepository($this->pdo);
        $stmt = $db->prepare("
            INSERT IGNORE INTO tbl_meta_attribution
                (tenant_id, customer_id, meta_campaign_id, meta_adset_id, meta_ad_id,
                 lead_form_id, leadgen_id, product_id, first_touch_at, model)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'LAST_CLICK')
        ");
        $stmt->execute([
            $tenantId,
            $customerId,
            $localIds['meta_campaign_id'],
            $localIds['meta_adset_id'],
            $localIds['meta_ad_id'],
            $localIds['meta_form_id'],
            $leadgenId,
            $localIds['product_id'],
        ]);
    }

    private function dispatchAiTask(int $convId, int $customerId, array $leadData, array $localIds, array $fields, int $tenantId): void
    {
        // Build rich context for AI Sales Agent
        $campaignName = '';
        $productName  = '';
        $adName       = $leadData['ad_name'] ?? '';

        if ($localIds['campaign_row_id']) {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT name FROM tbl_meta_campaigns WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$localIds['campaign_row_id']]);
            $campaignName = $stmt->fetchColumn() ?: '';
        }

        if ($localIds['product_id']) {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT p_name FROM tbl_product WHERE p_id = ? LIMIT 1"
            );
            $stmt->execute([$localIds['product_id']]);
            $productName = $stmt->fetchColumn() ?: '';
        }

        $payload = json_encode([
            'instruction'    => 'LEAD_FOLLOW_UP',
            'message'        => 'New lead from Meta Lead Ad',
            'type'           => 'LEAD',
            'channel_id'     => null,
            'platform_user_id' => 'lead_' . ($leadData['id'] ?? ''),
            // Rich context for AI
            'lead_context'   => [
                'customer_name'   => $fields['full_name'] ?? ($fields['first_name'] ?? ''),
                'phone'           => $fields['phone_number'] ?? $fields['phone'] ?? null,
                'email'           => $fields['email'] ?? null,
                'campaign_name'   => $campaignName,
                'campaign_id'     => $localIds['meta_campaign_id'],
                'ad_name'         => $adName,
                'ad_id'           => $localIds['meta_ad_id'],
                'product_name'    => $productName,
                'product_id'      => $localIds['product_id'],
                'lead_form_id'    => $localIds['meta_form_id'],
                'journey_stage'   => 'LEAD',
                'lead_score'      => 50,
                'lead_source'     => 'META_LEAD_AD',
                'all_fields'      => $fields,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
            "INSERT INTO tbl_ai_tasks (task_type, entity_type, entity_id, priority, payload, status) VALUES ('lead_follow_up', 'conversation', ?, 'HIGH', ?, 'PENDING')"
        );
        $stmt->execute([$convId, $payload]);
    }

    private function sendConversionsApiEvent(string $leadgenId, string $formId, ?string $phone, ?string $email, int $channelId, int $tenantId): void
    {
        try {
            // Get pixel ID from settings
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT facebook_pixel_id FROM tbl_settings WHERE id = 1 LIMIT 1"
            );
            $stmt->execute();
            $pixelId = $stmt->fetchColumn();

            if (!$pixelId) return;

            // Get ad account for API init
            $stmt2 = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT account_id FROM tbl_meta_ad_accounts WHERE tenant_id = ? AND status = 'ACTIVE' LIMIT 1"
            );
            $stmt2->execute([$tenantId]);
            $accountId = $stmt2->fetchColumn();
            if (!$accountId) return;

            $api = new MarketingApiClient($this->pdo, $accountId, $tenantId);
            $api->sendLeadEvent($pixelId, [
                'email'      => $email,
                'phone'      => $phone,
                'leadgen_id' => $leadgenId,
                'form_id'    => $formId,
            ]);
        } catch (Exception $e) {
            error_log("ConversionsAPI event failed: " . $e->getMessage());
        }
    }
}
