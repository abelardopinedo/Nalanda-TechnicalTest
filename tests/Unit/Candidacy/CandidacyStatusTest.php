<?php

namespace Tests\Unit\Candidacy;

use Candidacy\Domain\CandidacyStatus;
use Candidacy\Domain\Exception\InvalidCandidacyStatusTransition;
use PHPUnit\Framework\TestCase;

class CandidacyStatusTest extends TestCase
{
    public function test_received_can_transition_to_validated_or_rejected(): void
    {
        $this->assertTrue(CandidacyStatus::RECEIVED->canTransitionTo(CandidacyStatus::VALIDATED));
        $this->assertTrue(CandidacyStatus::RECEIVED->canTransitionTo(CandidacyStatus::REJECTED));
    }

    public function test_rejected_is_a_terminal_state(): void
    {
        $this->assertSame([], CandidacyStatus::REJECTED->allowedTransitions());
    }

    public function test_guard_throws_on_an_illegal_transition(): void
    {
        $this->expectException(InvalidCandidacyStatusTransition::class);
        $this->expectExceptionMessage('Cannot transition candidacy status from received to assigned.');

        CandidacyStatus::RECEIVED->guardTransitionTo(CandidacyStatus::ASSIGNED);
    }

    public function test_guard_allows_a_legal_transition(): void
    {
        CandidacyStatus::RECEIVED->guardTransitionTo(CandidacyStatus::VALIDATED);

        $this->addToAssertionCount(1);
    }
}
