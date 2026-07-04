<?php

namespace App\Http\Resources\Api\V1;

use Candidacy\Domain\Candidacy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Candidacy $resource
 */
class CandidacyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'full_name' => $this->resource->fullName(),
            'email' => $this->resource->email()->value(),
            'years_of_experience' => $this->resource->yearsOfExperience()->value(),
            'cv_text' => $this->resource->cvText()->value(),
            'status' => $this->resource->status()->value,
            'evaluator_id' => $this->resource->evaluatorId(),
            'assigned_at' => $this->resource->assignedAt()?->format(DATE_ATOM),
        ];
    }
}
