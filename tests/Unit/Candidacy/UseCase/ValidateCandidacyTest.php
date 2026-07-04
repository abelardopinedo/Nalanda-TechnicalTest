<?php

namespace Tests\Unit\Candidacy\UseCase;

use App\Infrastructure\Persistence\InMemoryCandidacyRepository;
use Candidacy\Application\Command\ValidateCandidacyCommand;
use Candidacy\Application\Exception\CandidacyNotFoundException;
use Candidacy\Application\UseCase\ValidateCandidacy;
use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationChain;
use Candidacy\Application\Validation\ValidationRule;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryTransactionManager;

/**
 * Exercises the ValidateCandidacy use case entirely in memory: no DB
 * connection, no Laravel container, and a hand-rolled ValidationChain so
 * the outcome (pass vs fail) is controlled directly by the test.
 */
class ValidateCandidacyTest extends TestCase
{
    public function test_it_transitions_to_validated_when_the_chain_passes(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->receivedCandidacy('candidacy-1'));

        $useCase = new ValidateCandidacy(
            $repository,
            $this->alwaysPassingChain(),
            new InMemoryTransactionManager(),
        );

        $outcome = $useCase(new ValidateCandidacyCommand('candidacy-1'));

        $this->assertTrue($outcome->report->isValid());
        $this->assertCount(1, $outcome->report->passed());
        $this->assertCount(0, $outcome->report->failed());
        $this->assertSame(CandidacyStatus::VALIDATED, $outcome->candidacy->status());
        $this->assertSame(CandidacyStatus::VALIDATED, $repository->findById('candidacy-1')->status());
    }

    public function test_it_transitions_to_rejected_when_the_chain_fails(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->receivedCandidacy('candidacy-2'));

        $useCase = new ValidateCandidacy(
            $repository,
            $this->alwaysFailingChain('Not enough experience.'),
            new InMemoryTransactionManager(),
        );

        $outcome = $useCase(new ValidateCandidacyCommand('candidacy-2'));

        $this->assertFalse($outcome->report->isValid());
        $this->assertSame(['Not enough experience.'], $outcome->report->reasons());
        $this->assertSame(CandidacyStatus::REJECTED, $outcome->candidacy->status());
        $this->assertSame(CandidacyStatus::REJECTED, $repository->findById('candidacy-2')->status());
    }

    public function test_it_throws_when_the_candidacy_does_not_exist(): void
    {
        $useCase = new ValidateCandidacy(
            new InMemoryCandidacyRepository(),
            $this->alwaysPassingChain(),
            new InMemoryTransactionManager(),
        );

        $this->expectException(CandidacyNotFoundException::class);

        $useCase(new ValidateCandidacyCommand('missing-id'));
    }

    public function test_it_throws_a_domain_exception_when_the_candidacy_is_no_longer_received(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $candidacy = $this->receivedCandidacy('candidacy-3');
        $candidacy->validate();
        $repository->save($candidacy);

        $useCase = new ValidateCandidacy(
            $repository,
            $this->alwaysPassingChain(),
            new InMemoryTransactionManager(),
        );

        $this->expectException(DomainException::class);

        $useCase(new ValidateCandidacyCommand('candidacy-3'));
    }

    private function receivedCandidacy(string $id): Candidacy
    {
        return Candidacy::register(
            $id,
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText('A sufficiently long curriculum vitae body for testing purposes.'),
        );
    }

    private function alwaysPassingChain(): ValidationChain
    {
        return new ValidationChain([
            new class implements ValidationRule
            {
                public function evaluate(CandidacyApplicationData $application): RuleResult
                {
                    return RuleResult::pass('AlwaysPasses');
                }
            },
        ]);
    }

    private function alwaysFailingChain(string $reason): ValidationChain
    {
        return new ValidationChain([
            new class($reason) implements ValidationRule
            {
                public function __construct(private readonly string $reason)
                {
                }

                public function evaluate(CandidacyApplicationData $application): RuleResult
                {
                    return RuleResult::fail('AlwaysFails', $this->reason);
                }
            },
        ]);
    }
}
