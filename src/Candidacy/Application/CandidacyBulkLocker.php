<?php

namespace Candidacy\Application;

use Candidacy\Domain\Candidacy;

/**
 * Port for fetching multiple candidacies locked for update within the
 * current transaction, so a bulk operation can read-then-write each one
 * without a concurrent request racing it on the same row.
 *
 * Kept separate from CandidacyRepository (a single-item, lock-free port
 * used by every other use case): pessimistic row locking only matters to
 * bulk operations and is meaningless for the in-memory fake used by unit
 * tests, so it doesn't belong on the shared, contract-tested interface.
 */
interface CandidacyBulkLocker
{
    /**
     * @param  list<string>  $candidacyIds
     * @return list<Candidacy> only the candidacies that actually exist, in no particular order
     */
    public function lockManyForUpdate(array $candidacyIds): array;
}
