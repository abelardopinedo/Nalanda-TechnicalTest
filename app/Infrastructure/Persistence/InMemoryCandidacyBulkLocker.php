<?php

namespace App\Infrastructure\Persistence;

use Candidacy\Application\CandidacyBulkLocker;
use Candidacy\Domain\CandidacyRepository;

/**
 * Test double for CandidacyBulkLocker: no real locking (there is no
 * concurrency to guard against in-memory), just looks candidacies up by id
 * through whatever CandidacyRepository it wraps.
 */
class InMemoryCandidacyBulkLocker implements CandidacyBulkLocker
{
    public function __construct(private readonly CandidacyRepository $repository)
    {
    }

    /**
     * @param  list<string>  $candidacyIds
     * @return list<\Candidacy\Domain\Candidacy>
     */
    public function lockManyForUpdate(array $candidacyIds): array
    {
        $found = [];

        foreach ($candidacyIds as $id) {
            $candidacy = $this->repository->findById($id);

            if ($candidacy !== null) {
                $found[] = $candidacy;
            }
        }

        return $found;
    }
}
