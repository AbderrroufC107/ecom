<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class AdAccountRepository extends BaseRepository
{
    protected string $table      = 'tbl_meta_ad_accounts';
    protected string $primaryKey = 'id';
    protected bool   $useSoftDelete = true;

    public function findByMetaAccountId(string $accountId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE account_id = ? AND tenant_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$accountId, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getActive(): array
    {
        return $this->findAll(['status' => 'ACTIVE', 'is_deleted' => 0], 'created_at DESC');
    }
}
