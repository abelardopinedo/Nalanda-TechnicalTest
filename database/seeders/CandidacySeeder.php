<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Database\Seeder;

class CandidacySeeder extends Seeder
{
    public function run(): void
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());
        $evaluatorIds = EvaluatorModel::query()->pluck('id')->all();

        $candidates = [
            ['name' => 'Diego Martinez', 'email' => 'diego.martinez@example.com', 'years' => 2, 'cv' => 'Backend developer with 2 years of experience in PHP and Laravel.', 'stage' => 'received'],
            ['name' => 'Elena Petrova', 'email' => 'elena.petrova@example.com', 'years' => 5, 'cv' => 'Full-stack engineer with 5 years across React and Node.js.', 'stage' => 'validated'],
            ['name' => 'Farid Haidari', 'email' => 'farid.haidari@example.com', 'years' => 8, 'cv' => 'Senior engineer specialising in distributed systems, 8 years.', 'stage' => 'validated'],
            ['name' => 'Grace Okafor', 'email' => 'grace.okafor@example.com', 'years' => 6, 'cv' => 'Platform engineer with a strong DDD and hexagonal architecture background.', 'stage' => 'assigned'],
            ['name' => 'Hiro Tanaka', 'email' => 'hiro.tanaka@example.com', 'years' => 1, 'cv' => 'Junior developer, recent bootcamp graduate.', 'stage' => 'rejected'],
        ];

        foreach ($candidates as $index => $data) {
            $candidacy = Candidacy::register(
                $repository->nextIdentity(),
                $data['name'],
                new Email($data['email']),
                new YearsOfExperience($data['years']),
                new CvText($data['cv']),
            );

            $this->advanceToStage($candidacy, $data['stage'], $evaluatorIds, $index);

            $repository->save($candidacy);
        }
    }

    /**
     * @param  list<string>  $evaluatorIds
     */
    private function advanceToStage(Candidacy $candidacy, string $stage, array $evaluatorIds, int $index): void
    {
        if ($stage === 'received') {
            return;
        }

        if ($stage === 'rejected') {
            $candidacy->reject();

            return;
        }

        $candidacy->validate();

        if ($stage === 'assigned' && $evaluatorIds !== []) {
            $candidacy->assignEvaluator($evaluatorIds[$index % count($evaluatorIds)]);
        }
    }
}
