<?php

namespace Database\Factories;

use App\Infrastructure\Report\ReportModel;
use App\Infrastructure\Report\ReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportModel>
 */
class ReportModelFactory extends Factory
{
    protected $model = ReportModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'requested_by_email' => fake()->unique()->safeEmail(),
            'status' => ReportStatus::PENDING->value,
            'file_path' => null,
            'idempotency_key' => null,
            'error_message' => null,
            'filters_snapshot' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::COMPLETED->value,
            'file_path' => "reports/{$attributes['id']}.xlsx",
            'completed_at' => now(),
        ]);
    }

    public function failed(string $errorMessage = 'Something went wrong.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::FAILED->value,
            'error_message' => $errorMessage,
        ]);
    }
}
