<?php

namespace Tests\Unit\Candidacy\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\Rules\ValidEmailRule;
use PHPUnit\Framework\TestCase;

class ValidEmailRuleTest extends TestCase
{
    public function test_passes_for_a_well_formed_email(): void
    {
        $result = (new ValidEmailRule())->evaluate($this->application('jane@example.com'));

        $this->assertTrue($result->passed());
    }

    public function test_fails_for_a_malformed_email(): void
    {
        $result = (new ValidEmailRule())->evaluate($this->application('not-an-email'));

        $this->assertTrue($result->failed());
        $this->assertNotNull($result->reason());
    }

    public function test_fails_for_an_empty_email(): void
    {
        $result = (new ValidEmailRule())->evaluate($this->application(''));

        $this->assertTrue($result->failed());
    }

    private function application(string $email): CandidacyApplicationData
    {
        return new CandidacyApplicationData(
            fullName: 'Jane Doe',
            email: $email,
            yearsOfExperience: 5,
            cvText: 'Some CV content.',
        );
    }
}
