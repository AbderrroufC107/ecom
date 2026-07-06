<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class InsightsRepository extends BaseRepository
{
    protected string $table      = 'tbl_meta_campaign_insights';
    protected string $primaryKey = 'id';

    /**
     * Upsert insight row (unique by tenant+level+entity+date)
     */
    public function upsertInsight(array $data): void
    {
        $data['tenant_id'] = TenantContext::getTenantId();
        $data['synced_at'] = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO `{$this->table}`
                (tenant_id, level, entity_meta_id, campaign_id, adset_id, ad_id,
                 date_start, date_stop,
                 impressions, reach, frequency, clicks, unique_clicks, spend,
                 cpc, cpm, ctr, cpp, leads, cost_per_lead,
                 purchases, purchase_value, roas, cost_per_purchase,
                 link_clicks, post_engagements, video_views, video_view_3s,
                 actions_json, raw_json, synced_at)
            VALUES
                (:tenant_id, :level, :entity_meta_id, :campaign_id, :adset_id, :ad_id,
                 :date_start, :date_stop,
                 :impressions, :reach, :frequency, :clicks, :unique_clicks, :spend,
                 :cpc, :cpm, :ctr, :cpp, :leads, :cost_per_lead,
                 :purchases, :purchase_value, :roas, :cost_per_purchase,
                 :link_clicks, :post_engagements, :video_views, :video_view_3s,
                 :actions_json, :raw_json, :synced_at)
            ON DUPLICATE KEY UPDATE
                impressions     = VALUES(impressions),
                reach           = VALUES(reach),
                frequency       = VALUES(frequency),
                clicks          = VALUES(clicks),
                unique_clicks   = VALUES(unique_clicks),
                spend           = VALUES(spend),
                cpc             = VALUES(cpc),
                cpm             = VALUES(cpm),
                ctr             = VALUES(ctr),
                cpp             = VALUES(cpp),
                leads           = VALUES(leads),
                cost_per_lead   = VALUES(cost_per_lead),
                purchases       = VALUES(purchases),
                purchase_value  = VALUES(purchase_value),
                roas            = VALUES(roas),
                cost_per_purchase = VALUES(cost_per_purchase),
                link_clicks     = VALUES(link_clicks),
                post_engagements= VALUES(post_engagements),
                video_views     = VALUES(video_views),
                video_view_3s   = VALUES(video_view_3s),
                actions_json    = VALUES(actions_json),
                raw_json        = VALUES(raw_json),
                synced_at       = VALUES(synced_at)
        ");
        $stmt->execute($data);
    }

    public function getSummary(int $days = 30, ?int $campaignId = null): array
    {
        $tenantId  = TenantContext::getTenantId();
        $dateFrom  = date('Y-m-d', strtotime("-{$days} days"));
        $params    = [$tenantId, $dateFrom];
        $condition = $campaignId ? " AND campaign_id = ?" : "";
        if ($campaignId) $params[] = $campaignId;

        $stmt = $this->pdo->prepare("
            SELECT
                SUM(impressions)    AS total_impressions,
                SUM(reach)          AS total_reach,
                SUM(clicks)         AS total_clicks,
                SUM(spend)          AS total_spend,
                SUM(leads)          AS total_leads,
                SUM(purchases)      AS total_purchases,
                SUM(purchase_value) AS total_revenue,
                ROUND(SUM(purchase_value)/NULLIF(SUM(spend),0), 2) AS roas,
                ROUND(SUM(clicks)/NULLIF(SUM(impressions),0)*100, 4) AS ctr,
                ROUND(SUM(spend)/NULLIF(SUM(clicks),0), 2) AS cpc
            FROM `{$this->table}`
            WHERE tenant_id = ? AND date_start >= ? {$condition}
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTimeSeries(int $campaignId, int $days = 30): array
    {
        $tenantId = TenantContext::getTenantId();
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
        $stmt = $this->pdo->prepare("
            SELECT date_start, SUM(spend) AS spend, SUM(purchase_value) AS revenue,
                   SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(leads) AS leads
            FROM `{$this->table}`
            WHERE tenant_id = ? AND campaign_id = ? AND date_start >= ?
            GROUP BY date_start ORDER BY date_start ASC
        ");
        $stmt->execute([$tenantId, $campaignId, $dateFrom]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopCampaigns(int $limit = 10, int $days = 30): array
    {
        $tenantId = TenantContext::getTenantId();
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
        $stmt = $this->pdo->prepare("
            SELECT i.campaign_id, c.name, c.meta_campaign_id,
                   SUM(i.spend) AS spend, SUM(i.purchase_value) AS revenue,
                   SUM(i.purchases) AS orders, SUM(i.leads) AS leads,
                   ROUND(SUM(i.purchase_value)/NULLIF(SUM(i.spend),0), 2) AS roas
            FROM `{$this->table}` i
            LEFT JOIN tbl_meta_campaigns c ON c.id = i.campaign_id
            WHERE i.tenant_id = ? AND i.level = 'CAMPAIGN' AND i.date_start >= ?
            GROUP BY i.campaign_id, c.name, c.meta_campaign_id
            ORDER BY roas DESC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $dateFrom, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
