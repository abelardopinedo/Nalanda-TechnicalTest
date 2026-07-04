<?php

namespace Tests\Unit\Candidacy;

use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\Event\CandidacyRegistered;
use Candidacy\Domain\Event\CandidacyValidated;
use Candidacy\Domain\Event\EvaluatorAssigned;
use Candidacy\Domain\Exception\InvalidCandidacyStatusTransition;
use Candidacy\Domain\YearsOfExperience;
use PHPUnit\Framework\TestCase;

class CandidacyTest extends TestCase
{
    private function registerCandidacy(): Candidacy
    {
        return Candidacy::register(
            'candidacy-1',
            'Jane Candidate',
            new Email('candidate@example.com'),
            new YearsOfExperience(4),
            new CvText('Some CV content.'),
        );
    }

    public function test_registering_a_candidacy_starts_in_received_status(): void
    {
        $candidacy = $this->registerCandidacy();

        $this->assertSame(CandidacyStatus::RECEIVED, $candidacy->status());
        $this->assertNull($candidacy->evaluatorId());
    }

    public function test_registering_a_candidacy_records_a_candidacy_registered_event(): void
    {
        $candidacy = $this->registerCandidacy();

        $events = $candidacy->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(CandidacyRegistered::class, $events[0]);
        $this->assertSame('candidacy-1', $events[0]->candidacyId);
        $this->assertSame('candidate@example.com', $events[0]->email);
    }

    public function test_pulling_domain_events_clears_them(): void
    {
        $candidacy = $this->registerCandidacy();

        $candidacy->pullDomainEvents();

        $this->assertSame([], $candidacy->pullDomainEvents());
    }

    public function test_full_happy_path_to_assignment(): void
    {
        $candidacy = $this->registerCandidacy();

        $candidacy->validate();
        $this->assertSame(CandidacyStatus::VALIDATED, $candidacy->status());

        $candidacy->assignEvaluator('evaluator-1');
        $this->assertSame(CandidacyStatus::ASSIGNED, $candidacy->status());
        $this->assertSame('evaluator-1', $candidacy->evaluatorId());
    }

    public function test_assigning_an_evaluator_records_an_evaluator_assigned_event(): void
    {
        $candidacy = $this->registerCandidacy();
        $candidacy->validate();
        $candidacy->pullDomainEvents();

        $candidacy->assignEvaluator('evaluator-1');

        $events = $candidacy->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EvaluatorAssigned::class, $events[0]);
        $this->assertSame('candidacy-1', $events[0]->candidacyId);
        $this->assertSame('evaluator-1', $events[0]->evaluatorId);
    }

    public function test_recording_a_validation_outcome_after_validating_captures_the_empty_reasons(): void
    {
        $candidacy = $this->registerCandidacy();
        $candidacy->validate();
        $candidacy->pullDomainEvents();

        $candidacy->recordValidationOutcome([]);

        $events = $candidacy->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(CandidacyValidated::class, $events[0]);
        $this->assertSame('candidacy-1', $events[0]->candidacyId);
        $this->assertSame(CandidacyStatus::VALIDATED, $events[0]->outcome);
        $this->assertSame([], $events[0]->reasons);
    }

    public function test_recording_a_validation_outcome_after_rejecting_captures_the_failed_reasons(): void
    {
        $candidacy = $this->registerCandidacy();
        $candidacy->reject();
        $candidacy->pullDomainEvents();

        $candidacy->recordValidationOutcome(['At least 2 years of experience are required, 0 given.']);

        $events = $candidacy->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(CandidacyValidated::class, $events[0]);
        $this->assertSame('candidacy-1', $events[0]->candidacyId);
        $this->assertSame(CandidacyStatus::REJECTED, $events[0]->outcome);
        $this->assertSame(['At least 2 years of experience are required, 0 given.'], $events[0]->reasons);
    }

    public function test_a_rejected_candidacy_cannot_be_assigned(): void
    {
        $candidacy = $this->registerCandidacy();
        $candidacy->reject();

        $this->expectException(InvalidCandidacyStatusTransition::class);

        $candidacy->assignEvaluator('evaluator-1');
    }

    public function test_a_candidacy_cannot_be_assigned_before_validation(): void
    {
        $candidacy = $this->registerCandidacy();

        $this->expectException(InvalidCandidacyStatusTransition::class);

        $candidacy->assignEvaluator('evaluator-1');
    }
}
