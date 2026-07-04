<?php

namespace Candidacy\Application\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationRule;

/**
 * Extension example: demonstrates that new rules can be added to the chain
 * by registering the class in config, with no changes to existing rules.
 */
final class CvMinimumLengthRule implements ValidationRule
{
    public function __construct(private readonly int $minimumLength = 50)
    {
    }

    public function evaluate(CandidacyApplicationData $application): RuleResult
    {
        if (mb_strlen(trim($application->cvText)) < $this->minimumLength) {
            return RuleResult::fail(
                self::class,
                "The CV must be at least {$this->minimumLength} characters long."
            );
        }

        return RuleResult::pass(self::class);
    }
}
