<?php

namespace App\Infrastructure\Providers;

use App\Infrastructure\Report\MaatwebsiteReportGenerator;
use Candidacy\Application\ReportGenerator;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportGenerator::class, MaatwebsiteReportGenerator::class);
    }
}
