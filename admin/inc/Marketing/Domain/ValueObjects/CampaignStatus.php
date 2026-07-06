<?php
namespace Marketing\Domain\ValueObjects;

use InvalidArgumentException;

class CampaignStatus
{
    public const DRAFT     = 'DRAFT';
    public const REVIEW    = 'IN_PROCESS';
    public const ACTIVE    = 'ACTIVE';
    public const PAUSED    = 'PAUSED';
    public const ARCHIVED  = 'ARCHIVED';
    public const COMPLETED = 'COMPLETED';

    private string $status;

    private static array $validStatuses = [
        self::DRAFT, self::REVIEW, self::ACTIVE, self::PAUSED, self::ARCHIVED, self::COMPLETED
    ];

    public function __construct(string $status)
    {
        $status = strtoupper($status);
        if (!in_array($status, self::$validStatuses)) {
            throw new InvalidArgumentException("Invalid campaign status: {$status}");
        }
        
        $this->status = $status;
    }

    public function getValue(): string
    {
        return $this->status;
    }

    public function equals(CampaignStatus $other): bool
    {
        return $this->status === $other->getValue();
    }
}
