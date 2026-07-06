<?php
namespace MarketingAI\Enums;

use InvalidArgumentException;

class RecommendationStatus
{
    public const NEW          = 'NEW';
    public const PENDING      = 'PENDING';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const APPROVED     = 'APPROVED';
    public const REJECTED     = 'REJECTED';
    public const APPLIED      = 'APPLIED';
    public const VERIFIED     = 'VERIFIED';
    public const ARCHIVED     = 'ARCHIVED';

    private string $status;

    private static array $validStatuses = [
        self::NEW, self::PENDING, self::UNDER_REVIEW, self::APPROVED, 
        self::REJECTED, self::APPLIED, self::VERIFIED, self::ARCHIVED
    ];

    public function __construct(string $status)
    {
        $status = strtoupper($status);
        if (!in_array($status, self::$validStatuses)) {
            throw new InvalidArgumentException("Invalid recommendation status: {$status}");
        }
        $this->status = $status;
    }

    public function getValue(): string
    {
        return $this->status;
    }
}
