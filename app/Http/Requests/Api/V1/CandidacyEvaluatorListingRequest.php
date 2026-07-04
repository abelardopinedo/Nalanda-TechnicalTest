<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ResolvesCandidacyListingFilters;
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
            'sort' => ['nullable', 'string', Rule::in(array_keys(CandidacyEvaluatorListingQuery::FIELDS))],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'filter' => ['nullable', 'array'],
            'filter.*' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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

    public function sort(): string
    {
        return $this->string('sort')->toString() ?: CandidacyEvaluatorListingQuery::DEFAULT_SORT;
    }

    public function direction(): string
    {
        $direction = $this->string('direction')->lower()->toString();

        return $direction === 'asc' ? 'asc' : CandidacyEvaluatorListingQuery::DEFAULT_DIRECTION;
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
