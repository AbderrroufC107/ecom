<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class AdRepository extends BaseRepository
{
    protected string $table      = 'tbl_meta_ads';
    protected string $primaryKey = 'id';
    protected bool   $useSoftDelete = true;

    public function findByMetaId(string $metaAdId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE meta_ad_id = ? AND tenant_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$metaAdId, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByAdSet(int $adSetId): array
    {
        return $this->findAll(['adset_id' => $adSetId, 'is_deleted' => 0], 'created_at DESC');
    }

    public function upsert(array $data): int
    {
        $existing = $this->findByMetaId($data['meta_ad_id']);
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        $data['tenant_id'] = TenantContext::getTenantId();
        return $this->create($data);
    }
}
