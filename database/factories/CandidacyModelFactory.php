<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Candidacy\Domain\CandidacyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
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

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidacyStatus::VALIDATED->value,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidacyStatus::REJECTED->value,
        ]);
    }

    /**
     * Marks the candidacy as ASSIGNED. If no evaluator id is given, a new
     * evaluator is created for it via EvaluatorModelFactory.
     */
    public function assigned(?string $evaluatorId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidacyStatus::ASSIGNED->value,
            'evaluator_id' => $evaluatorId ?? EvaluatorModelFactory::new()->create()->id,
            'assigned_at' => now(),
        ]);
    }
}
