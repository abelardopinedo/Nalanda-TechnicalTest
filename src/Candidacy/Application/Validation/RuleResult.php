<?php

namespace Candidacy\Application\Validation;

final class RuleResult
{
    private function __construct(
        private readonly string $rule,
        private readonly bool $passed,
        private readonly ?string $reason,
    ) {
    }

    public static function pass(string $rule): self
    {
        return new self($rule, true, null);
    }

    public static function fail(string $rule, string $reason): self
    {
        return new self($rule, false, $reason);
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function passed(): bool
    {
        return $this->passed;
    }

    public function failed(): bool
    {
        return ! $this->passed;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
