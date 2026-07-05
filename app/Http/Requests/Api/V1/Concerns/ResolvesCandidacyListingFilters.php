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
     * Named per-field rules (`filter.candidate_name`, `filter.years`, ...)
     * instead of a `filter.*` wildcard: functionally equivalent (each
     * whitelisted key's value must be a nullable string — unknown keys are
     * rejected separately, by unknownFilterFields() in withValidator()), but
     * a wildcard rule makes Scramble document `filter` as a bare array of
     * strings, generating a broken example request (`["string"]`) that
     * trips the very whitelist check this trait enforces. Naming each key
     * explicitly documents `filter` as an object with those properties
     * instead, which is what it actually is.
     *
     * @return array<string, mixed>
     */
    public function filterFieldRules(): array
    {
        $rules = [
            'filter' => ['nullable', 'array'],
        ];

        foreach (array_keys(CandidacyEvaluatorListingQuery::FIELDS) as $field) {
            $rules["filter.{$field}"] = ['nullable', 'string'];
        }

        return $rules;
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
