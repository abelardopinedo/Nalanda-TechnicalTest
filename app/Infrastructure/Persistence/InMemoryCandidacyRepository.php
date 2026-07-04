<?php

namespace App\Infrastructure\Persistence;

use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;
use Illuminate\Support\Str;

class InMemoryCandidacyRepository implements CandidacyRepository
{
    /** @var array<string, Candidacy> */
    private array $candidacies = [];

    public function nextIdentity(): string
    {
        return (string) Str::uuid();
    }

    public function save(Candidacy $candidacy): void
    {
        $candidacy->pullDomainEvents();

        $this->candidacies[$candidacy->id()] = $candidacy;
    }

    public function findById(string $id): ?Candidacy
    {
        return $this->candidacies[$id] ?? null;
    }
}
