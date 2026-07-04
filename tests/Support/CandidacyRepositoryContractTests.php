<?php

namespace Tests\Support;

use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyRepository;
use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;

/**
 * Behavioural contract shared by every CandidacyRepository implementation
 * (in-memory fake and Eloquent), so both are proven to satisfy the same port.
 */
trait CandidacyRepositoryContractTests
{
    private ?CandidacyRepository $repositoryUnderTest = null;

    abstract protected function createRepository(): CandidacyRepository;

    /**
     * Override in DB-backed test cases that enforce referential integrity
     * on evaluator_id.
     */
    protected function ensureEvaluatorExists(string $evaluatorId): void
    {
    }

    public function test_it_returns_null_when_candidacy_is_not_found(): void
    {
        $this->assertNull($this->repository()->findById('missing-id'));
    }

    public function test_it_saves_and_retrieves_a_candidacy_by_id(): void
    {
        $repository = $this->repository();
        $candidacy = $this->makeCandidacy('candidacy-contract-1');

        $repository->save($candidacy);
        $found = $repository->findById('candidacy-contract-1');

        $this->assertNotNull($found);
        $this->assertSame('candidacy-contract-1', $found->id());
        $this->assertSame('Jane Candidate', $found->fullName());
        $this->assertTrue($found->email()->equals(new Email('jane.candidate@example.com')));
        $this->assertSame(4, $found->yearsOfExperience()->value());
        $this->assertSame('Some CV content.', $found->cvText()->value());
        $this->assertSame(CandidacyStatus::RECEIVED, $found->status());
        $this->assertNull($found->evaluatorId());
        $this->assertNull($found->assignedAt());
    }

    public function test_it_persists_status_transitions_and_evaluator_assignment(): void
    {
        $this->ensureEvaluatorExists('evaluator-contract-1');

        $repository = $this->repository();
        $candidacy = $this->makeCandidacy('candidacy-contract-2');
        $candidacy->validate();
        $candidacy->assignEvaluator('evaluator-contract-1');

        $repository->save($candidacy);
        $found = $repository->findById('candidacy-contract-2');

        $this->assertSame(CandidacyStatus::ASSIGNED, $found->status());
        $this->assertSame('evaluator-contract-1', $found->evaluatorId());
        $this->assertNotNull($found->assignedAt());
    }

    public function test_saving_clears_the_candidacys_pulled_domain_events(): void
    {
        $repository = $this->repository();
        $candidacy = $this->makeCandidacy('candidacy-contract-3');

        $repository->save($candidacy);

        $this->assertSame([], $candidacy->pullDomainEvents());
    }

    public function test_next_identity_returns_distinct_non_empty_ids(): void
    {
        $repository = $this->repository();

        $first = $repository->nextIdentity();
        $second = $repository->nextIdentity();

        $this->assertNotSame('', $first);
        $this->assertNotSame($first, $second);
    }

    private function makeCandidacy(string $id): Candidacy
    {
        return Candidacy::register(
            $id,
            'Jane Candidate',
            new Email('jane.candidate@example.com'),
            new YearsOfExperience(4),
            new CvText('Some CV content.'),
        );
    }

    private function repository(): CandidacyRepository
    {
        return $this->repositoryUnderTest ??= $this->createRepository();
    }
}
