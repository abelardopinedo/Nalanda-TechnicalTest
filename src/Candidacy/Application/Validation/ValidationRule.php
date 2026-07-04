<?php

namespace Candidacy\Application\Validation;

interface ValidationRule
{
    public function evaluate(CandidacyApplicationData $application): RuleResult;
}
