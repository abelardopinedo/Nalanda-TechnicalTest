<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Illuminate\Database\Seeder;

/**
 * Manual-only seeder: 1 evaluator with 51 candidacies already assigned to
 * them, all eligible (>= 2 years of experience, CV over 50 characters).
 * Exercises the Excel report's per-sheet pagination with a single full
 * sheet (50) plus a one-row overflow sheet.
 *
 * Run explicitly, e.g. `sail artisan db:seed --class=BulkReportSingleEvaluatorSeeder`.
 * Not part of the default DatabaseSeeder run.
 */
class BulkReportSingleEvaluatorSeeder extends Seeder
{
    private const CANDIDACY_COUNT = 51;

    public function run(): void
    {
        $evaluator = EvaluatorModel::factory()->create();

        CandidacyModel::factory()
            ->count(self::CANDIDACY_COUNT)
            ->eligible()
            ->assigned($evaluator->id)
            ->create();
    }
}
