<?php

namespace Candidacy\Application\UseCase;

final class BulkAssignmentResult
{
    /**
     * @param  list<string>  $assigned
     * @param  list<SkippedAssignment>  $skipped
     */
    public function __construct(
        public readonly array $assigned,
        public readonly array $skipped,
    ) {
    }
}
