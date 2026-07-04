<?php

namespace App\Infrastructure\Report;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One worksheet's worth (at most ROWS_PER_SHEET, see CandidacyReportExport)
 * of the consolidated candidacy/evaluator listing.
 *
 * The row set is scoped via a WHERE IN on a fixed, pre-computed list of
 * candidacy ids (see CandidacyReportExport::sheets()) rather than
 * ->skip()/->take() — Maatwebsite's own chunked reader calls ->forPage() on
 * this query() internally (see Maatwebsite\Excel\Sheet::fromQuery() and
 * Jobs\AppendQueryToSheet), which would silently override any skip/take we
 * set ourselves. Scoping by id keeps this sheet's row count fixed
 * regardless of how Maatwebsite paginates its own chunked reads underneath.
 */
final class CandidacyReportSheetExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithTitle
{
    private const CHUNK_SIZE = 25;

    /**
     * @param  list<string>  $candidacyIds
     */
    public function __construct(
        private readonly CandidacyEvaluatorListingQuery $query,
        private readonly array $candidacyIds,
        private readonly int $sheetNumber,
    ) {
    }

    public function query(): Builder
    {
        return $this->query->baseQuery()
            ->whereIn('candidacies.id', $this->candidacyIds)
            ->orderBy(
                CandidacyEvaluatorListingQuery::FIELDS[CandidacyEvaluatorListingQuery::DEFAULT_SORT],
                CandidacyEvaluatorListingQuery::DEFAULT_DIRECTION,
            );
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Candidate Name', 'Candidate Email', 'Years of Experience', 'Evaluator Name', 'Assigned At'];
    }

    /**
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->candidate_name,
            $row->candidate_email,
            $row->years,
            $row->evaluator_name,
            $row->assigned_at,
        ];
    }

    public function title(): string
    {
        return "Sheet {$this->sheetNumber}";
    }

    public function chunkSize(): int
    {
        return self::CHUNK_SIZE;
    }
}
