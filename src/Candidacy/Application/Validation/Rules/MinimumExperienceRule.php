<?php

namespace Candidacy\Application\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationRule;

final class MinimumExperienceRule implements ValidationRule
{
    public function __construct(private readonly int $minimumYears = 2)
    {
    }

    public function evaluate(CandidacyApplicationData $application): RuleResult
    {
        if ($application->yearsOfExperience < $this->minimumYears) {
            return RuleResult::fail(
                self::class,
                "At least {$this->minimumYears} years of experience are required, {$application->yearsOfExperience} given."
            );
        }

        return RuleResult::pass(self::class);
    }
}
