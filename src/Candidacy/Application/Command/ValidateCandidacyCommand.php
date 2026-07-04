<?php

namespace Candidacy\Application\Command;

final class ValidateCandidacyCommand
{
    public function __construct(
        public readonly string $candidacyId,
    ) {
    }
}
