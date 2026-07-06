<?php
namespace Marketing\Application\Ports;

interface MarketingProviderInterface
{
    public function createCampaign(int $tenantId, string $adAccountId, array $data): array;
    public function updateCampaign(int $tenantId, string $adAccountId, string $campaignId, array $data): array;
    public function pauseCampaign(int $tenantId, string $adAccountId, string $campaignId): bool;
    public function resumeCampaign(int $tenantId, string $adAccountId, string $campaignId): bool;
    public function deleteCampaign(int $tenantId, string $adAccountId, string $campaignId): bool;
    
    public function syncCampaigns(int $tenantId, string $adAccountId, array $params = []): array;
    public function syncInsights(int $tenantId, string $adAccountId, string $entityId, string $level, array $params = []): array;
    public function syncLeads(int $tenantId, string $adAccountId, string $formId, array $params = []): array;
    
    public function uploadCreative(int $tenantId, string $adAccountId, string $filePath, string $type): array;
    public function getAdAccounts(int $tenantId): array;
}
