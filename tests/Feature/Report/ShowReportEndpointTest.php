<?php

namespace Tests\Feature\Report;

use App\Infrastructure\Report\ReportModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShowReportEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_pending_shape(): void
    {
        $report = ReportModel::factory()->create();

        $response = $this->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk();
        $response->assertExactJson([
            'report_id' => $report->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_returns_the_completed_shape_with_a_download_url(): void
    {
        Storage::fake(ReportModel::DISK);

        $report = ReportModel::factory()->completed()->create();

        $response = $this->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk();
        $response->assertJsonPath('report_id', $report->id);
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('file_path', $report->file_path);
        $this->assertStringContainsString($report->file_path, $response->json('download_url'));
    }

    public function test_it_returns_the_failed_shape_with_the_error_message(): void
    {
        $report = ReportModel::factory()->failed('Disk quota exceeded.')->create();

        $response = $this->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk();
        $response->assertExactJson([
            'report_id' => $report->id,
            'status' => 'failed',
            'error_message' => 'Disk quota exceeded.',
        ]);
    }

    public function test_it_returns_404_for_an_unknown_report(): void
    {
        $response = $this->getJson('/api/v1/reports/missing-id');

        $response->assertStatus(404);
    }
}
