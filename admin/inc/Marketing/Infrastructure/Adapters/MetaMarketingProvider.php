<?php
namespace Marketing\Infrastructure\Adapters;

use Marketing\Application\Ports\MarketingProviderInterface;
use PDO;

class MetaMarketingProvider implements MarketingProviderInterface
{
    private PDO $pdo;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // Ideally this comes from a ConfigurationProvider
        $this->apiVersion = 'v20.0';
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    private function getAccessToken(int $tenantId, string $adAccountId): string
    {
        // Mocking token retrieval via SecretManager for demonstration
        return 'mock_token_for_' . $adAccountId;
    }

    public function createCampaign(int $tenantId, string $adAccountId, array $data): array
    {
        // Outbox worker calls this eventually
        // Implementation similar to MarketingApiClient->post()
        return ['id' => 'remote_meta_id_123', 'success' => true];
    }

    public function updateCampaign(int $tenantId, string $adAccountId, string $campaignId, array $data): array
    {
        return ['success' => true];
    }

    public function pauseCampaign(int $tenantId, string $adAccountId, string $campaignId): bool
    {
        return true;
    }

    public function resumeCampaign(int $tenantId, string $adAccountId, string $campaignId): bool
    {
        return true;
    }

    public function deleteCampaign(int $tenantId, string $adAccountId, string $campaignId): bool
    {
        return true;
    }

    public function syncCampaigns(int $tenantId, string $adAccountId, array $params = []): array
    {
        return [];
    }

    public function syncInsights(int $tenantId, string $adAccountId, string $entityId, string $level, array $params = []): array
    {
        return [];
    }

    public function syncLeads(int $tenantId, string $adAccountId, string $formId, array $params = []): array
    {
        return [];
    }

    public function uploadCreative(int $tenantId, string $adAccountId, string $filePath, string $type): array
    {
        return ['id' => 'creative_id_456'];
    }

    public function getAdAccounts(int $tenantId): array
    {
        return [];
    }
}
