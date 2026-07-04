<?php

namespace Tests\Feature\Report;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Report\ReportModel;
use App\Jobs\GenerateReportJob;
use App\Mail\ReportFailedMail;
use App\Mail\ReportReadyMail;
use Candidacy\Application\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

class GenerateReportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transitions_pending_to_completed_and_sends_a_queued_email_with_a_download_link(): void
    {
        Storage::fake(ReportModel::DISK);
        Mail::fake();

        CandidacyModel::factory()->count(3)->eligible()->assigned()->create();

        $report = ReportModel::factory()->create(['requested_by_email' => 'reviewer@example.com']);

        GenerateReportJob::dispatchSync($report->id);

        $path = "reports/{$report->id}.xlsx";
        Storage::disk(ReportModel::DISK)->assertExists($path);

        $report->refresh();
        $this->assertSame('completed', $report->status->value);
        $this->assertSame($path, $report->file_path);
        $this->assertNotNull($report->completed_at);

        Mail::assertQueued(
            ReportReadyMail::class,
            function (ReportReadyMail $mail) use ($report, $path): bool {
                return $mail->hasTo($report->requested_by_email)
                    && $mail->reportId === $report->id
                    && str_contains($mail->downloadUrl, $path);
            },
        );
    }

    public function test_it_produces_a_valid_report_even_with_no_assigned_candidacies(): void
    {
        Storage::fake(ReportModel::DISK);
        Mail::fake();

        $report = ReportModel::factory()->create();

        GenerateReportJob::dispatchSync($report->id);

        Storage::disk(ReportModel::DISK)->assertExists("reports/{$report->id}.xlsx");
        Mail::assertQueued(ReportReadyMail::class);
    }

    public function test_a_report_with_a_filter_only_includes_matching_candidacies(): void
    {
        Storage::fake(ReportModel::DISK);
        Mail::fake();

        CandidacyModel::factory()->eligible()->assigned()->create(['years_of_experience' => 5]);
        CandidacyModel::factory()->eligible()->assigned()->create(['years_of_experience' => 5]);
        CandidacyModel::factory()->eligible()->assigned()->create(['years_of_experience' => 8]);

        $report = ReportModel::factory()->create([
            'filters_snapshot' => ['years' => 5],
        ]);

        GenerateReportJob::dispatchSync($report->id);

        $spreadsheet = IOFactory::load(Storage::disk(ReportModel::DISK)->path($report->fresh()->file_path));

        // Heading row + exactly the 2 candidacies with years_of_experience = 5.
        $this->assertSame(1, $spreadsheet->getSheetCount());
        $this->assertSame(3, $spreadsheet->getSheet(0)->getHighestRow());
    }

    public function test_an_unfiltered_report_includes_every_assigned_candidacy(): void
    {
        Storage::fake(ReportModel::DISK);
        Mail::fake();

        CandidacyModel::factory()->eligible()->assigned()->create(['years_of_experience' => 5]);
        CandidacyModel::factory()->eligible()->assigned()->create(['years_of_experience' => 5]);
        CandidacyModel::factory()->eligible()->assigned()->create(['years_of_experience' => 8]);

        $report = ReportModel::factory()->create();

        GenerateReportJob::dispatchSync($report->id);

        $spreadsheet = IOFactory::load(Storage::disk(ReportModel::DISK)->path($report->fresh()->file_path));

        // Heading row + all 3 assigned candidacies, filter or no filter.
        $this->assertSame(4, $spreadsheet->getSheet(0)->getHighestRow());
    }

    public function test_a_generator_failure_marks_the_report_failed_with_the_error_message_and_notifies(): void
    {
        Mail::fake();

        $report = ReportModel::factory()->create(['requested_by_email' => 'reviewer@example.com']);

        $this->app->bind(ReportGenerator::class, function (): ReportGenerator {
            return new class implements ReportGenerator
            {
                public function generate(string $path, string $disk, array $filters = []): void
                {
                    throw new RuntimeException('Disk quota exceeded.');
                }
            };
        });

        GenerateReportJob::dispatchSync($report->id);

        $report->refresh();
        $this->assertSame('failed', $report->status->value);
        $this->assertSame('Disk quota exceeded.', $report->error_message);
        $this->assertNull($report->file_path);
        $this->assertNull($report->completed_at);

        Mail::assertQueued(
            ReportFailedMail::class,
            fn (ReportFailedMail $mail): bool => $mail->hasTo('reviewer@example.com')
                && $mail->reportId === $report->id
                && $mail->errorMessage === 'Disk quota exceeded.',
        );
    }
}
