<?php

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Candidacy\Application\CandidacyBulkLocker;

class EloquentCandidacyBulkLocker implements CandidacyBulkLocker
{
    public function __construct(private readonly CandidacyMapper $mapper)
    {
    }

    /**
     * @param  list<string>  $candidacyIds
     * @return list<\Candidacy\Domain\Candidacy>
     */
    public function lockManyForUpdate(array $candidacyIds): array
    {
        return CandidacyModel::query()
            ->whereIn('id', $candidacyIds)
            // Lock in a fixed, deterministic order (ascending id) so two
            // overlapping bulk requests always attempt their row locks in
            // the same sequence, regardless of the order candidacy_ids
            // were submitted in — otherwise two batches locking the same
            // rows in opposite orders could deadlock each other.
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (CandidacyModel $model) => $this->mapper->toDomain($model))
            ->all();
    }
}
