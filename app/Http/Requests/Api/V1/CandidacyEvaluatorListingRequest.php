<?php

namespace App\Http\Requests\Api\V1;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP-level input guarding only: shape and whitelist membership of the
 * sort/filter/pagination parameters. The whitelist itself lives on
 * CandidacyEvaluatorListingQuery so both sides always agree on it.
 */
class CandidacyEvaluatorListingRequest extends FormRequest
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
            'sort' => ['nullable', 'string', Rule::in(array_keys(CandidacyEvaluatorListingQuery::FIELDS))],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'filter' => ['nullable', 'array'],
            'filter.*' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $unknown = array_diff(
                array_keys($this->input('filter', [])),
                array_keys(CandidacyEvaluatorListingQuery::FIELDS),
            );

            if ($unknown !== []) {
                $validator->errors()->add('filter', 'Unknown filter field(s): '.implode(', ', $unknown));
            }
        });
    }

    public function sort(): string
    {
        return $this->string('sort')->toString() ?: CandidacyEvaluatorListingQuery::DEFAULT_SORT;
    }

    public function direction(): string
    {
        $direction = $this->string('direction')->lower()->toString();

        return $direction === 'asc' ? 'asc' : CandidacyEvaluatorListingQuery::DEFAULT_DIRECTION;
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

    public function page(): int
    {
        return max(1, $this->integer('page', 1));
    }

    public function perPage(): int
    {
        return min(100, max(1, $this->integer('per_page', 15)));
    }
}
