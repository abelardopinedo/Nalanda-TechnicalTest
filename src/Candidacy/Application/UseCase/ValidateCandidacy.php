<?php

namespace Candidacy\Application\UseCase;

use Candidacy\Application\Command\ValidateCandidacyCommand;
use Candidacy\Application\Exception\CandidacyNotFoundException;
use Candidacy\Application\TransactionManager;
use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\ValidationChain;
use Candidacy\Domain\CandidacyRepository;

/**
 * The single endpoint for requirement #2: evaluating a candidacy and
 * committing the outcome happen atomically, in one call. There is no
 * separate read-only check and no separate manual reject action — the
 * ValidationChain's verdict decides the transition (RECEIVED -> VALIDATED
 * or RECEIVED -> REJECTED) right here.
 */
final class ValidateCandidacy
{
    public function __construct(
        private readonly CandidacyRepository $repository,
        private readonly ValidationChain $validationChain,
        private readonly TransactionManager $transactions,
    ) {
    }

    public function __invoke(ValidateCandidacyCommand $command): CandidacyValidationOutcome
    {
        return $this->transactions->run(function () use ($command): CandidacyValidationOutcome {
            $candidacy = $this->repository->findById($command->candidacyId);

            if ($candidacy === null) {
                throw new CandidacyNotFoundException($command->candidacyId);
            }

            $report = $this->validationChain->run(new CandidacyApplicationData(
                fullName: $candidacy->fullName(),
                email: $candidacy->email()->value(),
                yearsOfExperience: $candidacy->yearsOfExperience()->value(),
                cvText: $candidacy->cvText()->value(),
            ));

            if ($report->isValid()) {
                $candidacy->validate();
            } else {
                $candidacy->reject();
            }

            $candidacy->recordValidationOutcome($report->reasons());

            $this->repository->save($candidacy);

            return new CandidacyValidationOutcome($candidacy, $report);
        });
    }
}
