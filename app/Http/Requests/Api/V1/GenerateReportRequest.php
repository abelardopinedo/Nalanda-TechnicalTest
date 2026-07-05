<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ResolvesCandidacyListingFilters;
use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts the same `filter[field]=value` shape (plus the
 * years_of_experience_min/years_of_experience_max range params) as
 * CandidacyEvaluatorListingRequest, validated against the exact same
 * whitelist (CandidacyEvaluatorListingQuery::FIELDS) — so the report export
 * can be scoped to the same subset of the consolidated listing a client
 * already sees via GET /candidacies.
 */
class GenerateReportRequest extends FormRequest
{
    use ResolvesCandidacyListingFilters;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            ...$this->filterFieldRules(),
            ...$this->rangeFilterRules(),
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $unknown = CandidacyEvaluatorListingQuery::unknownFilterFields($this->input('filter', []));

            if ($unknown !== []) {
                $validator->errors()->add('filter', 'Unknown filter field(s): '.implode(', ', $unknown));
            }
        });
    }

    /**
     * Optional client-supplied replay key: if a report already exists with
     * this key, its current state is returned instead of creating a new one
     * and re-dispatching the job.
     */
    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return $key !== null && $key !== '' ? $key : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function filtersSnapshot(): ?array
    {
        return $this->filters() ?: null;
    }
}
