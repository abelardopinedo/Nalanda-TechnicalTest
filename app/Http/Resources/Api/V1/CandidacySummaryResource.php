<?php

namespace App\Http\Resources\Api\V1;

use App\Infrastructure\Query\CandidacySummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CandidacySummaryData $resource
 */
class CandidacySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $candidacy = $this->resource->candidacy;
        $evaluator = $this->resource->evaluator;

        return [
            'id' => $candidacy->id,
            'full_name' => $candidacy->full_name,
            'email' => $candidacy->email,
            'years_of_experience' => (int) $candidacy->years_of_experience,
            'cv_text' => $candidacy->cv_text,
            'status' => $candidacy->status,
            'created_at' => $candidacy->created_at?->toAtomString(),
            'evaluator' => $evaluator !== null ? [
                'name' => $evaluator->name,
                'assigned_at' => $candidacy->assigned_at?->toAtomString(),
            ] : null,
            'validation' => [
                'evaluated' => $this->resource->hasBeenEvaluated(),
                'outcome' => $this->resource->validationOutcome() ?? 'not_yet_evaluated',
                'passed' => $this->resource->validationPassed(),
                'evaluated_at' => $this->resource->evaluatedAt()?->toAtomString(),
                'failed_reasons' => $this->resource->failedReasons(),
            ],
            'derived' => [
                'days_since_registration' => $this->resource->daysSinceRegistration(),
                'time_to_decision_days' => $this->resource->timeToDecisionDays(),
                'experience_tier' => $this->resource->experienceTier(),
            ],
        ];
    }
}
