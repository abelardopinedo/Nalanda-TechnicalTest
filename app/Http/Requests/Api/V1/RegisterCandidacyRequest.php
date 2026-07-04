<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP-level input guarding only: shape and presence of the payload.
 * Business rules (disposable emails, minimum experience, CV quality, ...)
 * are the domain's ValidationChain's responsibility, not this request's.
 */
class RegisterCandidacyRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'years_of_experience' => ['required', 'integer', 'min:0'],
            'cv_text' => ['required', 'string'],
        ];
    }
}
