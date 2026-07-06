<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class LeadFormRepository extends BaseRepository
{
    protected string $table = 'tbl_meta_lead_forms';

    public function findByMetaId(string $metaFormId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE meta_form_id = ? AND tenant_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$metaFormId, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function upsert(array $data): int
    {
        $existing = $this->findByMetaId($data['meta_form_id']);
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        $data['tenant_id'] = TenantContext::getTenantId();
        return $this->create($data);
    }
}
