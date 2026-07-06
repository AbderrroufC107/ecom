<?php
namespace MarketingAI\Simulation;

class RecommendationSimulator
{
    /**
     * Simulates the expected outcome of a recommendation.
     * Returns an array with Predicted ROAS, Expected Revenue, and Risk factor.
     */
    public function simulate(array $currentMetrics, array $recommendationPayload): array
    {
        $currentRoas = $currentMetrics['roas'] ?? 1.0;
        $currentSpend = $currentMetrics['spend'] ?? 0;
        
        // Mock simulation logic
        $predictedRoas = $currentRoas * 1.15; // Assume 15% improvement
        $expectedRisk = 'LOW';
        
        if (isset($recommendationPayload['budget']) && $recommendationPayload['budget'] > ($currentSpend * 2)) {
            $expectedRisk = 'HIGH'; // Doubling budget is high risk
            $predictedRoas = $currentRoas * 0.9; // Usually efficiency drops with fast scaling
        }

        return [
            'predicted_roas' => round($predictedRoas, 2),
            'expected_revenue' => round($predictedRoas * ($recommendationPayload['budget'] ?? $currentSpend), 2),
            'risk_level' => $expectedRisk
        ];
    }
}
