<?php

namespace Tests\Feature\Report;

use App\Infrastructure\Report\ReportModel;
use App\Jobs\GenerateReportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateReportEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_report_queues_the_job_and_returns_202(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports', [
            'email' => 'reviewer@example.com',
        ]);

        $response->assertStatus(202);
        $response->assertJson(['status' => 'pending']);
        $response->assertJsonStructure(['report_id', 'status']);

        $reportId = $response->json('report_id');
        $this->assertNotEmpty($reportId);

        $this->assertDatabaseHas(ReportModel::class, [
            'id' => $reportId,
            'requested_by_email' => 'reviewer@example.com',
            'status' => 'pending',
        ]);

        Queue::assertPushed(
            GenerateReportJob::class,
            fn (GenerateReportJob $job): bool => $job->reportId === $reportId,
        );
    }

    public function test_it_stores_the_optional_filters_snapshot(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports', [
            'email' => 'reviewer@example.com',
            'filter' => ['evaluator_name' => 'Alice Reviewer'],
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas(ReportModel::class, [
            'id' => $response->json('report_id'),
        ]);

        $report = ReportModel::query()->findOrFail($response->json('report_id'));
        $this->assertSame(['evaluator_name' => 'Alice Reviewer'], $report->filters_snapshot);
    }

    public function test_it_stores_a_years_of_experience_range_in_the_filters_snapshot(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports?years_of_experience_min=3&years_of_experience_max=8', [
            'email' => 'reviewer@example.com',
        ]);

        $response->assertStatus(202);

        $report = ReportModel::query()->findOrFail($response->json('report_id'));
        // assertEquals, not assertSame: MySQL's JSON column doesn't
        // preserve key insertion order on round-trip.
        $this->assertEquals([
            'years_of_experience_min' => 3,
            'years_of_experience_max' => 8,
        ], $report->filters_snapshot);
    }

    public function test_it_rejects_a_non_integer_years_of_experience_range_bound(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports?years_of_experience_min=not-a-number', [
            'email' => 'reviewer@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['years_of_experience_min']);

        Queue::assertNothingPushed();
    }

    public function test_it_rejects_a_filter_on_a_column_outside_the_whitelist(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports', [
            'email' => 'reviewer@example.com',
            'filter' => ['cv_text' => 'anything'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['filter']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount(ReportModel::class, 0);
    }

    public function test_replaying_the_same_idempotency_key_returns_the_existing_report_without_a_new_row_or_job(): void
    {
        Queue::fake();

        $first = $this->postJson('/api/v1/reports', ['email' => 'reviewer@example.com'], [
            'Idempotency-Key' => 'replay-key-1',
        ]);
        $first->assertStatus(202);

        $second = $this->postJson('/api/v1/reports', ['email' => 'someone-else@example.com'], [
            'Idempotency-Key' => 'replay-key-1',
        ]);

        $second->assertStatus(200);
        $second->assertJsonPath('report_id', $first->json('report_id'));
        $second->assertJsonPath('status', 'pending');

        $this->assertDatabaseCount(ReportModel::class, 1);
        Queue::assertPushed(GenerateReportJob::class, 1);
    }

    public function test_it_rejects_a_missing_email(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount(ReportModel::class, 0);
    }

    public function test_it_rejects_a_malformed_email(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports', ['email' => 'not-an-email']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        Queue::assertNothingPushed();
    }
}
