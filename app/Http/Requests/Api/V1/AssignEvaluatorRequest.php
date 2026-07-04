<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP-level input guarding only: shape, presence, and referential existence
 * of the evaluator. The candidacy's own eligibility for assignment (status
 * transition rules) is enforced by the domain, not this request.
 */
class AssignEvaluatorRequest extends FormRequest
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
            'evaluator_id' => ['required', 'string', 'exists:evaluators,id'],
        ];
    }
}
