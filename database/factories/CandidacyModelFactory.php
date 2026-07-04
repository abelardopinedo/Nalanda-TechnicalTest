<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Candidacy\Domain\CandidacyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<CandidacyModel>
 */
class CandidacyModelFactory extends Factory
{
    protected $model = CandidacyModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'years_of_experience' => fake()->numberBetween(0, 15),
            'cv_text' => fake()->paragraph(),
            'status' => CandidacyStatus::RECEIVED->value,
            'evaluator_id' => null,
            'assigned_at' => null,
        ];
    }

    /**
     * Meets the business rules (MinimumExperienceRule, CvMinimumLengthRule):
     * at least 2 years of experience and a CV well over 50 characters.
     */
    public function eligible(): static
    {
        return $this->state(fn (array $attributes) => [
            'years_of_experience' => fake()->numberBetween(2, 15),
            'cv_text' => trim(fake()->paragraphs(3, true)),
        ]);
    }

    /**
     * Fails MinimumExperienceRule: 0 or 1 years of experience.
     */
    public function ineligible(): static
    {
        return $this->state(fn (array $attributes) => [
            'years_of_experience' => fake()->numberBetween(0, 1),
        ]);
    }

    /**
     * Sets status to VALIDATED and backdates registration so there is a
     * real gap for time_to_decision, plus writes the matching
     * candidacy_validated activity_log entry the real validate step would
     * have produced (attribute assignment only — no real transition/use
     * case is invoked).
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidacyStatus::VALIDATED->value,
        ])->afterCreating(function (CandidacyModel $candidacy) {
            $this->backdateAndLogValidation($candidacy, CandidacyStatus::VALIDATED, []);
        });
    }

    /**
     * Sets status to REJECTED and, like validated(), backdates registration
     * and writes the matching candidacy_rejected activity_log entry with a
     * plausible failed-reasons payload.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidacyStatus::REJECTED->value,
        ])->afterCreating(function (CandidacyModel $candidacy) {
            $this->backdateAndLogValidation($candidacy, CandidacyStatus::REJECTED, [
                "At least 2 years of experience are required, {$candidacy->years_of_experience} given.",
            ]);
        });
    }

    /**
     * Marks the candidacy as ASSIGNED. Assignment requires prior VALIDATED
     * status (the domain guard enforces this), so the fixture must carry
     * that history too: it reuses validated()'s own log-seeding logic to
     * write the same candidacy_validated entry, then sequences evaluator_id/
     * assigned_at and the evaluator_assigned entry strictly after it. If no
     * evaluator id is given, a new evaluator is created for it via
     * EvaluatorModelFactory.
     */
    public function assigned(?string $evaluatorId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidacyStatus::ASSIGNED->value,
            'evaluator_id' => $evaluatorId ?? EvaluatorModelFactory::new()->create()->id,
        ])->afterCreating(function (CandidacyModel $candidacy) {
            $decidedAt = $this->backdateAndLogValidation($candidacy, CandidacyStatus::VALIDATED, []);

            $assignedAt = $decidedAt->copy()->addHours(fake()->numberBetween(4, 48));

            $candidacy->forceFill(['assigned_at' => $assignedAt])->save();

            ActivityLogModel::query()->create([
                'id' => (string) Str::uuid7(),
                'candidacy_id' => $candidacy->id,
                'evaluator_id' => $candidacy->evaluator_id,
                'action' => 'evaluator_assigned',
                'payload' => ['evaluator_id' => $candidacy->evaluator_id],
                'occurred_at' => $assignedAt,
            ]);
        });
    }

    /**
     * Backdates the candidacy's created_at (bypassing $fillable via
     * forceFill, since timestamps aren't mass-assignable) and writes the
     * activity_log entry for the given outcome at a realistic point between
     * created_at and now, so time_to_decision has a meaningful, non-zero
     * value to compute. Returns the decision timestamp so callers that build
     * on top of it (e.g. assigned()) can sequence their own timestamps after
     * it.
     *
     * @param  list<string>  $reasons
     */
    private function backdateAndLogValidation(CandidacyModel $candidacy, CandidacyStatus $outcome, array $reasons): Carbon
    {
        // Registration is pushed back far enough (6-20 days) that even the
        // latest possible decision (+3 days) and, for assigned(), the latest
        // possible assignment after that (+48 hours) both still land safely
        // before "now" — timestamps must never appear to be in the future.
        $registeredAt = now()->subDays(fake()->numberBetween(6, 20));
        $decidedAt = $registeredAt->copy()->addDays(fake()->numberBetween(1, 3));

        $candidacy->forceFill(['created_at' => $registeredAt])->save();

        ActivityLogModel::query()->create([
            'id' => (string) Str::uuid7(),
            'candidacy_id' => $candidacy->id,
            'evaluator_id' => null,
            'action' => $outcome === CandidacyStatus::VALIDATED ? 'candidacy_validated' : 'candidacy_rejected',
            'payload' => [
                'outcome' => $outcome->value,
                'reasons' => $reasons,
            ],
            'occurred_at' => $decidedAt,
        ]);

        return $decidedAt;
    }
}
