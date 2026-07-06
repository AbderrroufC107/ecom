<?php
namespace MarketingAI\Core;

class MarketingAiContextBuilder
{
    /**
     * Builds a rich context object for the AI model based on the entity and its performance.
     */
    public function buildContext(string $entityType, string $entityId, array $metrics): array
    {
        // 1. Gather core information (mocked here, in reality fetched via Repositories)
        $context = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metrics' => [
                'roas' => $metrics['roas'] ?? 0,
                'roi' => $metrics['roi'] ?? 0,
                'ctr' => $metrics['ctr'] ?? 0,
                'cpa' => $metrics['cpa'] ?? 0,
                'spend' => $metrics['spend'] ?? 0,
                'revenue' => $metrics['revenue'] ?? 0,
            ],
            'audience_info' => 'Broad',
            'funnel_stage' => 'TOFU',
            'previous_recommendations' => [],
            'current_automation_rules' => []
        ];

        return $context;
    }
}
