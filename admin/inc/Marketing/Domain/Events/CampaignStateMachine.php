<?php
namespace Marketing\Domain\Events;

use Marketing\Domain\ValueObjects\CampaignStatus;

class CampaignStateMachine
{
    /**
     * Define the valid transitions from a given state.
     */
    private static array $transitions = [
        CampaignStatus::DRAFT => [
            CampaignStatus::REVIEW,
            CampaignStatus::ACTIVE,
            CampaignStatus::ARCHIVED
        ],
        CampaignStatus::REVIEW => [
            CampaignStatus::ACTIVE,
            CampaignStatus::PAUSED,
            CampaignStatus::ARCHIVED
        ],
        CampaignStatus::ACTIVE => [
            CampaignStatus::PAUSED,
            CampaignStatus::COMPLETED,
            CampaignStatus::ARCHIVED
        ],
        CampaignStatus::PAUSED => [
            CampaignStatus::ACTIVE,
            CampaignStatus::ARCHIVED
        ],
        CampaignStatus::COMPLETED => [
            CampaignStatus::ARCHIVED
        ],
        CampaignStatus::ARCHIVED => [
            // No exit from archived
        ]
    ];

    /**
     * Checks if a transition is valid and returns the new CampaignStatus.
     * Throws an exception if invalid.
     */
    public static function transition(CampaignStatus $current, CampaignStatus $new): CampaignStatus
    {
        $currentState = $current->getValue();
        $newState = $new->getValue();

        if (!isset(self::$transitions[$currentState]) || !in_array($newState, self::$transitions[$currentState])) {
            throw new \DomainException("Invalid state transition from {$currentState} to {$newState}");
        }

        return $new;
    }
}
