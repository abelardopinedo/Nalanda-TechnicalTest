<?php

namespace Candidacy\Application\Validation;

/**
 * A Chain of Responsibility that runs every registered rule against the
 * subject and accumulates all results, rather than short-circuiting on the
 * first failure — so callers can report every reason validation failed.
 */
final class ValidationChain
{
    /**
     * @param list<ValidationRule> $rules
     */
    public function __construct(private readonly array $rules)
    {
    }

    public function run(CandidacyApplicationData $application): ValidationReport
    {
        $passed = [];
        $failed = [];

        foreach ($this->rules as $rule) {
            $result = $rule->evaluate($application);

            if ($result->passed()) {
                $passed[] = $result;
            } else {
                $failed[] = $result;
            }
        }

        return new ValidationReport($passed, $failed);
    }
}
