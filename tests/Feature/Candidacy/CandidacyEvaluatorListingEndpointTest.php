<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CandidacyEvaluatorListingEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sorts_by_years_descending_by_default(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $bob = $this->createEvaluator('Bob Reviewer');

        $this->assignCandidacy($alice, 'Low Years', 'low@example.com', 3);
        $this->assignCandidacy($alice, 'High Years', 'high@example.com', 8);
        $this->assignCandidacy($bob, 'Mid Years', 'mid@example.com', 6);

        $response = $this->getJson('/api/v1/candidacies');

        $response->assertOk();
        $response->assertJsonPath('data.0.years_of_experience', 8);
        $response->assertJsonPath('data.1.years_of_experience', 6);
        $response->assertJsonPath('data.2.years_of_experience', 3);
    }

    public function test_it_aggregates_the_evaluators_total_assigned_count_and_candidate_emails(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $bob = $this->createEvaluator('Bob Reviewer');

        $this->assignCandidacy($alice, 'Candidate One', 'one@example.com', 4);
        $this->assignCandidacy($alice, 'Candidate Two', 'two@example.com', 5);
        $this->assignCandidacy($alice, 'Candidate Three', 'three@example.com', 6);
        $this->assignCandidacy($bob, 'Candidate Four', 'four@example.com', 2);

        $response = $this->getJson('/api/v1/candidacies');
        $response->assertOk();

        $rows = collect($response->json('data'));
        $aliceRows = $rows->where('evaluator_name', 'Alice Reviewer');

        $this->assertCount(3, $aliceRows);

        foreach ($aliceRows as $row) {
            $this->assertSame(3, $row['evaluator_total_assigned']);
            $this->assertStringContainsString('one@example.com', $row['evaluator_candidate_emails']);
            $this->assertStringContainsString('two@example.com', $row['evaluator_candidate_emails']);
            $this->assertStringContainsString('three@example.com', $row['evaluator_candidate_emails']);
        }

        $bobRow = $rows->firstWhere('evaluator_name', 'Bob Reviewer');
        $this->assertSame(1, $bobRow['evaluator_total_assigned']);
        $this->assertSame('four@example.com', $bobRow['evaluator_candidate_emails']);
    }

    public function test_it_filters_by_a_whitelisted_column(): void
    {
        $alice = $this->createEvaluator('Alice Reviewer');
        $bob = $this->createEvaluator('Bob Reviewer');

        $this->assignCandidacy($alice, 'Candidate One', 'one@example.com', 4);
        $this->assignCandidacy($bob, 'Candidate Two', 'two@example.com', 5);

        $response = $this->getJson('/api/v1/candidacies?'.http_build_query([
            'filter' => ['evaluator_name' => 'Alice Reviewer'],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.evaluator_name', 'Alice Reviewer');
    }

    public function test_it_rejects_a_filter_on_a_column_outside_the_whitelist(): void
    {
        $response = $this->getJson('/api/v1/candidacies?'.http_build_query([
            'filter' => ['cv_text' => 'anything'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['filter']);
    }

    public function test_it_filters_by_a_minimum_years_of_experience(): void
    {
        $evaluator = $this->createEvaluator('Alice Reviewer');

        $this->assignCandidacy($evaluator, 'Low Years', 'low@example.com', 3);
        $this->assignCandidacy($evaluator, 'Mid Years', 'mid@example.com', 6);
        $this->assignCandidacy($evaluator, 'High Years', 'high@example.com', 9);

        $response = $this->getJson('/api/v1/candidacies?years_of_experience_min=6');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $years = collect($response->json('data'))->pluck('years_of_experience')->sort()->values();
        $this->assertSame([6, 9], $years->all());
    }

    public function test_it_filters_by_a_maximum_years_of_experience(): void
    {
        $evaluator = $this->createEvaluator('Alice Reviewer');

        $this->assignCandidacy($evaluator, 'Low Years', 'low@example.com', 3);
        $this->assignCandidacy($evaluator, 'Mid Years', 'mid@example.com', 6);
        $this->assignCandidacy($evaluator, 'High Years', 'high@example.com', 9);

        $response = $this->getJson('/api/v1/candidacies?years_of_experience_max=6');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $years = collect($response->json('data'))->pluck('years_of_experience')->sort()->values();
        $this->assertSame([3, 6], $years->all());
    }

    public function test_it_filters_by_a_range_of_years_of_experience(): void
    {
        $evaluator = $this->createEvaluator('Alice Reviewer');

        $this->assignCandidacy($evaluator, 'Low Years', 'low@example.com', 3);
        $this->assignCandidacy($evaluator, 'Mid Years', 'mid@example.com', 6);
        $this->assignCandidacy($evaluator, 'High Years', 'high@example.com', 9);

        $response = $this->getJson('/api/v1/candidacies?years_of_experience_min=4&years_of_experience_max=8');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.years_of_experience', 6);
    }

    public function test_it_rejects_a_non_integer_years_of_experience_range_bound(): void
    {
        $response = $this->getJson('/api/v1/candidacies?years_of_experience_min=not-a-number');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['years_of_experience_min']);
    }

    public function test_it_overrides_the_default_sort(): void
    {
        $evaluator = $this->createEvaluator('Alice Reviewer');

        $this->assignCandidacy($evaluator, 'Zack Candidate', 'zack@example.com', 4);
        $this->assignCandidacy($evaluator, 'Amy Candidate', 'amy@example.com', 5);

        $response = $this->getJson('/api/v1/candidacies?sort=candidate_name&direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.candidate_name', 'Amy Candidate');
        $response->assertJsonPath('data.1.candidate_name', 'Zack Candidate');
    }

    public function test_it_paginates_the_results(): void
    {
        $evaluator = $this->createEvaluator('Alice Reviewer');

        for ($years = 1; $years <= 5; $years++) {
            $this->assignCandidacy($evaluator, "Candidate {$years}", "candidate{$years}@example.com", $years);
        }

        $response = $this->getJson('/api/v1/candidacies?per_page=2&page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 5);

        // Default sort is years desc: page 1 holds [5, 4], page 2 holds [3, 2].
        $response->assertJsonPath('data.0.years_of_experience', 3);
        $response->assertJsonPath('data.1.years_of_experience', 2);
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
