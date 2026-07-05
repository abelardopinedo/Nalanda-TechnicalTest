<?php

namespace Candidacy\Application\Command;

final class BulkAssignEvaluatorCommand
{
    /**
     * @param  list<string>  $candidacyIds
     */
    public function __construct(
        public readonly string $evaluatorId,
        public readonly array $candidacyIds,
    ) {
    }
}
