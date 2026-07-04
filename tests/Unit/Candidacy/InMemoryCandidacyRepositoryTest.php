<?php

namespace Tests\Unit\Candidacy;

use App\Infrastructure\Persistence\InMemoryCandidacyRepository;
use Candidacy\Domain\CandidacyRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\CandidacyRepositoryContractTests;

class InMemoryCandidacyRepositoryTest extends TestCase
{
    use CandidacyRepositoryContractTests;

    protected function createRepository(): CandidacyRepository
    {
        return new InMemoryCandidacyRepository();
    }
}
