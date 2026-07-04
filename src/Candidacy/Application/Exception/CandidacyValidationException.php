<?php

namespace Candidacy\Application\Exception;

use RuntimeException;

final class CandidacyValidationException extends RuntimeException
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(private readonly array $reasons)
    {
        parent::__construct('The candidacy application failed validation: '.implode(' ', $reasons));
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
