<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Illuminate\Database\Seeder;

/**
 * Manual-only seeder: 5 evaluators (idle, not yet assigned to anyone) and
 * 120 candidacies still in RECEIVED status.
 *
 * Run explicitly, e.g. `sail artisan db:seed --class=BulkReportReceivedSeeder`.
 * Not part of the default DatabaseSeeder run.
 */
class BulkReportReceivedSeeder extends Seeder
{
    private const EVALUATOR_COUNT = 5;

    private const CANDIDACY_COUNT = 120;

    public function run(): void
    {
        EvaluatorModel::factory()->count(self::EVALUATOR_COUNT)->create();

        CandidacyModel::factory()->count(self::CANDIDACY_COUNT)->create();
    }
}
