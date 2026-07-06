<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class CreativeRepository extends BaseRepository
{
    protected string $table = 'tbl_meta_creatives';

    public function findByMetaId(string $metaCreativeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE meta_creative_id = ? AND tenant_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$metaCreativeId, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByProduct(int $productId): array
    {
        return $this->findAll(['product_id' => $productId, 'is_deleted' => 0], 'created_at DESC');
    }

    public function findByType(string $type): array
    {
        return $this->findAll(['type' => $type, 'is_deleted' => 0], 'created_at DESC');
    }

    public function upsert(array $data): int
    {
        if (!empty($data['meta_creative_id'])) {
            $existing = $this->findByMetaId($data['meta_creative_id']);
            if ($existing) {
                $this->update($existing['id'], $data);
                return $existing['id'];
            }
        }
        $data['tenant_id'] = TenantContext::getTenantId();
        return $this->create($data);
    }
}
