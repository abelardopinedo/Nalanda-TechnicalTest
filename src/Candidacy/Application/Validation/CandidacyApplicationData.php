<?php

namespace Candidacy\Application\Validation;

/**
 * Raw candidate input, prior to value-object construction, used as the
 * subject that ValidationRule implementations inspect.
 */
final class CandidacyApplicationData
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $email,
        public readonly int $yearsOfExperience,
        public readonly string $cvText,
    ) {
    }
}
