<?php

namespace App\Infrastructure\Report;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Candidacy\Application\ReportGenerator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Maatwebsite/Laravel-Excel implementation of the ReportGenerator port.
 * Builds an in-memory spreadsheet model (via PhpSpreadsheet) and writes it
 * through Excel::store(), which itself stores via the Storage abstraction
 * (the given $disk from config/filesystems.php).
 *
 * Swappable: for very large reports where PhpSpreadsheet's in-memory model
 * becomes a memory bottleneck, an OpenSpout (https://github.com/openspout/openspout)
 * -based implementation of this same ReportGenerator port could replace this
 * class — OpenSpout writes rows straight to the output stream without
 * building a full in-memory workbook, trading Maatwebsite's richer
 * query/collection integration for near-constant memory use regardless of
 * report size.
 */
final class MaatwebsiteReportGenerator implements ReportGenerator
{
    public function __construct(private readonly CandidacyEvaluatorListingQuery $query)
    {
    }

    public function generate(string $path, string $disk, array $filters = []): void
    {
        Excel::store(new CandidacyReportExport($this->query, $filters), $path, $disk);
    }
}
