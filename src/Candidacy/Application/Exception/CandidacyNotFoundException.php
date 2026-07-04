<?php

namespace Candidacy\Application\Exception;

use RuntimeException;

final class CandidacyNotFoundException extends RuntimeException
{
    public function __construct(string $candidacyId)
    {
        parent::__construct("Candidacy \"{$candidacyId}\" was not found.");
    }
}
