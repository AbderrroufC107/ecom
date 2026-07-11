<?php
namespace Marketing;

use PDO;
use Exception;

/**
 * MarketingApiClient - Meta Graph API Marketing Client
 *
 * Supports: Campaigns, Ad Sets, Ads, Creatives, Audiences,
 *           Lead Forms, Insights, Uploads, A/B Tests, Conversions.
 *
 * - graph_api_version read from tbl_settings (no hardcoded version)
 * - Access tokens fetched from SecretManager (AES-256 encrypted)
 * - All requests logged via EventLogger
 */
class MarketingApiClient
{
    private PDO    $pdo;
    private string $accessToken;
    private string $apiVersion;
    private string $baseUrl;
    private int    $tenantId;

    // Default fields for common objects
    private array $campaignFields = [
        'id','name','objective','status','effective_status',
        'daily_budget','lifetime_budget','spend_cap','start_time','end_time',
        'bid_strategy','buying_type','special_ad_categories','created_time','updated_time'
    ];

    private array $adSetFields = [
        'id','name','campaign_id','status','effective_status',
        'daily_budget','lifetime_budget','bid_amount','bid_strategy',
        'optimization_goal','billing_event','targeting','destination_type',
        'promoted_object','pacing_type','start_time','end_time','created_time'
    ];

    private array $adFields = [
        'id','name','adset_id','status','effective_status',
        'creative','tracking_specs','bid_amount','conversion_specs','created_time'
    ];

    private array $insightFields = [
        'impressions','reach','frequency','clicks','unique_clicks','spend',
        'cpc','cpm','ctr','cpp','actions','cost_per_action_type',
        'purchase_roas','video_30_sec_watched_actions','video_p100_watched_actions',
        'outbound_clicks','unique_link_clicks_ctr','cost_per_unique_click'
    ];

    public function __construct(PDO $pdo, string $adAccountId, int $tenantId)
    {
        $this->pdo      = $pdo;
        $this->tenantId = $tenantId;

        // Load API version from tbl_settings
        $this->apiVersion = $this->loadApiVersion();
        $this->baseUrl    = "https://graph.facebook.com/{$this->apiVersion}";

        // Load access token from SecretManager
        $secretManager    = new \Security\SecretManager($pdo);
        $secretName       = "meta_marketing_{$adAccountId}_{$tenantId}";
        $token            = $secretManager->getSecret($secretName);

        if (!$token) {
            // Fallback: try account-level token
            $token = $secretManager->getSecret("meta_marketing_{$tenantId}");
        }

        if (!$token) {
            throw new Exception("No Meta Marketing API token found for account {$adAccountId}. Please configure it in Marketing Settings.");
        }

        $this->accessToken = $token;
    }

    // ════════════════════════════════════════════════════
    // CAMPAIGNS
    // ════════════════════════════════════════════════════

    public function getCampaigns(string $adAccountId, array $params = []): array
    {
        $fields = implode(',', $this->campaignFields);
        return $this->get("act_{$adAccountId}/campaigns", array_merge([
            'fields' => $fields,
            'limit'  => 100,
        ], $params));
    }

    public function getCampaign(string $campaignId): array
    {
        $fields = implode(',', $this->campaignFields);
        return $this->get($campaignId, ['fields' => $fields]);
    }

    public function createCampaign(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/campaigns", $data);
    }

    public function updateCampaign(string $campaignId, array $data): array
    {
        return $this->post($campaignId, $data);
    }

    public function pauseCampaign(string $campaignId): array
    {
        return $this->post($campaignId, ['status' => 'PAUSED']);
    }

    public function resumeCampaign(string $campaignId): array
    {
        return $this->post($campaignId, ['status' => 'ACTIVE']);
    }

    public function deleteCampaign(string $campaignId): array
    {
        return $this->delete($campaignId);
    }

    // ════════════════════════════════════════════════════
    // AD SETS
    // ════════════════════════════════════════════════════

    public function getAdSets(string $campaignId, array $params = []): array
    {
        $fields = implode(',', $this->adSetFields);
        return $this->get("{$campaignId}/adsets", array_merge([
            'fields' => $fields,
            'limit'  => 100,
        ], $params));
    }

    public function getAdSetsForAccount(string $adAccountId, array $params = []): array
    {
        $fields = implode(',', $this->adSetFields);
        return $this->get("act_{$adAccountId}/adsets", array_merge([
            'fields' => $fields,
            'limit'  => 100,
        ], $params));
    }

    public function getAdSet(string $adSetId): array
    {
        $fields = implode(',', $this->adSetFields);
        return $this->get($adSetId, ['fields' => $fields]);
    }

    public function createAdSet(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/adsets", $data);
    }

    public function updateAdSet(string $adSetId, array $data): array
    {
        return $this->post($adSetId, $data);
    }

    public function pauseAdSet(string $adSetId): array
    {
        return $this->post($adSetId, ['status' => 'PAUSED']);
    }

    public function resumeAdSet(string $adSetId): array
    {
        return $this->post($adSetId, ['status' => 'ACTIVE']);
    }

    public function deleteAdSet(string $adSetId): array
    {
        return $this->delete($adSetId);
    }

    // ════════════════════════════════════════════════════
    // ADS
    // ════════════════════════════════════════════════════

    public function getAds(string $adSetId, array $params = []): array
    {
        $fields = implode(',', $this->adFields);
        return $this->get("{$adSetId}/ads", array_merge([
            'fields' => $fields,
            'limit'  => 100,
        ], $params));
    }

    public function getAdsForAccount(string $adAccountId, array $params = []): array
    {
        $fields = implode(',', $this->adFields);
        return $this->get("act_{$adAccountId}/ads", array_merge([
            'fields' => $fields,
            'limit'  => 100,
        ], $params));
    }

    public function getAd(string $adId): array
    {
        $fields = implode(',', $this->adFields);
        return $this->get($adId, ['fields' => $fields]);
    }

    public function createAd(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/ads", $data);
    }

    public function updateAd(string $adId, array $data): array
    {
        return $this->post($adId, $data);
    }

    public function pauseAd(string $adId): array
    {
        return $this->post($adId, ['status' => 'PAUSED']);
    }

    public function resumeAd(string $adId): array
    {
        return $this->post($adId, ['status' => 'ACTIVE']);
    }

    public function deleteAd(string $adId): array
    {
        return $this->delete($adId);
    }

    // ════════════════════════════════════════════════════
    // CREATIVES
    // ════════════════════════════════════════════════════

    public function getCreatives(string $adAccountId, array $params = []): array
    {
        return $this->get("act_{$adAccountId}/adcreatives", array_merge([
            'fields' => 'id,name,object_type,title,body,image_url,video_id,call_to_action_type,link_url,created_time',
            'limit'  => 100,
        ], $params));
    }

    public function getCreative(string $creativeId): array
    {
        return $this->get($creativeId, [
            'fields' => 'id,name,object_type,title,body,image_url,video_id,call_to_action_type,link_url,object_story_spec,created_time'
        ]);
    }

    public function createImageCreative(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/adcreatives", $data);
    }

    public function createVideoCreative(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/adcreatives", $data);
    }

    public function createCarouselCreative(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/adcreatives", $data);
    }

    public function createCollectionCreative(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/adcreatives", $data);
    }

    public function deleteCreative(string $creativeId): array
    {
        return $this->delete($creativeId);
    }

    // ════════════════════════════════════════════════════
    // IMAGES & VIDEOS
    // ════════════════════════════════════════════════════

    public function uploadImage(string $adAccountId, string $imageUrl): array
    {
        return $this->post("act_{$adAccountId}/adimages", ['url' => $imageUrl]);
    }

    public function uploadImageFile(string $adAccountId, string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Image file not found: {$filePath}");
        }

        $url = "{$this->baseUrl}/act_{$adAccountId}/adimages?access_token={$this->accessToken}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['filename' => new \CURLFile($filePath)],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->parseResponse($response, $httpCode);
    }

    public function uploadVideo(string $adAccountId, string $videoPath): array
    {
        if (!file_exists($videoPath)) {
            throw new Exception("Video file not found: {$videoPath}");
        }

        $url = "{$this->baseUrl}/act_{$adAccountId}/advideos?access_token={$this->accessToken}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'source'      => new \CURLFile($videoPath),
                'title'       => basename($videoPath),
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->parseResponse($response, $httpCode);
    }

    public function getAdImages(string $adAccountId): array
    {
        return $this->get("act_{$adAccountId}/adimages", [
            'fields' => 'hash,url,name,created_time',
            'limit'  => 100,
        ]);
    }

    public function getAdVideos(string $adAccountId): array
    {
        return $this->get("act_{$adAccountId}/advideos", [
            'fields' => 'id,title,thumbnail_url,created_time,length,status',
            'limit'  => 50,
        ]);
    }

    // ════════════════════════════════════════════════════
    // LEAD FORMS
    // ════════════════════════════════════════════════════

    public function getLeadForms(string $adAccountId, array $params = []): array
    {
        // Lead forms are per page, not per account
        // Try fetching via connected pages first
        return $this->get("act_{$adAccountId}/leadgen_forms", array_merge([
            'fields' => 'id,name,status,leads_count,created_time,privacy_policy_url,questions',
            'limit'  => 50,
        ], $params));
    }

    public function getLeadFormsByPage(string $pageId): array
    {
        return $this->get("{$pageId}/leadgen_forms", [
            'fields' => 'id,name,status,leads_count,created_time,questions',
            'limit'  => 50,
        ]);
    }

    public function getLeadFormData(string $formId): array
    {
        return $this->get($formId, [
            'fields' => 'id,name,status,leads_count,created_time,questions,privacy_policy_url,follow_up_action_url,context_card'
        ]);
    }

    public function getLeadsForForm(string $formId, array $params = []): array
    {
        return $this->get("{$formId}/leads", array_merge([
            'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id',
            'limit'  => 100,
        ], $params));
    }

    public function getLeadDetails(string $leadgenId): array
    {
        return $this->get($leadgenId, [
            'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id'
        ]);
    }

    public function createLeadForm(string $pageId, array $data): array
    {
        return $this->post("{$pageId}/leadgen_forms", $data);
    }

    // ════════════════════════════════════════════════════
    // INSIGHTS / ANALYTICS
    // ════════════════════════════════════════════════════

    public function getCampaignInsights(string $campaignId, array $params = []): array
    {
        $fields = implode(',', $this->insightFields);
        return $this->get("{$campaignId}/insights", array_merge([
            'fields'       => $fields,
            'date_preset'  => 'last_30d',
            'time_increment' => 1,
            'level'        => 'campaign',
        ], $params));
    }

    public function getAdSetInsights(string $adSetId, array $params = []): array
    {
        $fields = implode(',', $this->insightFields);
        return $this->get("{$adSetId}/insights", array_merge([
            'fields'       => $fields,
            'date_preset'  => 'last_30d',
            'time_increment' => 1,
            'level'        => 'adset',
        ], $params));
    }

    public function getAdInsights(string $adId, array $params = []): array
    {
        $fields = implode(',', $this->insightFields);
        return $this->get("{$adId}/insights", array_merge([
            'fields'       => $fields,
            'date_preset'  => 'last_30d',
            'time_increment' => 1,
            'level'        => 'ad',
        ], $params));
    }

    public function getAccountInsights(string $adAccountId, array $params = []): array
    {
        $fields = implode(',', $this->insightFields);
        return $this->get("act_{$adAccountId}/insights", array_merge([
            'fields'         => $fields,
            'date_preset'    => 'last_30d',
            'time_increment' => 1,
            'level'          => 'campaign',
        ], $params));
    }

    public function getInsightsAsync(string $objectId, array $params = []): array
    {
        // For large date ranges, use async report jobs
        $fields = implode(',', $this->insightFields);
        $job = $this->post("{$objectId}/insights", array_merge([
            'fields'         => $fields,
            'time_increment' => 1,
            'async'          => true,
        ], $params));

        return $job; // Returns report_run_id
    }

    public function getAsyncJobResult(string $reportRunId): array
    {
        return $this->get($reportRunId);
    }

    // ════════════════════════════════════════════════════
    // AUDIENCES
    // ════════════════════════════════════════════════════

    public function getAudiences(string $adAccountId): array
    {
        return $this->get("act_{$adAccountId}/customaudiences", [
            'fields' => 'id,name,subtype,approximate_count,operation_status,created_time',
            'limit'  => 100,
        ]);
    }

    public function createCustomAudience(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/customaudiences", $data);
    }

    public function createLookalikeAudience(string $adAccountId, array $data): array
    {
        return $this->post("act_{$adAccountId}/act_{$adAccountId}/customaudiences", array_merge([
            'subtype' => 'LOOKALIKE',
        ], $data));
    }

    public function updateAudience(string $audienceId, array $data): array
    {
        return $this->post($audienceId, $data);
    }

    public function deleteAudience(string $audienceId): array
    {
        return $this->delete($audienceId);
    }

    public function addUsersToAudience(string $audienceId, array $users, string $schema = 'EMAIL'): array
    {
        return $this->post("{$audienceId}/users", [
            'payload' => json_encode(['schema' => $schema, 'data' => $users]),
        ]);
    }

    // ════════════════════════════════════════════════════
    // AD ACCOUNTS
    // ════════════════════════════════════════════════════

    public function getAdAccount(string $adAccountId): array
    {
        return $this->get("act_{$adAccountId}", [
            'fields' => 'id,name,account_id,currency,timezone_name,account_status,amount_spent,balance,spend_cap'
        ]);
    }

    public function getAdAccountsByUser(string $userId = 'me'): array
    {
        return $this->get("{$userId}/adaccounts", [
            'fields' => 'id,name,account_id,currency,timezone_name,account_status',
            'limit'  => 50,
        ]);
    }

    public function getAccountBalance(string $adAccountId): array
    {
        return $this->get("act_{$adAccountId}", [
            'fields' => 'balance,amount_spent,spend_cap,currency'
        ]);
    }

    // ════════════════════════════════════════════════════
    // CONVERSIONS API (Server-Side Events)
    // ════════════════════════════════════════════════════

    public function sendConversionEvent(string $pixelId, array $eventData): array
    {
        return $this->post("{$pixelId}/events", [
            'data' => json_encode([$eventData]),
        ]);
    }

    public function sendPurchaseEvent(string $pixelId, array $orderData): array
    {
        $event = [
            'event_name'  => 'Purchase',
            'event_time'  => time(),
            'event_source_url' => $orderData['page_url'] ?? '',
            'user_data'   => [
                'em' => isset($orderData['email']) ? hash('sha256', strtolower(trim($orderData['email']))) : null,
                'ph' => isset($orderData['phone']) ? hash('sha256', preg_replace('/\D/', '', $orderData['phone'])) : null,
            ],
            'custom_data' => [
                'currency'  => $orderData['currency'] ?? 'DZD',
                'value'     => $orderData['total'] ?? 0,
                'contents'  => $orderData['items'] ?? [],
                'order_id'  => $orderData['order_id'] ?? null,
                'num_items' => $orderData['qty'] ?? 1,
            ],
            'action_source' => 'website',
        ];

        return $this->sendConversionEvent($pixelId, $event);
    }

    public function sendLeadEvent(string $pixelId, array $leadData): array
    {
        $event = [
            'event_name'  => 'Lead',
            'event_time'  => time(),
            'user_data'   => [
                'em' => isset($leadData['email']) ? hash('sha256', strtolower(trim($leadData['email']))) : null,
                'ph' => isset($leadData['phone']) ? hash('sha256', preg_replace('/\D/', '', $leadData['phone'])) : null,
            ],
            'custom_data' => [
                'lead_id'  => $leadData['leadgen_id'] ?? null,
                'form_id'  => $leadData['form_id'] ?? null,
            ],
            'action_source' => 'website',
        ];

        return $this->sendConversionEvent($pixelId, $event);
    }

    // ════════════════════════════════════════════════════
    // PAGES
    // ════════════════════════════════════════════════════

    public function getPages(): array
    {
        return $this->get('me/accounts', [
            'fields' => 'id,name,category,fan_count,access_token',
            'limit'  => 50,
        ]);
    }

    public function getPage(string $pageId): array
    {
        return $this->get($pageId, [
            'fields' => 'id,name,category,fan_count,about,cover,picture'
        ]);
    }

    // ════════════════════════════════════════════════════
    // USER
    // ════════════════════════════════════════════════════

    public function getMe(): array
    {
        return $this->get('me', ['fields' => 'id,name,email']);
    }

    public function validateToken(): bool
    {
        try {
            $result = $this->get('me', ['fields' => 'id']);
            return isset($result['id']);
        } catch (Exception $e) {
            return false;
        }
    }

    // ════════════════════════════════════════════════════
    // HTTP CORE METHODS
    // ════════════════════════════════════════════════════

    private function get(string $endpoint, array $params = []): array
    {
        $params['access_token'] = $this->accessToken;
        $url = "{$this->baseUrl}/{$endpoint}?" . http_build_query($params);
        return $this->request('GET', $url, []);
    }

    private function post(string $endpoint, array $data = []): array
    {
        $url = "{$this->baseUrl}/{$endpoint}?access_token={$this->accessToken}";
        return $this->request('POST', $url, $data);
    }

    private function delete(string $endpoint): array
    {
        $url = "{$this->baseUrl}/{$endpoint}?access_token={$this->accessToken}";
        return $this->request('DELETE', $url, []);
    }

    private function request(string $method, string $url, array $data): array
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $options);

        // Retry with exponential backoff
        $maxRetries = 3;
        $attempt    = 0;
        $delay      = 1;
        $response   = '';
        $httpCode   = 0;

        while ($attempt < $maxRetries) {
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 429 && $httpCode < 500) {
                break; // Success or non-retryable error
            }

            $attempt++;
            sleep($delay);
            $delay *= 2; // Exponential backoff
        }

        curl_close($ch);

        return $this->parseResponse($response, $httpCode);
    }

    private function parseResponse(string $response, int $httpCode): array
    {
        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON from Meta API: {$response}");
        }

        if (isset($decoded['error'])) {
            $error = $decoded['error'];
            throw new Exception(
                "Meta API Error [{$error['code']}] {$error['type']}: {$error['message']}",
                (int)($error['code'] ?? 0)
            );
        }

        return $decoded;
    }

    // ════════════════════════════════════════════════════
    // PAGINATION HELPER
    // ════════════════════════════════════════════════════

    public function getAll(string $endpoint, array $params = []): array
    {
        $allData = [];
        $params['access_token'] = $this->accessToken;
        $url = "{$this->baseUrl}/{$endpoint}?" . http_build_query($params);

        while ($url) {
            $response = $this->request('GET', $url, []);
            $allData  = array_merge($allData, $response['data'] ?? []);
            $url      = $response['paging']['cursors']['after'] ?? null;

            if ($url && isset($response['paging']['next'])) {
                $url = $response['paging']['next'];
            } else {
                $url = null;
            }
        }

        return $allData;
    }

    // ════════════════════════════════════════════════════
    // SETTINGS HELPERS
    // ════════════════════════════════════════════════════

    private function loadApiVersion(): string
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT graph_api_version FROM tbl_settings WHERE id = 1 LIMIT 1"
            );
            $stmt->execute();
            $ver = $stmt->fetchColumn();
            return ($ver && preg_match('/^v\d+\.\d+$/', $ver)) ? $ver : 'v19.0';
        } catch (Exception $e) {
            return 'v19.0'; // Fallback
        }
    }
}
