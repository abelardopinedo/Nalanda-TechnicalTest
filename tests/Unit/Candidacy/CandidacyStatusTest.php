<?php

namespace Tests\Unit\Candidacy;

use Candidacy\Domain\CandidacyStatus;
use DomainException;
use PHPUnit\Framework\TestCase;

class CandidacyStatusTest extends TestCase
{
    public function test_received_can_transition_to_under_review(): void
    {
        $this->assertTrue(CandidacyStatus::RECEIVED->canTransitionTo(CandidacyStatus::UNDER_REVIEW));
    }

    public function test_under_review_can_transition_to_validated_or_rejected(): void
    {
        $this->assertTrue(CandidacyStatus::UNDER_REVIEW->canTransitionTo(CandidacyStatus::VALIDATED));
        $this->assertTrue(CandidacyStatus::UNDER_REVIEW->canTransitionTo(CandidacyStatus::REJECTED));
    }

    public function test_rejected_is_a_terminal_state(): void
    {
        $this->assertSame([], CandidacyStatus::REJECTED->allowedTransitions());
    }

    public function test_guard_throws_on_an_illegal_transition(): void
    {
        $this->expectException(DomainException::class);

        CandidacyStatus::RECEIVED->guardTransitionTo(CandidacyStatus::ASSIGNED);
    }

    public function test_guard_allows_a_legal_transition(): void
    {
        CandidacyStatus::RECEIVED->guardTransitionTo(CandidacyStatus::UNDER_REVIEW);

        $this->addToAssertionCount(1);
    }
}
