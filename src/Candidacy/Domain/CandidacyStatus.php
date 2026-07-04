<?php

namespace Candidacy\Domain;

use Candidacy\Domain\Exception\InvalidCandidacyStatusTransition;

enum CandidacyStatus: string
{
    case RECEIVED = 'received';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
    case ASSIGNED = 'assigned';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEIVED => [self::VALIDATED, self::REJECTED],
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
            throw InvalidCandidacyStatusTransition::from($this, $target);
        }
    }
}
