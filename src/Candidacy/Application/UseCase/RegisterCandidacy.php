<?php

namespace Candidacy\Application\UseCase;

use Candidacy\Application\Command\RegisterCandidacyCommand;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;

final class RegisterCandidacy
{
    public function __construct(
        private readonly CandidacyRepository $repository,
    ) {
    }

    public function __invoke(RegisterCandidacyCommand $command): Candidacy
    {
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
