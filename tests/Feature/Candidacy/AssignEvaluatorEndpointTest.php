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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\Support\ClearsCandidacyReadCache;
use Tests\TestCase;

class AssignEvaluatorEndpointTest extends TestCase
{
    use RefreshDatabase;
    use ClearsCandidacyReadCache;

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

    public function test_it_returns_a_conflict_when_the_candidacy_was_rejected(): void
    {
        $evaluatorId = $this->createEvaluator();
        $candidacyId = $this->createRejectedCandidacy();

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => $evaluatorId,
        ]);

        $response->assertStatus(409);
    }

    public function test_replaying_the_same_idempotency_key_returns_the_prior_result_without_a_second_assignment(): void
    {
        $alice = $this->createEvaluator();
        $bob = $this->createEvaluator();
        $candidacyId = $this->createValidatedCandidacy();

        $first = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => $alice,
        ], ['Idempotency-Key' => 'replay-key-1']);
        $first->assertOk();
        $first->assertJsonPath('data.evaluator_id', $alice);

        // Same key, different evaluator: must replay the first result
        // verbatim rather than attempting (and rejecting) a real second
        // assignment.
        $second = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => $bob,
        ], ['Idempotency-Key' => 'replay-key-1']);
        $second->assertOk();
        $second->assertJsonPath('data.evaluator_id', $alice);
        $this->assertSame($first->json(), $second->json());

        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $candidacyId,
            'status' => 'assigned',
            'evaluator_id' => $alice,
        ]);
        $this->assertSame(1, ActivityLogModel::query()
            ->where('candidacy_id', $candidacyId)
            ->where('action', 'evaluator_assigned')
            ->count());
    }

    public function test_a_different_idempotency_key_performs_an_independent_assignment(): void
    {
        $alice = $this->createEvaluator();
        $bob = $this->createEvaluator();
        $candidacyOne = $this->createValidatedCandidacy();
        $candidacyTwo = $this->createValidatedCandidacy();

        $first = $this->postJson("/api/v1/candidacies/{$candidacyOne}/evaluator", [
            'evaluator_id' => $alice,
        ], ['Idempotency-Key' => 'key-one']);
        $first->assertOk();
        $first->assertJsonPath('data.evaluator_id', $alice);

        $second = $this->postJson("/api/v1/candidacies/{$candidacyTwo}/evaluator", [
            'evaluator_id' => $bob,
        ], ['Idempotency-Key' => 'key-two']);
        $second->assertOk();
        $second->assertJsonPath('data.evaluator_id', $bob);

        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $candidacyOne, 'evaluator_id' => $alice]);
        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $candidacyTwo, 'evaluator_id' => $bob]);
        $this->assertSame(2, ActivityLogModel::query()->where('action', 'evaluator_assigned')->count());
    }

    public function test_no_idempotency_key_performs_a_normal_non_idempotent_assignment(): void
    {
        $alice = $this->createEvaluator();
        $candidacyId = $this->createValidatedCandidacy();

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/evaluator", [
            'evaluator_id' => $alice,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.evaluator_id', $alice);
        $this->assertSame(1, ActivityLogModel::query()
            ->where('candidacy_id', $candidacyId)
            ->where('action', 'evaluator_assigned')
            ->count());
    }

    public function test_after_the_ttl_window_the_same_idempotency_key_is_treated_as_new(): void
    {
        Config::set('cache.assignment_idempotency_ttl', 1);

        $alice = $this->createEvaluator();
        $bob = $this->createEvaluator();
        $candidacyOne = $this->createValidatedCandidacy();
        $candidacyTwo = $this->createValidatedCandidacy();

        $first = $this->postJson("/api/v1/candidacies/{$candidacyOne}/evaluator", [
            'evaluator_id' => $alice,
        ], ['Idempotency-Key' => 'expiring-key']);
        $first->assertOk();
        $first->assertJsonPath('data.evaluator_id', $alice);

        // Real Redis TTL expiry runs on the server's own clock, not
        // Carbon::setTestNow(), so this must actually wait it out.
        usleep(1_200_000);

        // Same key, but the entry has expired: this must be treated as a
        // brand new request and perform a real assignment for candidacyTwo,
        // not replay candidacyOne's cached result.
        $second = $this->postJson("/api/v1/candidacies/{$candidacyTwo}/evaluator", [
            'evaluator_id' => $bob,
        ], ['Idempotency-Key' => 'expiring-key']);
        $second->assertOk();
        $second->assertJsonPath('data.evaluator_id', $bob);
        $second->assertJsonPath('data.id', $candidacyTwo);

        $this->assertDatabaseHas(CandidacyModel::class, ['id' => $candidacyTwo, 'evaluator_id' => $bob]);
        $this->assertSame(2, ActivityLogModel::query()->where('action', 'evaluator_assigned')->count());
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

    private function createRejectedCandidacy(): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = $this->registerCandidacy($repository);
        $candidacy->reject();
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
