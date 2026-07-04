<?php

namespace Candidacy\Application\UseCase;

use Candidacy\Application\Command\AssignEvaluatorCommand;
use Candidacy\Application\Exception\CandidacyNotFoundException;
use Candidacy\Application\TransactionManager;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;

final class AssignEvaluator
{
    public function __construct(
        private readonly CandidacyRepository $repository,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function __invoke(AssignEvaluatorCommand $command): Candidacy
    {
        return $this->transactions->run(function () use ($command): Candidacy {
            $candidacy = $this->repository->findById($command->candidacyId);

            if ($candidacy === null) {
                throw new CandidacyNotFoundException($command->candidacyId);
            }

            $candidacy->assignEvaluator($command->evaluatorId);

            $this->repository->save($candidacy);

            return $candidacy;
        });
    }
}
