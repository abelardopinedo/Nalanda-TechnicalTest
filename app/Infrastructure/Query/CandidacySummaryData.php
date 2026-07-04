<?php

namespace App\Infrastructure\Query;

use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Candidacy\Domain\CandidacyStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-side view of a candidacy, assembled entirely from data already
 * persisted (the candidacies row, its latest validation activity_log entry,
 * and its evaluator, if any). Never hydrates the Candidacy aggregate and
 * never runs the ValidationChain — every derived value here is a pure
 * function of stored data.
 */
final class CandidacySummaryData
{
    public function __construct(
        public readonly CandidacyModel $candidacy,
        public readonly ?ActivityLogModel $validationEntry,
        public readonly ?EvaluatorModel $evaluator,
    ) {
    }

    public function hasBeenEvaluated(): bool
    {
        return $this->validationEntry !== null;
    }

    public function validationOutcome(): ?string
    {
        return match ($this->validationEntry?->action) {
            'candidacy_validated' => CandidacyStatus::VALIDATED->value,
            'candidacy_rejected' => CandidacyStatus::REJECTED->value,
            default => null,
        };
    }

    public function validationPassed(): ?bool
    {
        return match ($this->validationOutcome()) {
            CandidacyStatus::VALIDATED->value => true,
            CandidacyStatus::REJECTED->value => false,
            default => null,
        };
    }

    /**
     * @return Collection<int, string>
     */
    public function failedReasons(): Collection
    {
        return collect($this->validationEntry?->payload['reasons'] ?? []);
    }

    public function evaluatedAt(): ?Carbon
    {
        return $this->validationEntry !== null
            ? Carbon::instance($this->validationEntry->occurred_at)
            : null;
    }

    public function daysSinceRegistration(): float
    {
        return $this->daysBetween(Carbon::instance($this->candidacy->created_at), Carbon::now());
    }

    public function timeToDecisionDays(): ?float
    {
        if ($this->validationEntry === null) {
            return null;
        }

        return $this->daysBetween(
            Carbon::instance($this->candidacy->created_at),
            Carbon::instance($this->validationEntry->occurred_at),
        );
    }

    public function experienceTier(): string
    {
        return match (true) {
            $this->candidacy->years_of_experience >= 6 => 'Senior',
            $this->candidacy->years_of_experience >= 3 => 'Mid',
            default => 'Junior',
        };
    }

    private function daysBetween(Carbon $from, Carbon $to): float
    {
        return round(abs($to->diffInSeconds($from)) / 86400, 4);
    }
}
