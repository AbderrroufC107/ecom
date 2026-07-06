<?php
namespace Marketing\Repositories;

use SaaS\Repositories\BaseRepository;
use SaaS\TenantContext;
use PDO;

class SyncLogRepository extends BaseRepository
{
    protected string $table = 'tbl_meta_sync_logs';

    public function startSync(string $syncType, string $direction = 'IMPORT', ?string $entityId = null): int
    {
        $data = [
            'tenant_id'  => TenantContext::getTenantId(),
            'sync_type'  => $syncType,
            'direction'  => $direction,
            'status'     => 'RUNNING',
            'entity_id'  => $entityId,
            'started_at' => date('Y-m-d H:i:s'),
        ];
        return $this->create($data);
    }

    public function completeSync(int $logId, int $synced, int $failed = 0, ?string $error = null): void
    {
        $this->update($logId, [
            'status'          => $error ? 'FAILED' : ($failed > 0 ? 'PARTIAL' : 'SUCCESS'),
            'records_synced'  => $synced,
            'records_failed'  => $failed,
            'error_message'   => $error,
            'completed_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function getRecentLogs(int $limit = 20): array
    {
        $tenantId = TenantContext::getTenantId();
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE tenant_id = ? ORDER BY started_at DESC LIMIT ?"
        );
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
