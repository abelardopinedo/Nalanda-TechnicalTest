<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use Candidacy\Domain\CandidacyRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CandidacyRepositoryContractTests;
use Tests\TestCase;

class EloquentCandidacyRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CandidacyRepositoryContractTests;

    protected function createRepository(): CandidacyRepository
    {
        return new EloquentCandidacyRepository(new CandidacyMapper());
    }

    protected function ensureEvaluatorExists(string $evaluatorId): void
    {
        EvaluatorModel::query()->firstOrCreate(
            ['id' => $evaluatorId],
            ['name' => 'Contract Test Evaluator', 'email' => $evaluatorId.'@example.test'],
        );
    }
}
