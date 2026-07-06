<?php
namespace MarketingAI\Enums;

use InvalidArgumentException;

class RecommendationType
{
    public const BUDGET_OPTIMIZATION = 'BUDGET_OPTIMIZATION';
    public const AUDIENCE_OPTIMIZATION = 'AUDIENCE_OPTIMIZATION';
    public const CREATIVE_OPTIMIZATION = 'CREATIVE_OPTIMIZATION';
    public const CAMPAIGN_ANALYSIS = 'CAMPAIGN_ANALYSIS';
    public const LEAD_ANALYSIS = 'LEAD_ANALYSIS';
    public const ROAS_IMPROVEMENT = 'ROAS_IMPROVEMENT';
    public const CTR_IMPROVEMENT = 'CTR_IMPROVEMENT';
    public const CPA_REDUCTION = 'CPA_REDUCTION';
    public const AUTOMATION_SUGGESTION = 'AUTOMATION_SUGGESTION';
    public const GENERAL = 'GENERAL';

    private string $type;

    private static array $validTypes = [
        self::BUDGET_OPTIMIZATION, self::AUDIENCE_OPTIMIZATION, self::CREATIVE_OPTIMIZATION,
        self::CAMPAIGN_ANALYSIS, self::LEAD_ANALYSIS, self::ROAS_IMPROVEMENT,
        self::CTR_IMPROVEMENT, self::CPA_REDUCTION, self::AUTOMATION_SUGGESTION, self::GENERAL
    ];

    public function __construct(string $type)
    {
        $type = strtoupper($type);
        if (!in_array($type, self::$validTypes)) {
            throw new InvalidArgumentException("Invalid recommendation type: {$type}");
        }
        $this->type = $type;
    }

    public function getValue(): string
    {
        return $this->type;
    }
}
