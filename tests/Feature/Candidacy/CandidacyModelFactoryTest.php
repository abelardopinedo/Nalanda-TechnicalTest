<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These states must remain direct attribute assignment (no real
 * validate()/reject()/assignEvaluator() transition, no use case) but still
 * seed the activity_log side effect a real transition would have produced,
 * so fixtures built from them are complete for anything that reads
 * activity-log-derived data (e.g. the summary endpoint's time_to_decision).
 */
class CandidacyModelFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_validated_state_sets_status_and_logs_a_matching_activity_entry(): void
    {
        $candidacy = CandidacyModel::factory()->validated()->create();

        $this->assertSame('validated', $candidacy->status);

        $this->assertDatabaseHas(ActivityLogModel::class, [
            'candidacy_id' => $candidacy->id,
            'action' => 'candidacy_validated',
        ]);

        $entry = ActivityLogModel::query()->where('candidacy_id', $candidacy->id)->firstOrFail();

        $this->assertSame('validated', $entry->payload['outcome']);
        $this->assertSame([], $entry->payload['reasons']);
        $this->assertTrue($entry->occurred_at->isAfter($candidacy->created_at));
        $this->assertTrue($entry->occurred_at->lessThanOrEqualTo(now()));
    }

    public function test_rejected_state_sets_status_and_logs_a_matching_activity_entry_with_reasons(): void
    {
        $candidacy = CandidacyModel::factory()->rejected()->create();

        $this->assertSame('rejected', $candidacy->status);

        $entry = ActivityLogModel::query()->where('candidacy_id', $candidacy->id)->firstOrFail();

        $this->assertSame('candidacy_rejected', $entry->action);
        $this->assertSame('rejected', $entry->payload['outcome']);
        $this->assertNotEmpty($entry->payload['reasons']);
        $this->assertTrue($entry->occurred_at->isAfter($candidacy->created_at));
    }

    public function test_assigned_state_sets_evaluator_and_logs_a_matching_activity_entry(): void
    {
        $candidacy = CandidacyModel::factory()->assigned()->create();

        $this->assertSame('assigned', $candidacy->status);
        $this->assertNotNull($candidacy->evaluator_id);
        $this->assertNotNull($candidacy->assigned_at);

        $entry = ActivityLogModel::query()
            ->where('candidacy_id', $candidacy->id)
            ->where('action', 'evaluator_assigned')
            ->firstOrFail();

        $this->assertSame($candidacy->evaluator_id, $entry->evaluator_id);
        $this->assertSame($candidacy->evaluator_id, $entry->payload['evaluator_id']);
    }

    public function test_assigned_state_also_seeds_the_prior_validation_entry_in_chronological_order(): void
    {
        $candidacy = CandidacyModel::factory()->assigned()->create();

        $validatedEntry = ActivityLogModel::query()
            ->where('candidacy_id', $candidacy->id)
            ->where('action', 'candidacy_validated')
            ->firstOrFail();

        $assignedEntry = ActivityLogModel::query()
            ->where('candidacy_id', $candidacy->id)
            ->where('action', 'evaluator_assigned')
            ->firstOrFail();

        $this->assertSame('validated', $validatedEntry->payload['outcome']);
        $this->assertSame([], $validatedEntry->payload['reasons']);

        // Assignment requires prior VALIDATED status: created_at <= validated <= assigned <= now.
        $this->assertTrue($validatedEntry->occurred_at->isAfter($candidacy->created_at));
        $this->assertTrue($assignedEntry->occurred_at->isAfter($validatedEntry->occurred_at));
        $this->assertTrue($assignedEntry->occurred_at->lessThanOrEqualTo(now()));
    }
}
