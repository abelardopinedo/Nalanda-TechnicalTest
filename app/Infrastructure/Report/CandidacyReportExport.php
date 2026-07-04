<?php

namespace App\Infrastructure\Report;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Splits the consolidated candidacy/evaluator listing into worksheets of at
 * most ROWS_PER_SHEET candidates each. "50 per page" is interpreted here as
 * 50 rows per worksheet (see the README note on this interpretation).
 */
final class CandidacyReportExport implements WithMultipleSheets
{
    public const ROWS_PER_SHEET = 50;

    /**
     * @param  array<string, string>  $filters  apiField => value, whitelisted against CandidacyEvaluatorListingQuery::FIELDS
     */
    public function __construct(
        private readonly CandidacyEvaluatorListingQuery $query,
        private readonly array $filters = [],
    ) {
    }

    /**
     * @return list<CandidacyReportSheetExport>
     */
    public function sheets(): array
    {
        // baseQuery() already applies its own aliased ->select([...]), so
        // pluck() won't auto-narrow the select to just the id column (it
        // only does that when no columns are set yet) — pluck the aliased
        // `candidacy_id` column that select already produces instead of the
        // raw `candidacies.id`, which the existing select doesn't expose.
        $query = $this->query->baseQuery();

        $this->query->applyFilters($query, $this->filters);

        $ids = $query
            ->orderBy(
                CandidacyEvaluatorListingQuery::FIELDS[CandidacyEvaluatorListingQuery::DEFAULT_SORT],
                CandidacyEvaluatorListingQuery::DEFAULT_DIRECTION,
            )
            ->pluck('candidacy_id')
            ->all();

        if ($ids === []) {
            return [new CandidacyReportSheetExport($this->query, [], 1)];
        }

        return collect($ids)
            ->chunk(self::ROWS_PER_SHEET)
            ->values()
            ->map(fn ($idsForSheet, $index) => new CandidacyReportSheetExport(
                $this->query,
                $idsForSheet->values()->all(),
                $index + 1,
            ))
            ->all();
    }
}
