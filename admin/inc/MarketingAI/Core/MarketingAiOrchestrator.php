<?php
namespace MarketingAI\Core;

use MarketingAI\Enums\RecommendationType;
use MarketingAI\Guardrails\MarketingAiGuardrails;
use MarketingAI\Simulation\RecommendationSimulator;
use Marketing\Domain\Events\DomainEventInterface;
use PDO;

class MarketingAiOrchestrator
{
    private PDO $pdo;
    private MarketingAiContextBuilder $contextBuilder;
    private MarketingAiPolicyEngine $policyEngine;
    private MarketingAiGuardrails $guardrails;
    private RecommendationSimulator $simulator;

    public function __construct(
        PDO $pdo,
        MarketingAiContextBuilder $contextBuilder,
        MarketingAiPolicyEngine $policyEngine,
        MarketingAiGuardrails $guardrails,
        RecommendationSimulator $simulator
    ) {
        $this->pdo = $pdo;
        $this->contextBuilder = $contextBuilder;
        $this->policyEngine = $policyEngine;
        $this->guardrails = $guardrails;
        $this->simulator = $simulator;
    }

    /**
     * Entry point for an EventBus hook
     */
    public function handleEvent(DomainEventInterface $event): void
    {
        // Ignore events that don't need AI optimization
        $supportedEvents = ['CampaignCreatedEvent', 'CampaignUpdatedEvent', 'SyncCompletedEvent'];
        if (!in_array($event->getEventName(), $supportedEvents)) {
            return;
        }

        $payload = $event->getPayload();
        $tenantId = $event->getTenantId();
        
        // 1. Build Context
        $context = $this->contextBuilder->buildContext('campaign', $payload['campaign_id'] ?? '', []);
        
        // 2. Pass context to specific recommendation services based on some rules...
        // For simplicity, we assume we invoke BudgetOptimizationService directly here
        $budgetService = new \MarketingAI\Services\BudgetOptimizationService();
        $recommendationData = $budgetService->analyze($context);
        
        if (!empty($recommendationData)) {
            $this->processRecommendation($tenantId, $payload['campaign_id'] ?? null, clone $recommendationData['type'], $recommendationData, $context);
        }
    }

    private function processRecommendation(int $tenantId, ?string $campaignId, RecommendationType $type, array $rec, array $context): void
    {
        // 3. Guardrails check
        if (!$this->guardrails->isSafe($type, $rec['recommendation'], $context)) {
            return; // Blocked by guardrails
        }
        
        // 4. Simulate Outcome
        $simulatedResults = $this->simulator->simulate($context['metrics'] ?? [], $rec['recommendation']);
        
        // 5. Determine State based on Confidence Policy
        $status = $this->policyEngine->determineStatus($rec['confidence_score'] ?? 0);
        
        // 6. Save to Memory Database
        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_marketing_ai_recommendations (
                tenant_id, campaign_id, recommendation_type, recommendation, reasoning, 
                confidence_score, evidence, expected_impact, risks, status, model_provider
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'system_engine')
        ");
        
        $stmt->execute([
            $tenantId,
            $campaignId,
            $type->getValue(),
            json_encode($rec['recommendation']),
            $rec['reasoning'],
            $rec['confidence_score'],
            json_encode($rec['evidence']),
            json_encode(array_merge($rec['expected_impact'] ?? [], $simulatedResults)),
            json_encode($rec['risks']),
            $status->getValue()
        ]);
    }
}
