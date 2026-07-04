<?php

namespace Candidacy\Domain\Event;

use DateTimeImmutable;

final class CandidacyRegistered
{
    public readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $candidacyId,
        public readonly string $email,
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }
}
