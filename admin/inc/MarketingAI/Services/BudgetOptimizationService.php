<?php
namespace MarketingAI\Services;

use MarketingAI\Enums\RecommendationType;

class BudgetOptimizationService
{
    public function analyze(array $context): array
    {
        $metrics = $context['metrics'] ?? [];
        $roas = $metrics['roas'] ?? 0;
        $spend = $metrics['spend'] ?? 0;
        
        if ($roas > 3.0 && $spend > 0) {
            return [
                'type' => RecommendationType::BUDGET_OPTIMIZATION,
                'recommendation' => ['action' => 'INCREASE_BUDGET', 'amount' => $spend * 0.20],
                'reasoning' => 'Highly profitable campaign. Scaling budget by 20% is recommended to capture more volume while maintaining ROAS.',
                'confidence_score' => 92.0,
                'evidence' => ['current_roas' => $roas, 'threshold' => 3.0],
                'expected_impact' => ['revenue_increase' => 'Expected +15% revenue'],
                'risks' => ['efficiency_drop' => 'CPA might slightly increase.']
            ];
        }
        
        return [];
    }
}
