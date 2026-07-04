<?php

namespace Candidacy\Domain\Exception;

use Candidacy\Domain\CandidacyStatus;
use DomainException;

final class InvalidCandidacyStatusTransition extends DomainException
{
    public static function from(CandidacyStatus $current, CandidacyStatus $target): self
    {
        return new self(
            "Cannot transition candidacy status from {$current->value} to {$target->value}."
        );
    }
}
