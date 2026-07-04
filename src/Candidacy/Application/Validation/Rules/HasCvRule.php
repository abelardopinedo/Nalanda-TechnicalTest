<?php

namespace Candidacy\Application\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationRule;

final class HasCvRule implements ValidationRule
{
    public function evaluate(CandidacyApplicationData $application): RuleResult
    {
        if (trim($application->cvText) === '') {
            return RuleResult::fail(self::class, 'A CV is required.');
        }

        return RuleResult::pass(self::class);
    }
}
