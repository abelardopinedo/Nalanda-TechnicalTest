<?php

namespace Candidacy\Domain;

final class CvText
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return trim($this->value) === '';
    }
}
