<?php
namespace MarketingAI\Guardrails;

use MarketingAI\Enums\RecommendationType;

class MarketingAiGuardrails
{
    /**
     * Inspects a recommendation payload to ensure it does not violate any safety policies.
     * Returns true if safe, false if blocked.
     */
    public function isSafe(RecommendationType $type, array $payload, array $context): bool
    {
        // 1. Prevent negative budget
        if (isset($payload['budget']) && $payload['budget'] <= 0) {
            return false;
        }
        
        // 2. Prevent exceeding max allowed budget (e.g. 5x current budget jump)
        if (isset($payload['budget']) && isset($context['current_budget'])) {
            if ($payload['budget'] > ($context['current_budget'] * 5)) {
                return false;
            }
        }
        
        // 3. Prevent pausing ALL active campaigns
        if ($type->getValue() === RecommendationType::CAMPAIGN_ANALYSIS) {
            if (isset($payload['action']) && $payload['action'] === 'PAUSE_ALL') {
                return false;
            }
        }
        
        // 4. Prevent deleting campaigns (AI should never delete)
        if (isset($payload['action']) && $payload['action'] === 'DELETE') {
            return false;
        }

        // Additional enterprise guardrails go here...
        
        return true; // Passed all guardrails
    }
}
