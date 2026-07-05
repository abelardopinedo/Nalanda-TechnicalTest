<?php

namespace Tests\Unit\Candidacy\UseCase;

use App\Infrastructure\Persistence\InMemoryCandidacyBulkLocker;
use App\Infrastructure\Persistence\InMemoryCandidacyRepository;
use Candidacy\Application\Command\BulkAssignEvaluatorCommand;
use Candidacy\Application\TransactionManager;
use Candidacy\Application\UseCase\BulkAssignEvaluator;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryTransactionManager;

/**
 * Exercises the BulkAssignEvaluator use case entirely in memory: no DB
 * connection, no Laravel container, just the in-memory repository/locker
 * fakes and a pass-through transaction manager.
 */
class BulkAssignEvaluatorTest extends TestCase
{
    public function test_it_assigns_every_validated_candidacy_in_the_batch(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-1'));
        $repository->save($this->validatedCandidacy('candidacy-2'));

        $useCase = new BulkAssignEvaluator(
            new InMemoryCandidacyBulkLocker($repository),
            $repository,
            new InMemoryTransactionManager(),
        );

        $result = $useCase(new BulkAssignEvaluatorCommand('evaluator-1', ['candidacy-1', 'candidacy-2']));

        $this->assertSame(['candidacy-1', 'candidacy-2'], $result->assigned);
        $this->assertSame([], $result->skipped);

        foreach (['candidacy-1', 'candidacy-2'] as $id) {
            $persisted = $repository->findById($id);
            $this->assertSame(CandidacyStatus::ASSIGNED, $persisted->status());
            $this->assertSame('evaluator-1', $persisted->evaluatorId());
        }
    }

    public function test_it_skips_a_candidacy_that_is_not_validated_and_still_assigns_the_rest(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-1'));
        $repository->save($this->receivedCandidacy('candidacy-2'));

        $useCase = new BulkAssignEvaluator(
            new InMemoryCandidacyBulkLocker($repository),
            $repository,
            new InMemoryTransactionManager(),
        );

        $result = $useCase(new BulkAssignEvaluatorCommand('evaluator-1', ['candidacy-1', 'candidacy-2']));

        $this->assertSame(['candidacy-1'], $result->assigned);
        $this->assertCount(1, $result->skipped);
        $this->assertSame('candidacy-2', $result->skipped[0]->candidacyId);
        $this->assertSame('not_validated:received', $result->skipped[0]->reason);
    }

    public function test_it_skips_an_unknown_candidacy_id(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-1'));

        $useCase = new BulkAssignEvaluator(
            new InMemoryCandidacyBulkLocker($repository),
            $repository,
            new InMemoryTransactionManager(),
        );

        $result = $useCase(new BulkAssignEvaluatorCommand('evaluator-1', ['candidacy-1', 'missing-id']));

        $this->assertSame(['candidacy-1'], $result->assigned);
        $this->assertCount(1, $result->skipped);
        $this->assertSame('missing-id', $result->skipped[0]->candidacyId);
        $this->assertSame('not_found', $result->skipped[0]->reason);
    }

    public function test_a_duplicate_id_in_the_same_batch_is_only_assigned_once(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-1'));

        $useCase = new BulkAssignEvaluator(
            new InMemoryCandidacyBulkLocker($repository),
            $repository,
            new InMemoryTransactionManager(),
        );

        $result = $useCase(new BulkAssignEvaluatorCommand('evaluator-1', ['candidacy-1', 'candidacy-1']));

        $this->assertSame(['candidacy-1'], $result->assigned);
        $this->assertCount(1, $result->skipped);
        $this->assertSame('not_validated:assigned', $result->skipped[0]->reason);
    }

    public function test_it_runs_the_operation_through_the_injected_transaction_manager(): void
    {
        $repository = new InMemoryCandidacyRepository();
        $repository->save($this->validatedCandidacy('candidacy-1'));

        $transactions = new RecordingBulkTransactionManager();
        $useCase = new BulkAssignEvaluator(new InMemoryCandidacyBulkLocker($repository), $repository, $transactions);

        $useCase(new BulkAssignEvaluatorCommand('evaluator-1', ['candidacy-1']));

        $this->assertSame(1, $transactions->timesRun);
    }

    private function validatedCandidacy(string $id): Candidacy
    {
        $candidacy = $this->receivedCandidacy($id);
        $candidacy->validate();

        return $candidacy;
    }

    private function receivedCandidacy(string $id): Candidacy
    {
        return Candidacy::register(
            $id,
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText('Some CV content.'),
        );
    }
}

final class RecordingBulkTransactionManager implements TransactionManager
{
    public int $timesRun = 0;

    public function run(callable $operation): mixed
    {
        $this->timesRun++;

        return $operation();
    }
}
