<?php

namespace Tests\Unit\Candidacy\UseCase;

use App\Infrastructure\Persistence\InMemoryCandidacyRepository;
use Candidacy\Application\Command\AssignEvaluatorCommand;
use Candidacy\Application\Exception\CandidacyNotFoundException;
use Candidacy\Application\TransactionManager;
use Candidacy\Application\UseCase\AssignEvaluator;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;
use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\Event\EvaluatorAssigned;
use Candidacy\Domain\YearsOfExperience;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryTransactionManager;

/**
 * Exercises the AssignEvaluator use case entirely in memory: no DB
 * connection, no Laravel container, just the in-memory repository fake and
 * a pass-through transaction manager.
 */
class AssignEvaluatorTest extends TestCase
{
    public function test_it_assigns_the_evaluator_sets_assigned_at_and_persists_via_the_repository(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-1'));

        $useCase = new AssignEvaluator($repository, new InMemoryTransactionManager());

        $updated = $useCase(new AssignEvaluatorCommand('candidacy-1', 'evaluator-1'));

        $this->assertSame(CandidacyStatus::ASSIGNED, $updated->status());
        $this->assertSame('evaluator-1', $updated->evaluatorId());
        $this->assertNotNull($updated->assignedAt());

        $persisted = $repository->findById('candidacy-1');
        $this->assertSame(CandidacyStatus::ASSIGNED, $persisted->status());
        $this->assertSame('evaluator-1', $persisted->evaluatorId());
    }

    public function test_it_raises_an_evaluator_assigned_event_within_the_transaction(): void
    {
        $spy = new EventCapturingCandidacyRepository(new InMemoryCandidacyRepository());
        $spy->save($this->validatedCandidacy('candidacy-2'));
        $spy->capturedEvents = [];

        $useCase = new AssignEvaluator($spy, new InMemoryTransactionManager());

        $useCase(new AssignEvaluatorCommand('candidacy-2', 'evaluator-2'));

        $this->assertCount(1, $spy->capturedEvents);
        $this->assertInstanceOf(EvaluatorAssigned::class, $spy->capturedEvents[0]);
        $this->assertSame('candidacy-2', $spy->capturedEvents[0]->candidacyId);
        $this->assertSame('evaluator-2', $spy->capturedEvents[0]->evaluatorId);
    }

    public function test_it_runs_the_operation_through_the_injected_transaction_manager(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-3'));

        $transactions = new RecordingTransactionManager();
        $useCase = new AssignEvaluator($repository, $transactions);

        $useCase(new AssignEvaluatorCommand('candidacy-3', 'evaluator-3'));

        $this->assertSame(1, $transactions->timesRun);
    }

    public function test_it_throws_when_the_candidacy_does_not_exist(): void
    {
        $useCase = new AssignEvaluator(new InMemoryCandidacyRepository(), new InMemoryTransactionManager());

        $this->expectException(CandidacyNotFoundException::class);

        $useCase(new AssignEvaluatorCommand('missing-id', 'evaluator-1'));
    }

    private function validatedCandidacy(string $id): Candidacy
    {
        $candidacy = Candidacy::register(
            $id,
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText('Some CV content.'),
        );

        $candidacy->startReview();
        $candidacy->validate();

        return $candidacy;
    }
}

final class RecordingTransactionManager implements TransactionManager
{
    public int $timesRun = 0;

    public function run(callable $operation): mixed
    {
        $this->timesRun++;

        return $operation();
    }
}

final class EventCapturingCandidacyRepository implements CandidacyRepository
{
    /** @var list<object> */
    public array $capturedEvents = [];

    public function __construct(private readonly CandidacyRepository $inner)
    {
    }

    public function nextIdentity(): string
    {
        return $this->inner->nextIdentity();
    }

    public function save(Candidacy $candidacy): void
    {
        $this->capturedEvents = array_merge($this->capturedEvents, $candidacy->pullDomainEvents());

        $this->inner->save($candidacy);
    }

    public function findById(string $id): ?Candidacy
    {
        return $this->inner->findById($id);
    }
}
