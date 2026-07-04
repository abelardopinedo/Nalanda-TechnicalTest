<?php

namespace Candidacy\Application\Command;

final class AssignEvaluatorCommand
{
    public function __construct(
        public readonly string $candidacyId,
        public readonly string $evaluatorId,
    ) {
    }
}
