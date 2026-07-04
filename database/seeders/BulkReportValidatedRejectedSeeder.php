<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Illuminate\Database\Seeder;

/**
 * Manual-only seeder: 5 evaluators plus 60 VALIDATED candidacies (eligible:
 * >= 2 years of experience, CV over 50 characters) and 60 REJECTED
 * candidacies (ineligible: 0 or 1 years of experience).
 *
 * Run explicitly, e.g. `sail artisan db:seed --class=BulkReportValidatedRejectedSeeder`.
 * Not part of the default DatabaseSeeder run.
 */
class BulkReportValidatedRejectedSeeder extends Seeder
{
    private const EVALUATOR_COUNT = 5;

    private const VALIDATED_COUNT = 60;

    private const REJECTED_COUNT = 60;

    public function run(): void
    {
        EvaluatorModel::factory()->count(self::EVALUATOR_COUNT)->create();

        CandidacyModel::factory()
            ->count(self::VALIDATED_COUNT)
            ->eligible()
            ->validated()
            ->create();

        CandidacyModel::factory()
            ->count(self::REJECTED_COUNT)
            ->ineligible()
            ->rejected()
            ->create();
    }
}
