<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class CampaignRepository extends BaseRepository
{
    protected string $table      = 'tbl_meta_campaigns';
    protected string $primaryKey = 'id';
    protected bool   $useSoftDelete = true;

    public function findByMetaId(string $metaCampaignId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE meta_campaign_id = ? AND tenant_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$metaCampaignId, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAllByAccount(int $adAccountId): array
    {
        return $this->findAll(['ad_account_id' => $adAccountId, 'is_deleted' => 0], 'created_at DESC');
    }

    public function findActive(): array
    {
        return $this->findAll(['status' => 'ACTIVE', 'is_deleted' => 0], 'updated_at DESC');
    }

    /** Upsert based on meta_campaign_id */
    public function upsert(array $data): int
    {
        $existing = $this->findByMetaId($data['meta_campaign_id']);
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        $data['tenant_id'] = TenantContext::getTenantId();
        return $this->create($data);
    }
}
