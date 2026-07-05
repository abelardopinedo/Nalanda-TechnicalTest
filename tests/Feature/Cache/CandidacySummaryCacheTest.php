<?php

namespace Tests\Feature\Cache;

use App\Infrastructure\Cache\CachedCandidacySummaryQuery;
use App\Infrastructure\Cache\CandidacyCacheTags;
use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use App\Infrastructure\Query\CandidacySummaryQuery;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\ClearsCandidacyReadCache;
use Tests\TestCase;

class CandidacySummaryCacheTest extends TestCase
{
    use RefreshDatabase;
    use ClearsCandidacyReadCache;

    public function test_a_repeated_identical_request_is_served_from_cache_without_hitting_the_database_again(): void
    {
        $candidacyId = $this->createValidatedCandidacy();
        $cached = $this->app->make(CachedCandidacySummaryQuery::class);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $first = $cached->forCandidacy($candidacyId);
        $this->assertGreaterThan(0, $queries, 'Expected the cold call to hit the database.');

        $queriesAfterFirst = $queries;
        $second = $cached->forCandidacy($candidacyId);

        $this->assertSame($queriesAfterFirst, $queries, 'A repeated request must be served from cache, not the database.');
        $this->assertSame($first->validationOutcome(), $second->validationOutcome());
    }

    public function test_a_validation_outcome_invalidates_the_cached_summary(): void
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText(str_repeat('Experienced backend engineer. ', 5)),
        );
        $repository->save($candidacy);

        $cached = $this->app->make(CachedCandidacySummaryQuery::class);

        $beforeValidation = $cached->forCandidacy($candidacy->id());
        $this->assertNull($beforeValidation->validationOutcome());

        $candidacy = $repository->findById($candidacy->id());
        $candidacy->validate();
        $candidacy->recordValidationOutcome([]);
        $repository->save($candidacy);

        $afterValidation = $cached->forCandidacy($candidacy->id());
        $this->assertSame('validated', $afterValidation->validationOutcome());
    }

    public function test_an_evaluator_assignment_invalidates_the_cached_summary(): void
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

        $cached = $this->app->make(CachedCandidacySummaryQuery::class);

        $beforeAssignment = $cached->forCandidacy($candidacy->id());
        $this->assertNull($beforeAssignment->evaluator);

        $evaluator = EvaluatorModel::factory()->create();
        $candidacy = $repository->findById($candidacy->id());
        $candidacy->assignEvaluator($evaluator->id);
        $repository->save($candidacy);

        $afterAssignment = $cached->forCandidacy($candidacy->id());
        $this->assertNotNull($afterAssignment->evaluator);
        $this->assertSame($evaluator->id, $afterAssignment->evaluator->id);
    }

    public function test_a_registration_invalidates_any_pre_existing_cached_summary_for_that_id(): void
    {
        // Defensive/idempotent case: nothing should blow up evicting a
        // summary cache tag for a candidacy id that has no cache entry yet.
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());
        $candidacyId = $repository->nextIdentity();

        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);
        $store->tags([CandidacyCacheTags::candidacy($candidacyId)])->flush();

        $candidacy = Candidacy::register(
            $candidacyId,
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText(str_repeat('Experienced backend engineer. ', 5)),
        );
        $repository->save($candidacy);

        $cached = $this->app->make(CachedCandidacySummaryQuery::class);
        $summary = $cached->forCandidacy($candidacyId);

        $this->assertNotNull($summary);
        $this->assertSame('received', $summary->candidacy->status);
    }

    public function test_a_contended_cold_key_waits_out_the_lock_then_falls_back_to_a_single_recompute(): void
    {
        $candidacyId = $this->createValidatedCandidacy();
        $key = "candidacy-summary:{$candidacyId}";
        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);

        // Simulate "another worker is already computing this key" by
        // holding the lock ourselves for longer than the wait below.
        $externalLock = $store->lock("lock:{$key}", 10);
        $this->assertTrue($externalLock->get());

        try {
            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });

            $cached = new CachedCandidacySummaryQuery(new CandidacySummaryQuery(), lockWaitSeconds: 1);

            $start = microtime(true);
            $result = $cached->forCandidacy($candidacyId);
            $elapsed = microtime(true) - $start;

            $this->assertNotNull($result);
            // Lock::block() polls and exits ~one sleep-interval before the
            // full requested wait to avoid overshooting it, so a 1s wait
            // elapses a bit under 1s; 0.6s safely distinguishes genuine
            // waiting from an immediate bypass.
            $this->assertGreaterThanOrEqual(
                0.6,
                $elapsed,
                'A contended caller must wait out the lock before falling back, not bypass it immediately.',
            );
            $queriesAfterFallback = $queries;
            $this->assertGreaterThan(0, $queriesAfterFallback);

            // A second contender arriving after the fallback recompute
            // already warmed the cache must be served from it directly:
            // no further wait, no further recompute, despite the original
            // lock still nominally being held.
            $secondStart = microtime(true);
            $second = $cached->forCandidacy($candidacyId);
            $secondElapsed = microtime(true) - $secondStart;

            $this->assertSame($queriesAfterFallback, $queries);
            $this->assertLessThan(0.5, $secondElapsed);
            $this->assertNotNull($second);
        } finally {
            $externalLock->forceRelease();
        }
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
}
