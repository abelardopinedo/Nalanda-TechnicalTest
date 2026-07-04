<?php

namespace Candidacy\Application\Command;

final class RegisterCandidacyCommand
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $email,
        public readonly int $yearsOfExperience,
        public readonly string $cvText,
    ) {
    }
}
