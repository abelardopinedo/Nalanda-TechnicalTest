<?php

namespace Candidacy\Domain;

use DomainException;

enum CandidacyStatus: string
{
    case RECEIVED = 'received';
    case UNDER_REVIEW = 'under_review';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
    case ASSIGNED = 'assigned';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEIVED => [self::UNDER_REVIEW],
            self::UNDER_REVIEW => [self::VALIDATED, self::REJECTED],
            self::VALIDATED => [self::ASSIGNED],
            self::REJECTED, self::ASSIGNED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function guardTransitionTo(self $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot transition candidacy status from {$this->value} to {$target->value}."
            );
        }
    }
}
