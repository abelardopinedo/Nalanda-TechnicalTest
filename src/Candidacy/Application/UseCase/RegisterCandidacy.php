<?php

namespace Candidacy\Application\UseCase;

use Candidacy\Application\Command\RegisterCandidacyCommand;
use Candidacy\Application\Exception\CandidacyValidationException;
use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\ValidationChain;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;

final class RegisterCandidacy
{
    public function __construct(
        private readonly CandidacyRepository $repository,
        private readonly ValidationChain $validationChain,
    ) {
    }

    public function __invoke(RegisterCandidacyCommand $command): Candidacy
    {
        $report = $this->validationChain->run(new CandidacyApplicationData(
            fullName: $command->fullName,
            email: $command->email,
            yearsOfExperience: $command->yearsOfExperience,
            cvText: $command->cvText,
        ));

        if (! $report->isValid()) {
            throw new CandidacyValidationException($report->reasons());
        }

        $candidacy = Candidacy::register(
            $this->repository->nextIdentity(),
            $command->fullName,
            new Email($command->email),
            new YearsOfExperience($command->yearsOfExperience),
            new CvText($command->cvText),
        );

        $this->repository->save($candidacy);

        return $candidacy;
    }
}
