<?php

namespace App\Infrastructure\Query;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read-side query for the consolidated candidacy/evaluator listing (CQRS-lite).
 *
 * This bypasses domain hydration entirely: it talks to the database directly
 * and returns plain rows, because the listing only ever displays data and
 * never needs to become a Candidacy aggregate. Sorting and filtering accept
 * only the apiField keys below; the request layer must never interpolate
 * a raw column name or expression from user input into this class.
 */
final class CandidacyEvaluatorListingQuery
{
    /**
     * Whitelisted apiField => dbColumn map. Both sorting and filtering are
     * restricted to these keys, so request input only ever selects a value
     * out of this map and is never concatenated into SQL as an identifier.
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        'candidate_name' => 'candidacies.full_name',
        'candidate_email' => 'candidacies.email',
        'years' => 'candidacies.years_of_experience',
        'evaluator_name' => 'evaluators.name',
        'assigned_at' => 'candidacies.assigned_at',
    ];

    public const DEFAULT_SORT = 'years';

    public const DEFAULT_DIRECTION = 'desc';

    /**
     * @param  array<string, string>  $filters  apiField => value, both whitelisted against self::FIELDS
     */
    public function paginate(
        string $sort = self::DEFAULT_SORT,
        string $direction = self::DEFAULT_DIRECTION,
        array $filters = [],
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        $sortColumn = self::FIELDS[$sort] ?? throw new InvalidArgumentException("Unsortable field: {$sort}");
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        // Step 1: page the candidacies joined to their evaluator. Only an
        // assigned candidacy has an evaluator, so the inner join naturally
        // restricts the listing to assigned rows without a separate filter.
        $query = DB::table('candidacies')
            ->join('evaluators', 'evaluators.id', '=', 'candidacies.evaluator_id')
            ->select([
                'candidacies.id as candidacy_id',
                'candidacies.full_name as candidate_name',
                'candidacies.email as candidate_email',
                'candidacies.years_of_experience as years',
                'candidacies.evaluator_id as evaluator_id',
                'evaluators.name as evaluator_name',
                'candidacies.assigned_at as assigned_at',
            ]);

        foreach ($filters as $field => $value) {
            $column = self::FIELDS[$field] ?? throw new InvalidArgumentException("Unfilterable field: {$field}");
            $query->where($column, $value);
        }

        $paginator = $query
            ->orderBy($sortColumn, $direction)
            ->paginate(perPage: $perPage, page: $page);

        // Step 2: fetch aggregates only for the evaluator IDs present on this
        // page (not the whole evaluators table). We deliberately avoid SQL
        // GROUP_CONCAT() for the email list: MySQL silently truncates it at
        // `group_concat_max_len` (1024 bytes by default), which would drop
        // emails for evaluators with a large assigned list. Pulling the raw
        // (evaluator_id, email) rows and joining them in PHP has no such
        // ceiling. Likewise, computing these once per distinct evaluator and
        // merging them below avoids the repeated payload a per-row correlated
        // subquery or window function would produce, since a busy evaluator
        // appears on multiple rows of the page.
        $evaluatorIds = $paginator->getCollection()->pluck('evaluator_id')->unique()->values();

        $totalAssignedByEvaluator = DB::table('candidacies')
            ->select('evaluator_id', DB::raw('COUNT(*) as total_assigned'))
            ->whereIn('evaluator_id', $evaluatorIds)
            ->groupBy('evaluator_id')
            ->get()
            ->keyBy('evaluator_id');

        $candidateEmailsByEvaluator = DB::table('candidacies')
            ->select('evaluator_id', 'email')
            ->whereIn('evaluator_id', $evaluatorIds)
            ->get()
            ->groupBy('evaluator_id')
            ->map(static fn ($rows) => $rows->pluck('email')->implode(', '));

        // Step 3: merge the page with its aggregates via keyBy + map, rather
        // than re-querying or re-joining per row.
        $merged = $paginator->getCollection()->map(static function (object $row) use ($totalAssignedByEvaluator, $candidateEmailsByEvaluator) {
            $row->evaluator_total_assigned = (int) ($totalAssignedByEvaluator->get($row->evaluator_id)->total_assigned ?? 0);
            $row->evaluator_candidate_emails = $candidateEmailsByEvaluator->get($row->evaluator_id, '');

            return $row;
        });

        return $paginator->setCollection($merged);
    }
}
