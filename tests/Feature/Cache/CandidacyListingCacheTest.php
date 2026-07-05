<?php

namespace Tests\Feature\Cache;

use App\Infrastructure\Cache\CachedCandidacyEvaluatorListingQuery;
use App\Infrastructure\Cache\CandidacyCacheTags;
use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ClearsCandidacyReadCache;
use Tests\TestCase;

class CandidacyListingCacheTest extends TestCase
{
    use RefreshDatabase;
    use ClearsCandidacyReadCache;

    public function test_a_repeated_identical_request_is_served_from_cache_without_hitting_the_database_again(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $this->assignCandidacy($alice, 'Candidate One', 'one@example.com', 4);
        $this->assignCandidacy($alice, 'Candidate Two', 'two@example.com', 6);

        $cached = $this->app->make(CachedCandidacyEvaluatorListingQuery::class);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $first = $cached->paginate();
        $this->assertGreaterThan(0, $queries, 'Expected the cold call to hit the database.');

        $queriesAfterFirst = $queries;
        $second = $cached->paginate();

        $this->assertSame($queriesAfterFirst, $queries, 'A repeated request must be served from cache, not the database.');
        $this->assertSame($first->total(), $second->total());
    }

    public function test_an_evaluator_assignment_invalidates_only_the_listing_pages_containing_that_evaluator(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $bob = $this->createEvaluator('Bob Reviewer');

        $this->assignCandidacy($alice, 'Alice Candidate One', 'alice.one@example.com', 4);
        $this->assignCandidacy($bob, 'Bob Candidate One', 'bob.one@example.com', 5);

        $cached = $this->app->make(CachedCandidacyEvaluatorListingQuery::class);

        // Warm two distinct cached pages: one scoped to each evaluator.
        $alicePage = $cached->paginate(filters: ['evaluator_name' => 'Alice Reviewer']);
        $bobPage = $cached->paginate(filters: ['evaluator_name' => 'Bob Reviewer']);
        $this->assertCount(1, $alicePage->items());
        $this->assertCount(1, $bobPage->items());

        // A new candidacy assigned to Alice must invalidate her cached
        // page, but must leave Bob's cached page untouched.
        $this->assignCandidacy($alice, 'Alice Candidate Two', 'alice.two@example.com', 7);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $aliceAfter = $cached->paginate(filters: ['evaluator_name' => 'Alice Reviewer']);
        $this->assertGreaterThan(0, $queries, 'Alice\'s page must have been evicted and recomputed.');
        $this->assertCount(2, $aliceAfter->items());

        $queriesAfterAlice = $queries;
        $bobAfter = $cached->paginate(filters: ['evaluator_name' => 'Bob Reviewer']);
        $this->assertSame($queriesAfterAlice, $queries, 'Bob\'s page must still be served from cache, untouched by Alice\'s assignment.');
        $this->assertCount(1, $bobAfter->items());
    }

    public function test_a_contended_cold_key_waits_out_the_lock_then_falls_back_to_a_single_recompute(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $this->assignCandidacy($alice, 'Candidate One', 'one@example.com', 4);

        $key = 'candidacy-listing:'.md5(json_encode([
            'sort' => CandidacyEvaluatorListingQuery::DEFAULT_SORT,
            'direction' => CandidacyEvaluatorListingQuery::DEFAULT_DIRECTION,
            'filters' => [],
            'perPage' => 15,
            'page' => 1,
        ]));
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

            $cached = new CachedCandidacyEvaluatorListingQuery(new CandidacyEvaluatorListingQuery(), lockWaitSeconds: 1);

            $start = microtime(true);
            $result = $cached->paginate();
            $elapsed = microtime(true) - $start;

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
            $this->assertSame(1, $result->total());

            $secondStart = microtime(true);
            $second = $cached->paginate();
            $secondElapsed = microtime(true) - $secondStart;

            $this->assertSame($queriesAfterFallback, $queries);
            $this->assertLessThan(0.5, $secondElapsed);
            $this->assertSame(1, $second->total());
        } finally {
            $externalLock->forceRelease();
        }
    }

    private function createEvaluator(string $name): string
    {
        $evaluator = EvaluatorModel::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid().'@example.test',
        ]);

        return $evaluator->id;
    }

    private function assignCandidacy(string $evaluatorId, string $fullName, string $email, int $years): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            $fullName,
            new Email($email),
            new YearsOfExperience($years),
            new CvText('Some CV content that is long enough to pass validation.'),
        );

        $candidacy->validate();
        $candidacy->assignEvaluator($evaluatorId);

        $repository->save($candidacy);

        return $candidacy->id();
    }
}
