<?php
namespace Marketing\Domain\Events;

use Marketing\Infrastructure\Logging\CorrelationTracker;

class CampaignCreatedEvent implements DomainEventInterface
{
    private int $tenantId;
    private string $campaignId;
    private array $payload;
    private \DateTimeImmutable $occurredOn;
    private string $correlationId;

    public function __construct(int $tenantId, string $campaignId, array $payload)
    {
        $this->tenantId = $tenantId;
        $this->campaignId = $campaignId;
        $this->payload = $payload;
        $this->occurredOn = new \DateTimeImmutable();
        $this->correlationId = CorrelationTracker::getId();
    }

    public function getEventName(): string
    {
        return 'CampaignCreatedEvent';
    }

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'data' => $this->payload
        ];
    }
}
