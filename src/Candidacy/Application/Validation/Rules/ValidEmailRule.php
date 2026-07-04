<?php

namespace Candidacy\Application\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationRule;

final class ValidEmailRule implements ValidationRule
{
    public function evaluate(CandidacyApplicationData $application): RuleResult
    {
        if (filter_var($application->email, FILTER_VALIDATE_EMAIL) === false) {
            return RuleResult::fail(self::class, "\"{$application->email}\" is not a valid email address.");
        }

        return RuleResult::pass(self::class);
    }
}
