<?php
namespace Marketing\Domain\Events;

interface DomainEventInterface
{
    public function getEventName(): string;
    public function getOccurredOn(): \DateTimeImmutable;
    public function getCorrelationId(): string;
    public function getTenantId(): int;
    public function getPayload(): array;
}
