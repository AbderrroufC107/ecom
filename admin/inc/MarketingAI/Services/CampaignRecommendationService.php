<?php
namespace MarketingAI\Services;

use MarketingAI\Enums\RecommendationType;

class CampaignRecommendationService
{
    /**
     * Analyzes campaign performance and generates explainable recommendations.
     */
    public function analyze(array $context): array
    {
        $metrics = $context['metrics'] ?? [];
        $roas = $metrics['roas'] ?? 0;
        
        if ($roas < 1.0) {
            return [
                'type' => RecommendationType::CAMPAIGN_ANALYSIS,
                'recommendation' => ['action' => 'PAUSE_AD', 'ad_id' => 'worst_performing_ad_id'],
                'reasoning' => 'The campaign ROAS is below 1.0. Pausing the lowest performing ad will prevent further budget drain.',
                'confidence_score' => 85.5,
                'evidence' => ['current_roas' => $roas, 'threshold' => 1.0],
                'expected_impact' => ['roas_increase' => '+0.2'],
                'risks' => ['volume_drop' => 'May reduce overall impressions.']
            ];
        }
        
        return [];
    }
}
