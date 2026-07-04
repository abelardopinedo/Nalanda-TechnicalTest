<?php

namespace Candidacy\Domain;

interface CandidacyRepository
{
    public function nextIdentity(): string;

    public function save(Candidacy $candidacy): void;

    public function findById(string $id): ?Candidacy;
}
