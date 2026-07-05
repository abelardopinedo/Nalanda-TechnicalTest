<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP-level input guarding only: shape and presence of the candidacy id
 * list. Deliberately does NOT validate that each id refers to an existing
 * (or VALIDATED) candidacy — unknown/ineligible ids are reported back as
 * skipped rather than rejecting the whole batch with a 422.
 */
class BulkAssignEvaluatorRequest extends FormRequest
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
            'candidacy_ids' => ['required', 'array', 'min:1'],
            'candidacy_ids.*' => ['required', 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    public function candidacyIds(): array
    {
        return $this->input('candidacy_ids', []);
    }

    /**
     * Optional client-supplied replay key: idempotency is opt-in, only
     * applied when this header is present.
     */
    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return $key !== null && $key !== '' ? $key : null;
    }
}
