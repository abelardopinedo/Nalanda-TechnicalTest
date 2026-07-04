<?php

namespace Tests\Integration\Candidacy;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs CandidacyEvaluatorListingQuery against the real MySQL engine the
 * app ships on (the Sail `mysql` service), not sqlite. The query
 * deliberately avoids SQL GROUP_CONCAT() when aggregating a busy
 * evaluator's candidate emails because MySQL silently truncates it at
 * group_concat_max_len (1024 bytes by default) — a regression back to
 * GROUP_CONCAT() would still pass on sqlite (no such limit) and only
 * fails on the engine this app actually runs on, which is why this
 * suite is pinned to real MySQL (see phpunit.xml) rather than an
 * in-memory fake.
 */
class CandidacyEvaluatorListingQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            'mysql',
            DB::connection()->getDriverName(),
            'This integration test must run against the real MySQL engine, not sqlite.',
        );
    }

    public function test_it_sorts_filters_and_paginates_against_mysql(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $bob = $this->createEvaluator('Bob Reviewer');

        $this->assignCandidacy($alice, 'Low Years', 'low@example.com', 3);
        $this->assignCandidacy($alice, 'High Years', 'high@example.com', 8);
        $this->assignCandidacy($bob, 'Mid Years', 'mid@example.com', 6);

        $query = new CandidacyEvaluatorListingQuery();

        $paginator = $query->paginate(sort: 'years', direction: 'asc', perPage: 2, page: 1);

        $this->assertSame(3, $paginator->total());
        $this->assertSame(2, $paginator->count());
        $this->assertSame(3, $paginator->getCollection()[0]->years);
        $this->assertSame(6, $paginator->getCollection()[1]->years);

        $secondPage = $query->paginate(sort: 'years', direction: 'asc', perPage: 2, page: 2);

        $this->assertSame(1, $secondPage->count());
        $this->assertSame(8, $secondPage->getCollection()[0]->years);

        $filtered = $query->paginate(filters: ['evaluator_name' => 'Bob Reviewer']);

        $this->assertSame(1, $filtered->total());
        $this->assertSame('Mid Years', $filtered->getCollection()[0]->candidate_name);
    }

    public function test_it_computes_evaluator_aggregates_past_mysqls_group_concat_default_limit(): void
    {
        $evaluator = $this->createEvaluator('Prolific Reviewer');

        // Each email pads its domain label to ~80 bytes, so 20 of them
        // concatenated (~1.6KB) comfortably exceed MySQL's default
        // group_concat_max_len of 1024 bytes: a naive GROUP_CONCAT()
        // would silently drop the tail entries here, but computing the
        // aggregate in PHP (as the query does) must not.
        $emails = [];

        for ($i = 0; $i < 20; $i++) {
            $email = sprintf('candidate%d@%s.example.com', $i, str_repeat('x', 60));
            $emails[] = $email;

            $this->assignCandidacy($evaluator, "Candidate {$i}", $email, 5);
        }

        $paginator = (new CandidacyEvaluatorListingQuery())->paginate(perPage: 100);

        $row = $paginator->getCollection()->first();

        $this->assertSame(20, $row->evaluator_total_assigned);

        foreach ($emails as $email) {
            $this->assertStringContainsString($email, $row->evaluator_candidate_emails);
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

    private function assignCandidacy(string $evaluatorId, string $fullName, string $email, int $years): void
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
    }
}
