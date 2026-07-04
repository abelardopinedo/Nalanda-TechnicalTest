<?php

namespace Candidacy\Application\UseCase;

use Candidacy\Application\Validation\ValidationReport;
use Candidacy\Domain\Candidacy;

final class CandidacyValidationOutcome
{
    public function __construct(
        public readonly Candidacy $candidacy,
        public readonly ValidationReport $report,
    ) {
    }
}
