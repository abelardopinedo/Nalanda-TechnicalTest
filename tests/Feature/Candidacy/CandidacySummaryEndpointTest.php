<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CandidacySummaryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_for_a_received_candidacy_shows_not_yet_evaluated_and_runs_no_chain(): void
    {
        $candidacyId = $this->createReceivedCandidacy();

        $response = $this->getJson("/api/v1/candidacies/{$candidacyId}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'received');
        $response->assertJsonPath('data.validation.evaluated', false);
        $response->assertJsonPath('data.validation.outcome', 'not_yet_evaluated');
        $response->assertJsonPath('data.validation.passed', null);
        $response->assertJsonPath('data.validation.failed_reasons', []);
        $response->assertJsonPath('data.derived.time_to_decision_days', null);
        $response->assertJsonPath('data.evaluator', null);

        // Hitting the summary endpoint must not run the ValidationChain or
        // mutate anything: status is unchanged and no validation entry exists.
        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $candidacyId, 'status' => 'received']);
        $this->assertDatabaseCount(ActivityLogModel::class, 1); // only candidacy_registered
    }

    public function test_summary_for_a_validated_candidacy_surfaces_the_stored_outcome(): void
    {
        $candidacyId = $this->createValidatedCandidacy();

        $response = $this->getJson("/api/v1/candidacies/{$candidacyId}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'validated');
        $response->assertJsonPath('data.validation.evaluated', true);
        $response->assertJsonPath('data.validation.outcome', 'validated');
        $response->assertJsonPath('data.validation.passed', true);
        $response->assertJsonPath('data.validation.failed_reasons', []);
        $this->assertNotNull($response->json('data.derived.time_to_decision_days'));
    }

    public function test_summary_for_a_rejected_candidacy_surfaces_the_stored_failed_reasons(): void
    {
        $reason = 'At least 2 years of experience are required, 0 given.';
        $candidacyId = $this->createRejectedCandidacy([$reason]);

        $response = $this->getJson("/api/v1/candidacies/{$candidacyId}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $response->assertJsonPath('data.validation.evaluated', true);
        $response->assertJsonPath('data.validation.outcome', 'rejected');
        $response->assertJsonPath('data.validation.passed', false);
        $response->assertJsonPath('data.validation.failed_reasons', [$reason]);
        $this->assertNotNull($response->json('data.derived.time_to_decision_days'));
    }

    public function test_summary_for_an_assigned_candidacy_populates_the_evaluator_block(): void
    {
        $evaluator = EvaluatorModel::factory()->create(['name' => 'Alice Reviewer']);
        $candidacyId = $this->createAssignedCandidacy($evaluator->id);

        $response = $this->getJson("/api/v1/candidacies/{$candidacyId}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'assigned');
        $response->assertJsonPath('data.evaluator.name', 'Alice Reviewer');
        $this->assertNotNull($response->json('data.evaluator.assigned_at'));
        // Assignment presupposes an earlier successful validation.
        $response->assertJsonPath('data.validation.outcome', 'validated');
    }

    public function test_summary_populates_time_to_decision_for_a_candidacy_built_from_the_validated_factory_state(): void
    {
        $candidacy = CandidacyModel::factory()->eligible()->validated()->create();

        $response = $this->getJson("/api/v1/candidacies/{$candidacy->id}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.validation.outcome', 'validated');
        $timeToDecision = $response->json('data.derived.time_to_decision_days');
        $this->assertNotNull($timeToDecision);
        $this->assertGreaterThan(0, $timeToDecision);
    }

    public function test_summary_populates_time_to_decision_for_a_candidacy_built_from_the_rejected_factory_state(): void
    {
        $candidacy = CandidacyModel::factory()->ineligible()->rejected()->create();

        $response = $this->getJson("/api/v1/candidacies/{$candidacy->id}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.validation.outcome', 'rejected');
        $this->assertNotEmpty($response->json('data.validation.failed_reasons'));
        $timeToDecision = $response->json('data.derived.time_to_decision_days');
        $this->assertNotNull($timeToDecision);
        $this->assertGreaterThan(0, $timeToDecision);
    }

    public function test_it_returns_404_for_an_unknown_candidacy(): void
    {
        $response = $this->getJson('/api/v1/candidacies/missing-id/summary');

        $response->assertStatus(404);
    }

    #[DataProvider('experienceTierBoundaries')]
    public function test_experience_tier_boundaries(int $years, string $expectedTier): void
    {
        $candidacyId = $this->createReceivedCandidacy(years: $years);

        $response = $this->getJson("/api/v1/candidacies/{$candidacyId}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.derived.experience_tier', $expectedTier);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function experienceTierBoundaries(): array
    {
        return [
            '2 years is Junior' => [2, 'Junior'],
            '3 years is Mid' => [3, 'Mid'],
            '6 years is Senior' => [6, 'Senior'],
        ];
    }

    private function createReceivedCandidacy(int $years = 4): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience($years),
            new CvText(str_repeat('Experienced backend engineer. ', 5)),
        );

        $repository->save($candidacy);

        return $candidacy->id();
    }

    private function createValidatedCandidacy(): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText(str_repeat('Experienced backend engineer. ', 5)),
        );

        $candidacy->validate();
        $candidacy->recordValidationOutcome([]);
        $repository->save($candidacy);

        return $candidacy->id();
    }

    /**
     * @param  list<string>  $reasons
     */
    private function createRejectedCandidacy(array $reasons): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(0),
            new CvText('N/A'),
        );

        $candidacy->reject();
        $candidacy->recordValidationOutcome($reasons);
        $repository->save($candidacy);

        return $candidacy->id();
    }

    private function createAssignedCandidacy(string $evaluatorId): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText(str_repeat('Experienced backend engineer. ', 5)),
        );

        $candidacy->validate();
        $candidacy->recordValidationOutcome([]);
        $repository->save($candidacy);

        $candidacy->assignEvaluator($evaluatorId);
        $repository->save($candidacy);

        return $candidacy->id();
    }
}
