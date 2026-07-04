<?php

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class EloquentCandidacyRepository implements CandidacyRepository
{
    public function __construct(private readonly CandidacyMapper $mapper)
    {
    }

    public function nextIdentity(): string
    {
        return (string) Str::uuid();
    }

    public function save(Candidacy $candidacy): void
    {
        $events = $candidacy->pullDomainEvents();

        DB::transaction(function () use ($candidacy, $events): void {
            $model = CandidacyModel::query()->find($candidacy->id()) ?? new CandidacyModel();

            $model->fill($this->mapper->toAttributes($candidacy));
            $model->save();

            foreach ($events as $event) {
                Event::dispatch($event);
            }
        });
    }

    public function findById(string $id): ?Candidacy
    {
        $model = CandidacyModel::query()->find($id);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }
}
