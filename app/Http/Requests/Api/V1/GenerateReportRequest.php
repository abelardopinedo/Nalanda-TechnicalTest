<?php

namespace App\Http\Requests\Api\V1;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts the same `filter[field]=value` shape as
 * CandidacyEvaluatorListingRequest, validated against the exact same
 * whitelist (CandidacyEvaluatorListingQuery::FIELDS) — so the report export
 * can be scoped to the same subset of the consolidated listing a client
 * already sees via GET /candidacies.
 */
class GenerateReportRequest extends FormRequest
{
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
            'filter' => ['nullable', 'array'],
            'filter.*' => ['nullable', 'string'],
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
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter(
            $this->input('filter', []),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @return array<string, string>|null
     */
    public function filtersSnapshot(): ?array
    {
        return $this->filters() ?: null;
    }
}
