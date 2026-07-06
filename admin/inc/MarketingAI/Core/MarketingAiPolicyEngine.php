<?php
namespace MarketingAI\Core;

use MarketingAI\Enums\RecommendationStatus;

class MarketingAiPolicyEngine
{
    /**
     * Determines the initial status of a recommendation based on its confidence score.
     */
    public function determineStatus(float $confidenceScore, bool $storePolicyAllowsAuto = false): RecommendationStatus
    {
        if ($confidenceScore >= 95.0) {
            return $storePolicyAllowsAuto ? new RecommendationStatus(RecommendationStatus::APPROVED) : new RecommendationStatus(RecommendationStatus::PENDING);
        }
        
        if ($confidenceScore >= 90.0) {
            // Needs human approval
            return new RecommendationStatus(RecommendationStatus::PENDING);
        }
        
        if ($confidenceScore >= 70.0) {
            // Recommendation only (Insight)
            return new RecommendationStatus(RecommendationStatus::PENDING); // Will be displayed differently in UI
        }
        
        // Below 70% -> Just an insight, archived immediately so it doesn't clutter pending approvals
        return new RecommendationStatus(RecommendationStatus::ARCHIVED);
    }
}
