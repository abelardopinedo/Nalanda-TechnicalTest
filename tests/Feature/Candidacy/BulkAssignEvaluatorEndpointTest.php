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
use Tests\Support\ClearsCandidacyReadCache;
use Tests\TestCase;

class BulkAssignEvaluatorEndpointTest extends TestCase
{
    use RefreshDatabase;
    use ClearsCandidacyReadCache;

    public function test_it_assigns_every_validated_candidacy_in_the_batch_and_logs_each_one(): void
    {
        $evaluatorId = $this->createEvaluator();
        $candidacyOne = $this->createValidatedCandidacy();
        $candidacyTwo = $this->createValidatedCandidacy();

        $response = $this->postJson("/api/v1/evaluators/{$evaluatorId}/assign-bulk", [
            'candidacy_ids' => [$candidacyOne, $candidacyTwo],
        ]);

        $response->assertOk();
        $response->assertJson([
            'assigned' => [$candidacyOne, $candidacyTwo],
            'skipped' => [],
        ]);

        foreach ([$candidacyOne, $candidacyTwo] as $candidacyId) {
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
    }

    public function test_a_non_validated_candidacy_in_the_batch_is_skipped_without_failing_the_request(): void
    {
        $evaluatorId = $this->createEvaluator();
        $validCandidacy = $this->createValidatedCandidacy();
        $receivedCandidacy = $this->createReceivedCandidacy();

        $response = $this->postJson("/api/v1/evaluators/{$evaluatorId}/assign-bulk", [
            'candidacy_ids' => [$validCandidacy, $receivedCandidacy],
        ]);

        $response->assertOk();
        $response->assertJson([
            'assigned' => [$validCandidacy],
            'skipped' => [
                ['id' => $receivedCandidacy, 'reason' => 'not_validated:received'],
            ],
        ]);

        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $validCandidacy,
            'status' => 'assigned',
            'evaluator_id' => $evaluatorId,
        ]);
        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $receivedCandidacy,
            'status' => 'received',
            'evaluator_id' => null,
        ]);
    }

    public function test_an_unknown_candidacy_id_in_the_batch_is_skipped_as_not_found(): void
    {
        $evaluatorId = $this->createEvaluator();
        $validCandidacy = $this->createValidatedCandidacy();

        $response = $this->postJson("/api/v1/evaluators/{$evaluatorId}/assign-bulk", [
            'candidacy_ids' => [$validCandidacy, 'missing-id'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'assigned' => [$validCandidacy],
            'skipped' => [
                ['id' => 'missing-id', 'reason' => 'not_found'],
            ],
        ]);
    }

    public function test_it_returns_404_for_an_unknown_evaluator(): void
    {
        $candidacyId = $this->createValidatedCandidacy();

        $response = $this->postJson('/api/v1/evaluators/missing-id/assign-bulk', [
            'candidacy_ids' => [$candidacyId],
        ]);

        $response->assertStatus(404);
    }

    public function test_it_rejects_an_empty_candidacy_ids_list(): void
    {
        $evaluatorId = $this->createEvaluator();

        $response = $this->postJson("/api/v1/evaluators/{$evaluatorId}/assign-bulk", [
            'candidacy_ids' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['candidacy_ids']);
    }

    /**
     * Simulates two overlapping bulk-assign requests arriving one after the
     * other for the same candidacy (see conversation notes: sequential
     * same-process simulation, chosen over a real two-connection
     * concurrency test). The row lock plus the post-lock status re-check
     * mean the second request must see the row already ASSIGNED and skip
     * it — the outcome that actually matters: a candidacy never ends up
     * overwritten by a second, later evaluator.
     */
    public function test_overlapping_bulk_assign_requests_never_assign_the_same_candidacy_to_two_evaluators(): void
    {
        $alice = $this->createEvaluator();
        $bob = $this->createEvaluator();
        $shared = $this->createValidatedCandidacy();
        $aliceOnly = $this->createValidatedCandidacy();
        $bobOnly = $this->createValidatedCandidacy();

        $first = $this->postJson("/api/v1/evaluators/{$alice}/assign-bulk", [
            'candidacy_ids' => [$shared, $aliceOnly],
        ]);
        $first->assertOk();
        $first->assertJson([
            'assigned' => [$shared, $aliceOnly],
            'skipped' => [],
        ]);

        // Overlaps on $shared, which request A already claimed.
        $second = $this->postJson("/api/v1/evaluators/{$bob}/assign-bulk", [
            'candidacy_ids' => [$shared, $bobOnly],
        ]);
        $second->assertOk();
        $second->assertJson([
            'assigned' => [$bobOnly],
            'skipped' => [
                ['id' => $shared, 'reason' => 'not_validated:assigned'],
            ],
        ]);

        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $shared, 'evaluator_id' => $alice]);
        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $aliceOnly, 'evaluator_id' => $alice]);
        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $bobOnly, 'evaluator_id' => $bob]);
        $this->assertSame(1, ActivityLogModel::query()
            ->where('candidacy_id', $shared)
            ->where('action', 'evaluator_assigned')
            ->count());
    }

    public function test_replaying_the_same_idempotency_key_does_not_double_assign(): void
    {
        $alice = $this->createEvaluator();
        $bob = $this->createEvaluator();
        $candidacyOne = $this->createValidatedCandidacy();
        $candidacyTwo = $this->createValidatedCandidacy();

        $first = $this->postJson("/api/v1/evaluators/{$alice}/assign-bulk", [
            'candidacy_ids' => [$candidacyOne],
        ], ['Idempotency-Key' => 'bulk-replay-key']);
        $first->assertOk();
        $first->assertJson(['assigned' => [$candidacyOne], 'skipped' => []]);

        // Same key, different evaluator and different candidacy list: must
        // replay the first result verbatim rather than performing a real
        // second bulk assignment.
        $second = $this->postJson("/api/v1/evaluators/{$bob}/assign-bulk", [
            'candidacy_ids' => [$candidacyTwo],
        ], ['Idempotency-Key' => 'bulk-replay-key']);
        $second->assertOk();
        $this->assertSame($first->json(), $second->json());

        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $candidacyOne, 'evaluator_id' => $alice]);
        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $candidacyTwo, 'status' => 'validated', 'evaluator_id' => null]);
        $this->assertSame(1, ActivityLogModel::query()->where('action', 'evaluator_assigned')->count());
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
