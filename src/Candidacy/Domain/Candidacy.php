<?php

namespace Candidacy\Domain;

use Candidacy\Domain\Event\CandidacyRegistered;
use Candidacy\Domain\Event\EvaluatorAssigned;
use DateTimeImmutable;

final class Candidacy
{
    /** @var list<object> */
    private array $domainEvents = [];

    private ?string $evaluatorId = null;

    private ?DateTimeImmutable $assignedAt = null;

    private function __construct(
        private readonly string $id,
        private readonly string $fullName,
        private readonly Email $email,
        private readonly YearsOfExperience $yearsOfExperience,
        private readonly CvText $cvText,
        private CandidacyStatus $status,
    ) {
    }

    public static function register(
        string $id,
        string $fullName,
        Email $email,
        YearsOfExperience $yearsOfExperience,
        CvText $cvText,
    ): self {
        $candidacy = new self($id, $fullName, $email, $yearsOfExperience, $cvText, CandidacyStatus::RECEIVED);

        $candidacy->recordThat(new CandidacyRegistered($id, $email->value()));

        return $candidacy;
    }

    /**
     * Rehydrates a candidacy from persisted state without replaying domain events
     * or re-validating already-applied status transitions.
     */
    public static function reconstitute(
        string $id,
        string $fullName,
        Email $email,
        YearsOfExperience $yearsOfExperience,
        CvText $cvText,
        CandidacyStatus $status,
        ?string $evaluatorId,
        ?DateTimeImmutable $assignedAt,
    ): self {
        $candidacy = new self($id, $fullName, $email, $yearsOfExperience, $cvText, $status);
        $candidacy->evaluatorId = $evaluatorId;
        $candidacy->assignedAt = $assignedAt;

        return $candidacy;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function yearsOfExperience(): YearsOfExperience
    {
        return $this->yearsOfExperience;
    }

    public function cvText(): CvText
    {
        return $this->cvText;
    }

    public function status(): CandidacyStatus
    {
        return $this->status;
    }

    public function evaluatorId(): ?string
    {
        return $this->evaluatorId;
    }

    public function assignedAt(): ?DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function validate(): void
    {
        $this->transitionTo(CandidacyStatus::VALIDATED);
    }

    public function reject(): void
    {
        $this->transitionTo(CandidacyStatus::REJECTED);
    }

    public function assignEvaluator(string $evaluatorId): void
    {
        $this->transitionTo(CandidacyStatus::ASSIGNED);

        $this->evaluatorId = $evaluatorId;
        $this->assignedAt = new DateTimeImmutable();

        $this->recordThat(new EvaluatorAssigned($this->id, $evaluatorId));
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;

        $this->domainEvents = [];

        return $events;
    }

    private function transitionTo(CandidacyStatus $target): void
    {
        $this->status->guardTransitionTo($target);

        $this->status = $target;
    }

    private function recordThat(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
