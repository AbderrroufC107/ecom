<?php
namespace Marketing\Application\Commands;

use Marketing\Application\Ports\MarketingProviderInterface;
use Marketing\Application\UnitOfWork\UnitOfWorkInterface;
use Marketing\Domain\ValueObjects\CampaignBudget;
use Marketing\Domain\ValueObjects\CampaignStatus;
use Marketing\Domain\Events\CampaignCreatedEvent;

class CreateCampaignHandler
{
    private MarketingProviderInterface $provider;
    private UnitOfWorkInterface $uow;
    
    // In a real DI container setup, these are injected.
    public function __construct(MarketingProviderInterface $provider, UnitOfWorkInterface $uow)
    {
        $this->provider = $provider;
        $this->uow = $uow;
    }

    public function handle(CreateCampaignCommand $command): array
    {
        // 1. Validate domain objects
        $budget = new CampaignBudget($command->dailyBudget);
        $status = new CampaignStatus(CampaignStatus::DRAFT); // initially DRAFT
        
        $this->uow->begin();
        try {
            // 2. Call provider (or push to outbox if strictly async)
            // For CQRS with Outbox, we might just save to local DB and Outbox, 
            // then the worker actually calls the provider.
            // Let's assume we store it locally first to get a local ID.
            
            $localData = [
                'name' => $command->name,
                'objective' => $command->objective,
                'budget' => $budget->getAmount(),
                'status' => $status->getValue()
            ];
            
            // Register domain event
            $event = new CampaignCreatedEvent(
                $command->tenantId, 
                'temp_local_id', 
                $localData
            );
            $this->uow->registerEvent($event);
            
            // 3. Commit (This will save to DB + Outbox + Publish Events)
            $this->uow->commit();
            
            return ['success' => true, 'local_data' => $localData];
        } catch (\Exception $e) {
            $this->uow->rollback();
            throw $e;
        }
    }
}
