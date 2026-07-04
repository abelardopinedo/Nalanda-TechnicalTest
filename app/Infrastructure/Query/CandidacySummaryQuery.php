<?php

namespace App\Infrastructure\Query;

use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;

/**
 * Read-side query for the candidacy summary (requirement #5). Bypasses
 * domain hydration entirely and never runs the ValidationChain: it only
 * ever reads what is already stored (the candidacies row, the latest
 * candidacy_validated/candidacy_rejected activity_log entry, and the
 * assigned evaluator, if any).
 */
final class CandidacySummaryQuery
{
    private const VALIDATION_ACTIONS = ['candidacy_validated', 'candidacy_rejected'];

    public function forCandidacy(string $candidacyId): ?CandidacySummaryData
    {
        $candidacy = CandidacyModel::query()->find($candidacyId);

        if ($candidacy === null) {
            return null;
        }

        $validationEntry = ActivityLogModel::query()
            ->where('candidacy_id', $candidacyId)
            ->whereIn('action', self::VALIDATION_ACTIONS)
            ->orderByDesc('occurred_at')
            ->first();

        $evaluator = $candidacy->evaluator_id !== null
            ? EvaluatorModel::query()->find($candidacy->evaluator_id)
            : null;

        return new CandidacySummaryData($candidacy, $validationEntry, $evaluator);
    }
}
