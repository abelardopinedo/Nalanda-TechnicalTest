<?php

namespace Candidacy\Domain;

use InvalidArgumentException;

final class YearsOfExperience
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Years of experience cannot be negative: {$value}");
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isAtLeast(int $years): bool
    {
        return $this->value >= $years;
    }
}
