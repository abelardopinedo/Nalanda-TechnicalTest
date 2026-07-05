<?php

namespace Candidacy\Application\UseCase;

use Candidacy\Application\CandidacyBulkLocker;
use Candidacy\Application\Command\BulkAssignEvaluatorCommand;
use Candidacy\Application\TransactionManager;
use Candidacy\Domain\CandidacyRepository;
use Candidacy\Domain\CandidacyStatus;

/**
 * Assigns one evaluator to many candidacies in a single request. Only
 * VALIDATED candidacies are eligible: anything else in the batch (not
 * found, or in some other status — including one already ASSIGNED by a
 * request that raced this one) is skipped and reported back rather than
 * failing the whole batch.
 *
 * Runs inside one transaction, with every candidacy row locked
 * (CandidacyBulkLocker::lockManyForUpdate — SELECT ... FOR UPDATE) before
 * any of them are read, so a second, overlapping bulk-assign request must
 * wait for this one to commit before it can see (and correctly skip) rows
 * this request already claimed.
 */
final class BulkAssignEvaluator
{
    public function __construct(
        private readonly CandidacyBulkLocker $locker,
        private readonly CandidacyRepository $repository,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function __invoke(BulkAssignEvaluatorCommand $command): BulkAssignmentResult
    {
        return $this->transactions->run(function () use ($command): BulkAssignmentResult {
            $lockedById = [];

            foreach ($this->locker->lockManyForUpdate($command->candidacyIds) as $candidacy) {
                $lockedById[$candidacy->id()] = $candidacy;
            }

            $assigned = [];
            $skipped = [];

            foreach ($command->candidacyIds as $candidacyId) {
                $candidacy = $lockedById[$candidacyId] ?? null;

                if ($candidacy === null) {
                    $skipped[] = new SkippedAssignment($candidacyId, 'not_found');

                    continue;
                }

                if ($candidacy->status() !== CandidacyStatus::VALIDATED) {
                    $skipped[] = new SkippedAssignment($candidacyId, "not_validated:{$candidacy->status()->value}");

                    continue;
                }

                $candidacy->assignEvaluator($command->evaluatorId);
                $this->repository->save($candidacy);
                $assigned[] = $candidacyId;
            }

            return new BulkAssignmentResult($assigned, $skipped);
        });
    }
}
