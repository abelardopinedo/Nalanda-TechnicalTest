<?php

namespace Candidacy\Domain\Event;

use DateTimeImmutable;

final class EvaluatorAssigned
{
    public readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $candidacyId,
        public readonly string $evaluatorId,
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }
}
