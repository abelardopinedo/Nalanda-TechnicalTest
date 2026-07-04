<?php

namespace App\Jobs;

use App\Infrastructure\Report\ReportModel;
use App\Infrastructure\Report\ReportStatus;
use App\Mail\ReportFailedMail;
use App\Mail\ReportReadyMail;
use Candidacy\Application\ReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $reportId,
    ) {
    }

    public function handle(ReportGenerator $reportGenerator): void
    {
        $report = ReportModel::query()->findOrFail($this->reportId);

        try {
            $path = "reports/{$report->id}.xlsx";

            $reportGenerator->generate($path, ReportModel::DISK, $report->filters_snapshot ?? []);

            $report->forceFill([
                'status' => ReportStatus::COMPLETED,
                'file_path' => $path,
                'completed_at' => now(),
            ])->save();

            Mail::to($report->requested_by_email)->send(new ReportReadyMail($report->id, $report->downloadUrl()));
        } catch (Throwable $exception) {
            $report->forceFill([
                'status' => ReportStatus::FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            Mail::to($report->requested_by_email)->send(new ReportFailedMail($report->id, $exception->getMessage()));
        }
    }
}
