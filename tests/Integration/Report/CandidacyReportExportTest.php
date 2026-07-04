<?php

namespace Tests\Integration\Report;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use App\Infrastructure\Report\CandidacyReportExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Verifies the 50-rows-per-sheet chunking against the real MySQL engine and
 * a real generated .xlsx file. This is the trickiest part of the report:
 * each sheet's query() is scoped by a WHERE IN on a fixed, pre-computed
 * list of candidacy ids rather than ->skip()/->take(), because Maatwebsite's
 * own chunked reader calls ->forPage() on that same query internally and
 * would silently override a manually applied skip/take. Only reading the
 * real output file back with PhpSpreadsheet proves the row counts actually
 * land where intended, rather than trusting the reasoning about how
 * Maatwebsite's chunk-reading interacts with the query builder.
 */
class CandidacyReportExportTest extends TestCase
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

    public function test_it_splits_120_candidacies_into_three_sheets_of_50_50_and_20(): void
    {
        Storage::fake('local');

        CandidacyModel::factory()->count(120)->eligible()->assigned()->create();

        $path = 'reports/test-120.xlsx';
        Excel::store(new CandidacyReportExport(new CandidacyEvaluatorListingQuery()), $path, 'local');

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));

        $this->assertSame(3, $spreadsheet->getSheetCount());
        // +1 row on each sheet for the heading row.
        $this->assertSame(51, $spreadsheet->getSheet(0)->getHighestRow());
        $this->assertSame(51, $spreadsheet->getSheet(1)->getHighestRow());
        $this->assertSame(21, $spreadsheet->getSheet(2)->getHighestRow());
    }

    public function test_it_produces_exactly_one_sheet_when_row_count_equals_the_boundary(): void
    {
        Storage::fake('local');

        CandidacyModel::factory()->count(50)->eligible()->assigned()->create();

        $path = 'reports/test-50.xlsx';
        Excel::store(new CandidacyReportExport(new CandidacyEvaluatorListingQuery()), $path, 'local');

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));

        $this->assertSame(1, $spreadsheet->getSheetCount());
        $this->assertSame(51, $spreadsheet->getSheet(0)->getHighestRow());
    }

    public function test_it_produces_a_single_heading_only_sheet_when_there_are_no_assigned_candidacies(): void
    {
        Storage::fake('local');

        $path = 'reports/test-empty.xlsx';
        Excel::store(new CandidacyReportExport(new CandidacyEvaluatorListingQuery()), $path, 'local');

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));

        $this->assertSame(1, $spreadsheet->getSheetCount());
        $this->assertSame(1, $spreadsheet->getSheet(0)->getHighestRow());
    }
}
