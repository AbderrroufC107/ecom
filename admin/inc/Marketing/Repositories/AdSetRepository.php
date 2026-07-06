<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class AdSetRepository extends BaseRepository
{
    protected string $table      = 'tbl_meta_ad_sets';
    protected string $primaryKey = 'id';
    protected bool   $useSoftDelete = true;

    public function findByMetaId(string $metaAdSetId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE meta_adset_id = ? AND tenant_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$metaAdSetId, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByCampaign(int $campaignId): array
    {
        return $this->findAll(['campaign_id' => $campaignId, 'is_deleted' => 0], 'created_at DESC');
    }

    public function upsert(array $data): int
    {
        $existing = $this->findByMetaId($data['meta_adset_id']);
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        $data['tenant_id'] = TenantContext::getTenantId();
        return $this->create($data);
    }
}

