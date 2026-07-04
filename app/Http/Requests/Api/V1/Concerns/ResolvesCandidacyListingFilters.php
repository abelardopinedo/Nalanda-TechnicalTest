<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;

/**
 * Shared by CandidacyEvaluatorListingRequest and GenerateReportRequest so
 * both resolve the exact-match `filter[field]=value` bag and the
 * years_of_experience_min/years_of_experience_max range params the exact
 * same way, against the exact same whitelist.
 */
trait ResolvesCandidacyListingFilters
{
    /**
     * @return array<string, mixed>
     */
    public function rangeFilterRules(): array
    {
        return [
            'years_of_experience_min' => ['nullable', 'integer', 'min:0'],
            'years_of_experience_max' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = array_filter(
            $this->input('filter', []),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        if ($this->filled('years_of_experience_min')) {
            $filters[CandidacyEvaluatorListingQuery::YEARS_MIN_FILTER] = $this->integer('years_of_experience_min');
        }

        if ($this->filled('years_of_experience_max')) {
            $filters[CandidacyEvaluatorListingQuery::YEARS_MAX_FILTER] = $this->integer('years_of_experience_max');
        }

        return $filters;
    }
}
