<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property object{
 *     candidacy_id: string,
 *     candidate_name: string,
 *     candidate_email: string,
 *     years: int,
 *     evaluator_id: string,
 *     evaluator_name: string,
 *     assigned_at: string|null,
 *     evaluator_total_assigned: int,
 *     evaluator_candidate_emails: string,
 * } $resource
 */
class CandidacyEvaluatorListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'candidacy_id' => $this->resource->candidacy_id,
            'candidate_name' => $this->resource->candidate_name,
            'candidate_email' => $this->resource->candidate_email,
            'years_of_experience' => (int) $this->resource->years,
            'evaluator_id' => $this->resource->evaluator_id,
            'evaluator_name' => $this->resource->evaluator_name,
            'assigned_at' => $this->resource->assigned_at,
            'evaluator_total_assigned' => $this->resource->evaluator_total_assigned,
            'evaluator_candidate_emails' => $this->resource->evaluator_candidate_emails,
        ];
    }
}
