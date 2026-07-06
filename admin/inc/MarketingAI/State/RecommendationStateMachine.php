<?php
namespace MarketingAI\State;

use MarketingAI\Enums\RecommendationStatus;
use DomainException;

class RecommendationStateMachine
{
    private static array $transitions = [
        RecommendationStatus::NEW => [
            RecommendationStatus::PENDING
        ],
        RecommendationStatus::PENDING => [
            RecommendationStatus::UNDER_REVIEW,
            RecommendationStatus::APPROVED, // if auto-approval allows
            RecommendationStatus::REJECTED,
            RecommendationStatus::ARCHIVED
        ],
        RecommendationStatus::UNDER_REVIEW => [
            RecommendationStatus::APPROVED,
            RecommendationStatus::REJECTED
        ],
        RecommendationStatus::APPROVED => [
            RecommendationStatus::APPLIED,
            RecommendationStatus::ARCHIVED // if manual intervention skips it
        ],
        RecommendationStatus::APPLIED => [
            RecommendationStatus::VERIFIED,
            RecommendationStatus::REJECTED // if feedback says it failed miserably
        ],
        RecommendationStatus::REJECTED => [
            RecommendationStatus::ARCHIVED
        ],
        RecommendationStatus::VERIFIED => [
            RecommendationStatus::ARCHIVED
        ],
        RecommendationStatus::ARCHIVED => [
            // End state
        ]
    ];

    public static function transition(RecommendationStatus $current, RecommendationStatus $new): RecommendationStatus
    {
        $currentState = $current->getValue();
        $newState = $new->getValue();

        if (!isset(self::$transitions[$currentState]) || !in_array($newState, self::$transitions[$currentState])) {
            throw new DomainException("Invalid AI Recommendation state transition from {$currentState} to {$newState}");
        }

        return $new;
    }
}
