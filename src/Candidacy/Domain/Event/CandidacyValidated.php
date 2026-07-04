<?php

namespace Candidacy\Domain\Event;

use Candidacy\Domain\CandidacyStatus;
use DateTimeImmutable;

final class CandidacyValidated
{
    public readonly DateTimeImmutable $occurredOn;

    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public readonly string $candidacyId,
        public readonly CandidacyStatus $outcome,
        public readonly array $reasons,
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }
}
