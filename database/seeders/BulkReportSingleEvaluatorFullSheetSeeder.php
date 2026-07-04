<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Illuminate\Database\Seeder;

/**
 * Manual-only seeder: 1 evaluator with exactly 50 eligible candidacies
 * (>= 2 years of experience, CV over 50 characters) already assigned to
 * them. Exercises the Excel report's per-sheet pagination with exactly one
 * full sheet and no overflow row.
 *
 * Run explicitly, e.g. `sail artisan db:seed --class=BulkReportSingleEvaluatorFullSheetSeeder`.
 * Not part of the default DatabaseSeeder run.
 */
class BulkReportSingleEvaluatorFullSheetSeeder extends Seeder
{
    private const CANDIDACY_COUNT = 50;

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
