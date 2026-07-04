<?php

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\Event\CandidacyRegistered;
use Candidacy\Domain\Event\EvaluatorAssigned;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CandidacyMapper
{
    public function toDomain(CandidacyModel $model): Candidacy
    {
        return Candidacy::reconstitute(
            $model->id,
            $model->full_name,
            new Email($model->email),
            new YearsOfExperience($model->years_of_experience),
            new CvText($model->cv_text),
            CandidacyStatus::from($model->status),
            $model->evaluator_id,
            $model->assigned_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Candidacy $candidacy): array
    {
        return [
            'id' => $candidacy->id(),
            'full_name' => $candidacy->fullName(),
            'email' => $candidacy->email()->value(),
            'years_of_experience' => $candidacy->yearsOfExperience()->value(),
            'cv_text' => $candidacy->cvText()->value(),
            'status' => $candidacy->status()->value,
            'evaluator_id' => $candidacy->evaluatorId(),
            'assigned_at' => $candidacy->assignedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventToActivityLogAttributes(object $event): array
    {
        return match (true) {
            $event instanceof CandidacyRegistered => [
                'id' => (string) Str::uuid7(),
                'candidacy_id' => $event->candidacyId,
                'evaluator_id' => null,
                'action' => 'candidacy_registered',
                'payload' => ['email' => $event->email],
                'occurred_at' => $event->occurredOn,
            ],
            $event instanceof EvaluatorAssigned => [
                'id' => (string) Str::uuid7(),
                'candidacy_id' => $event->candidacyId,
                'evaluator_id' => $event->evaluatorId,
                'action' => 'evaluator_assigned',
                'payload' => ['evaluator_id' => $event->evaluatorId],
                'occurred_at' => $event->occurredOn,
            ],
            default => throw new InvalidArgumentException('Unsupported domain event: ' . $event::class),
        };
    }
}
