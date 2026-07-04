<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EvaluatorModel>
 */
class EvaluatorModelFactory extends Factory
{
    protected $model = EvaluatorModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
