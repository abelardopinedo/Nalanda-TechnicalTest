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
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignEvaluatorEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_an_evaluator_to_a_validated_candidacy_and_logs_the_event(): void
    {
        $evaluatorId = $this->createEvaluator();
        $candidacyId = $this->createValidatedCandidacy();

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => $evaluatorId,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'assigned');
        $response->assertJsonPath('data.evaluator_id', $evaluatorId);
        $this->assertNotNull($response->json('data.assigned_at'));

        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $candidacyId,
            'status' => 'assigned',
            'evaluator_id' => $evaluatorId,
        ]);

        $this->assertDatabaseHas(ActivityLogModel::class, [
            'candidacy_id' => $candidacyId,
            'evaluator_id' => $evaluatorId,
            'action' => 'evaluator_assigned',
        ]);
    }

    public function test_it_rejects_an_evaluator_id_that_does_not_exist(): void
    {
        $candidacyId = $this->createValidatedCandidacy();

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evaluator_id']);
    }

    public function test_it_returns_404_for_an_unknown_candidacy(): void
    {
        $evaluatorId = $this->createEvaluator();

        $response = $this->postJson('/api/v1/candidacies/missing-id/evaluator', [
            'evaluator_id' => $evaluatorId,
        ]);

        $response->assertStatus(404);
    }

    public function test_it_returns_a_conflict_when_the_candidacy_is_not_yet_validated(): void
    {
        $evaluatorId = $this->createEvaluator();
        $candidacyId = $this->createReceivedCandidacy();

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => $evaluatorId,
        ]);

        $response->assertStatus(409);
    }

    private function createEvaluator(): string
    {
        $evaluator = EvaluatorModel::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Alice Reviewer',
            'email' => Str::uuid().'@example.test',
        ]);

        return $evaluator->id;
    }

    private function createValidatedCandidacy(): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = $this->registerCandidacy($repository);
        $candidacy->startReview();
        $candidacy->validate();
        $repository->save($candidacy);

        return $candidacy->id();
    }

    private function createReceivedCandidacy(): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = $this->registerCandidacy($repository);
        $repository->save($candidacy);

        return $candidacy->id();
    }

    private function registerCandidacy(EloquentCandidacyRepository $repository): Candidacy
    {
        return Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText('Some CV content.'),
        );
    }
}
