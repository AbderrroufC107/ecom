<?php

namespace Ecom\Audit;

use PDO;

class AuditService
{
    public static function log(PDO $pdo, int $storeId, string $action, string $entityType = '',
        int $entityId = 0, ?array $oldValue = null, ?array $newValue = null,
        ?int $staffId = null): int
    {
        return AuditRepository::insert(
            $pdo, $storeId, $action, $entityType, $entityId,
            $oldValue, $newValue, $staffId
        );
    }

    public static function getLogs(PDO $pdo, ?int $storeId = null, int $page = 1,
        int $perPage = 50, ?string $action = null, ?string $entityType = null,
        ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return AuditRepository::query(
            $pdo, $storeId, $action, $entityType,
            $dateFrom, $dateTo, $page, $perPage
        );
    }

    public static function getLogCount(PDO $pdo, ?int $storeId = null, ?string $action = null,
        ?string $entityType = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        return AuditRepository::count($pdo, $storeId, $action, $entityType, $dateFrom, $dateTo);
    }

    public static function getRecentActions(PDO $pdo, int $storeId, int $limit = 10): array
    {
        return AuditRepository::getRecentByStore($pdo, $storeId, $limit);
    }
}
