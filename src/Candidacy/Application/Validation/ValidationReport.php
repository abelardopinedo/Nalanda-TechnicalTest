<?php

namespace Candidacy\Application\Validation;

final class ValidationReport
{
    /**
     * @param list<RuleResult> $passed
     * @param list<RuleResult> $failed
     */
    public function __construct(
        private readonly array $passed,
        private readonly array $failed,
    ) {
    }

    public function isValid(): bool
    {
        return $this->failed === [];
    }

    /**
     * @return list<RuleResult>
     */
    public function passed(): array
    {
        return $this->passed;
    }

    /**
     * @return list<RuleResult>
     */
    public function failed(): array
    {
        return $this->failed;
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return array_map(
            static fn (RuleResult $result): string => $result->reason(),
            $this->failed,
        );
    }
}
