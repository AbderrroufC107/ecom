<?php
namespace Marketing;

use PDO;
use Exception;
use Marketing\MarketingApiClient;
use Marketing\Repositories\CampaignRepository;
use Marketing\Repositories\AdSetRepository;
use Marketing\Repositories\AdRepository;
use Marketing\Repositories\CreativeRepository;
use Marketing\Repositories\InsightsRepository;
use Marketing\Repositories\SyncLogRepository;
use Omni\EventLogger;

/**
 * SyncEngine - Bidirectional synchronization between local DB and Meta API.
 *
 * Supports:
 * - Import campaigns/adsets/ads/creatives from Meta
 * - Export local changes to Meta
 * - Delta detection (sync_hash comparison)
 * - Full insights sync with time-series data
 */
class SyncEngine
{
    private PDO                 $pdo;
    private MarketingApiClient  $api;
    private CampaignRepository  $campaigns;
    private AdSetRepository     $adSets;
    private AdRepository        $ads;
    private CreativeRepository  $creatives;
    private InsightsRepository  $insights;
    private SyncLogRepository   $syncLogs;
    private EventLogger         $logger;
    private string              $adAccountId;

    public function __construct(PDO $pdo, string $adAccountId, int $tenantId)
    {
        $this->pdo          = $pdo;
        $this->adAccountId  = $adAccountId;
        $this->api          = new MarketingApiClient($pdo, $adAccountId, $tenantId);
        $this->campaigns    = new CampaignRepository($pdo);
        $this->adSets       = new AdSetRepository($pdo);
        $this->ads          = new AdRepository($pdo);
        $this->creatives    = new CreativeRepository($pdo);
        $this->insights     = new InsightsRepository($pdo);
        $this->syncLogs     = new SyncLogRepository($pdo);
        $this->logger       = new EventLogger($pdo);
    }

    // ════════════════════════════════════════════════════
    // IMPORT (Meta → Local DB)
    // ════════════════════════════════════════════════════

    public function importCampaigns(int $adAccountRowId): array
    {
        $logId   = $this->syncLogs->startSync('CAMPAIGNS', 'IMPORT');
        $synced  = 0;
        $failed  = 0;
        $errors  = [];

        try {
            $data = $this->api->getCampaigns($this->adAccountId);
            $campaigns = $data['data'] ?? [];

            foreach ($campaigns as $c) {
                try {
                    $row = [
                        'ad_account_id'    => $adAccountRowId,
                        'meta_campaign_id' => $c['id'],
                        'name'             => $c['name'] ?? '',
                        'objective'        => $c['objective'] ?? null,
                        'status'           => $c['status'] ?? 'ACTIVE',
                        'effective_status' => $c['effective_status'] ?? null,
                        'budget_daily'     => isset($c['daily_budget']) ? $c['daily_budget'] / 100 : null,
                        'budget_lifetime'  => isset($c['lifetime_budget']) ? $c['lifetime_budget'] / 100 : null,
                        'spend_cap'        => isset($c['spend_cap']) ? $c['spend_cap'] / 100 : null,
                        'start_time'       => $c['start_time'] ?? null,
                        'end_time'         => $c['end_time'] ?? null,
                        'bid_strategy'     => $c['bid_strategy'] ?? null,
                        'buying_type'      => $c['buying_type'] ?? 'AUCTION',
                        'meta_json'        => json_encode($c, JSON_UNESCAPED_UNICODE),
                        'last_synced_at'   => date('Y-m-d H:i:s'),
                    ];

                    // Delta detection via hash
                    $newHash = hash('sha256', json_encode($row));
                    $existing = $this->campaigns->findByMetaId($c['id']);
                    if ($existing && $existing['sync_hash'] === $newHash) {
                        continue; // No change
                    }
                    $row['sync_hash'] = $newHash;
                    $this->campaigns->upsert($row);
                    $synced++;

                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "Campaign {$c['id']}: " . $e->getMessage();
                }
            }

            $this->syncLogs->completeSync($logId, $synced, $failed, $failed > 0 ? implode('; ', $errors) : null);
            $this->logger->log('Campaigns Synced', ['status' => 'SUCCESS', 'metadata' => ['count' => $synced]]);
            return ['synced' => $synced, 'failed' => $failed];

        } catch (Exception $e) {
            $this->syncLogs->completeSync($logId, $synced, $failed, $e->getMessage());
            throw $e;
        }
    }

    public function importAdSets(int $campaignRowId, string $metaCampaignId): array
    {
        $logId  = $this->syncLogs->startSync('ADSETS', 'IMPORT', $metaCampaignId);
        $synced = 0; $failed = 0;

        try {
            $data   = $this->api->getAdSets($metaCampaignId);
            $adSets = $data['data'] ?? [];

            foreach ($adSets as $as) {
                try {
                    $row = [
                        'campaign_id'      => $campaignRowId,
                        'meta_adset_id'    => $as['id'],
                        'name'             => $as['name'] ?? '',
                        'status'           => $as['status'] ?? 'ACTIVE',
                        'effective_status' => $as['effective_status'] ?? null,
                        'budget_daily'     => isset($as['daily_budget']) ? $as['daily_budget'] / 100 : null,
                        'budget_lifetime'  => isset($as['lifetime_budget']) ? $as['lifetime_budget'] / 100 : null,
                        'bid_amount'       => isset($as['bid_amount']) ? $as['bid_amount'] / 100 : null,
                        'bid_strategy'     => $as['bid_strategy'] ?? null,
                        'optimization_goal'=> $as['optimization_goal'] ?? null,
                        'billing_event'    => $as['billing_event'] ?? null,
                        'targeting_json'   => json_encode($as['targeting'] ?? [], JSON_UNESCAPED_UNICODE),
                        'destination_type' => $as['destination_type'] ?? null,
                        'start_time'       => $as['start_time'] ?? null,
                        'end_time'         => $as['end_time'] ?? null,
                        'meta_json'        => json_encode($as, JSON_UNESCAPED_UNICODE),
                        'last_synced_at'   => date('Y-m-d H:i:s'),
                    ];
                    $this->adSets->upsert($row);
                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                }
            }

            $this->syncLogs->completeSync($logId, $synced, $failed);
            return ['synced' => $synced, 'failed' => $failed];

        } catch (Exception $e) {
            $this->syncLogs->completeSync($logId, 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function importAds(int $adSetRowId, string $metaAdSetId): array
    {
        $logId  = $this->syncLogs->startSync('ADS', 'IMPORT', $metaAdSetId);
        $synced = 0; $failed = 0;

        try {
            $data = $this->api->getAds($metaAdSetId);
            $ads  = $data['data'] ?? [];

            foreach ($ads as $ad) {
                try {
                    $row = [
                        'adset_id'        => $adSetRowId,
                        'meta_ad_id'      => $ad['id'],
                        'name'            => $ad['name'] ?? '',
                        'status'          => $ad['status'] ?? 'ACTIVE',
                        'effective_status'=> $ad['effective_status'] ?? null,
                        'tracking_specs'  => json_encode($ad['tracking_specs'] ?? [], JSON_UNESCAPED_UNICODE),
                        'bid_amount'      => isset($ad['bid_amount']) ? $ad['bid_amount'] / 100 : null,
                        'meta_json'       => json_encode($ad, JSON_UNESCAPED_UNICODE),
                        'last_synced_at'  => date('Y-m-d H:i:s'),
                    ];
                    $this->ads->upsert($row);
                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                }
            }

            $this->syncLogs->completeSync($logId, $synced, $failed);
            return ['synced' => $synced, 'failed' => $failed];

        } catch (Exception $e) {
            $this->syncLogs->completeSync($logId, 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function importInsights(string $metaCampaignId, ?int $campaignRowId = null, int $days = 30): array
    {
        $logId  = $this->syncLogs->startSync('INSIGHTS', 'IMPORT', $metaCampaignId);
        $synced = 0; $failed = 0;

        try {
            $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
            $response = $this->api->getCampaignInsights($metaCampaignId, [
                'time_range' => json_encode(['since' => $dateFrom, 'until' => date('Y-m-d')]),
                'time_increment' => 1,
                'level' => 'campaign',
            ]);

            $rows = $response['data'] ?? [];

            foreach ($rows as $row) {
                try {
                    $actions = [];
                    foreach ($row['actions'] ?? [] as $a) {
                        $actions[$a['action_type']] = $a['value'];
                    }

                    $purchaseValue = 0;
                    foreach ($row['purchase_roas'] ?? [] as $roas) {
                        if ($roas['action_type'] === 'omni_purchase') {
                            $purchaseValue = (float)$roas['value'] * (float)($row['spend'] ?? 0);
                        }
                    }

                    $data = [
                        'level'            => 'CAMPAIGN',
                        'entity_meta_id'   => $metaCampaignId,
                        'campaign_id'      => $campaignRowId,
                        'adset_id'         => null,
                        'ad_id'            => null,
                        'date_start'       => $row['date_start'],
                        'date_stop'        => $row['date_stop'],
                        'impressions'      => (int)($row['impressions'] ?? 0),
                        'reach'            => (int)($row['reach'] ?? 0),
                        'frequency'        => (float)($row['frequency'] ?? 0),
                        'clicks'           => (int)($row['clicks'] ?? 0),
                        'unique_clicks'    => (int)($row['unique_clicks'] ?? 0),
                        'spend'            => (float)($row['spend'] ?? 0),
                        'cpc'              => (float)($row['cpc'] ?? 0),
                        'cpm'              => (float)($row['cpm'] ?? 0),
                        'ctr'              => (float)($row['ctr'] ?? 0),
                        'cpp'              => (float)($row['cpp'] ?? 0),
                        'leads'            => (int)($actions['lead'] ?? $actions['onsite_conversion.lead_grouped'] ?? 0),
                        'cost_per_lead'    => (float)($row['cost_per_action_type'][0]['value'] ?? 0),
                        'purchases'        => (int)($actions['purchase'] ?? $actions['omni_purchase'] ?? 0),
                        'purchase_value'   => $purchaseValue,
                        'roas'             => $purchaseValue > 0 ? round($purchaseValue / max($row['spend'], 0.01), 4) : 0,
                        'cost_per_purchase'=> (int)($actions['purchase'] ?? 0) > 0
                                                ? round((float)($row['spend'] ?? 0) / max((int)($actions['purchase'] ?? 1), 1), 2) : null,
                        'link_clicks'      => (int)($row['outbound_clicks'][0]['value'] ?? 0),
                        'post_engagements' => (int)($actions['post_engagement'] ?? 0),
                        'video_views'      => (int)($row['video_30_sec_watched_actions'][0]['value'] ?? 0),
                        'video_view_3s'    => (int)($row['video_p100_watched_actions'][0]['value'] ?? 0),
                        'actions_json'     => json_encode($row['actions'] ?? [], JSON_UNESCAPED_UNICODE),
                        'raw_json'         => json_encode($row, JSON_UNESCAPED_UNICODE),
                    ];

                    $this->insights->upsertInsight($data);
                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                }
            }

            $this->syncLogs->completeSync($logId, $synced, $failed);
            return ['synced' => $synced, 'failed' => $failed];

        } catch (Exception $e) {
            $this->syncLogs->completeSync($logId, 0, 0, $e->getMessage());
            throw $e;
        }
    }

    // ════════════════════════════════════════════════════
    // FULL SYNC (all campaigns for an account)
    // ════════════════════════════════════════════════════

    public function fullSync(int $adAccountRowId): array
    {
        $results = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0, 'insights' => 0];

        // 1. Import campaigns
        $r = $this->importCampaigns($adAccountRowId);
        $results['campaigns'] = $r['synced'];

        // 2. For each campaign, import adsets + insights
        $campaigns = $this->campaigns->findAllByAccount($adAccountRowId);
        foreach ($campaigns as $campaign) {
            try {
                $r = $this->importAdSets($campaign['id'], $campaign['meta_campaign_id']);
                $results['adsets'] += $r['synced'];

                $r = $this->importInsights($campaign['meta_campaign_id'], $campaign['id'], 30);
                $results['insights'] += $r['synced'];

                // 3. For each adset, import ads
                $adSets = $this->adSets->findByCampaign($campaign['id']);
                foreach ($adSets as $adSet) {
                    $r = $this->importAds($adSet['id'], $adSet['meta_adset_id']);
                    $results['ads'] += $r['synced'];
                }
            } catch (Exception $e) {
                $this->logger->log('Sync Error', [
                    'status'   => 'PARTIAL_FAIL',
                    'metadata' => ['campaign' => $campaign['meta_campaign_id'], 'error' => $e->getMessage()]
                ]);
            }
        }

        return $results;
    }

    // ════════════════════════════════════════════════════
    // EXPORT (Local DB → Meta API)
    // ════════════════════════════════════════════════════

    public function publishCampaign(array $campaignData): array
    {
        $result = $this->api->createCampaign($this->adAccountId, $campaignData);
        if (isset($result['id'])) {
            $this->campaigns->upsert(array_merge($campaignData, [
                'meta_campaign_id' => $result['id'],
                'last_synced_at'   => date('Y-m-d H:i:s'),
                'meta_json'        => json_encode($result),
            ]));
            $this->logger->log('Campaign Published', ['metadata' => ['meta_id' => $result['id']]]);
        }
        return $result;
    }

    public function toggleStatus(string $entityType, string $metaId, string $newStatus): array
    {
        $result = match ($newStatus) {
            'ACTIVE' => match ($entityType) {
                'campaign' => $this->api->resumeCampaign($metaId),
                'adset'    => $this->api->resumeAdSet($metaId),
                'ad'       => $this->api->resumeAd($metaId),
                default    => throw new Exception("Unknown entity type: {$entityType}")
            },
            'PAUSED' => match ($entityType) {
                'campaign' => $this->api->pauseCampaign($metaId),
                'adset'    => $this->api->pauseAdSet($metaId),
                'ad'       => $this->api->pauseAd($metaId),
                default    => throw new Exception("Unknown entity type: {$entityType}")
            },
            default => throw new Exception("Invalid status: {$newStatus}")
        };

        // Update local DB
        $repo = match ($entityType) {
            'campaign' => $this->campaigns,
            'adset'    => $this->adSets,
            'ad'       => $this->ads,
            default    => null
        };

        if ($repo) {
            $existing = match ($entityType) {
                'campaign' => $this->campaigns->findByMetaId($metaId),
                'adset'    => $this->adSets->findByMetaId($metaId),
                'ad'       => $this->ads->findByMetaId($metaId),
                default    => null
            };
            if ($existing) {
                $repo->update($existing['id'], ['status' => $newStatus, 'last_synced_at' => date('Y-m-d H:i:s')]);
            }
        }

        return $result;
    }
}
