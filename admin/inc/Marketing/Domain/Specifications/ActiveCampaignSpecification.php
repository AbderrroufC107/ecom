<?php
namespace Marketing\Domain\Specifications;

use Marketing\Domain\ValueObjects\CampaignStatus;

class ActiveCampaignSpecification implements SpecificationInterface
{
    public function isSatisfiedBy($entity): bool
    {
        // Assuming $entity is an array or object with a 'status' property
        $status = is_array($entity) ? ($entity['status'] ?? '') : ($entity->status ?? '');
        
        return $status === CampaignStatus::ACTIVE;
    }
}
