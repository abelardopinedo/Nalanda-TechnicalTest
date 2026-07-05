<?php

namespace Candidacy\Application\UseCase;

final class SkippedAssignment
{
    public function __construct(
        public readonly string $candidacyId,
        public readonly string $reason,
    ) {
    }
}
