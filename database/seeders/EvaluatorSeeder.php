<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EvaluatorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Alice Reviewer', 'email' => 'alice.reviewer@nalanda.test'],
            ['name' => 'Bruno Ferreira', 'email' => 'bruno.ferreira@nalanda.test'],
            ['name' => 'Carla Gomez', 'email' => 'carla.gomez@nalanda.test'],
        ] as $evaluator) {
            EvaluatorModel::query()->firstOrCreate(
                ['email' => $evaluator['email']],
                ['id' => (string) Str::uuid(), 'name' => $evaluator['name']],
            );
        }
    }
}
