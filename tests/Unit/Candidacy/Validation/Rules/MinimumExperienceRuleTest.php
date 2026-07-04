<?php

namespace Tests\Unit\Candidacy\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\Rules\MinimumExperienceRule;
use PHPUnit\Framework\TestCase;

class MinimumExperienceRuleTest extends TestCase
{
    public function test_passes_when_experience_equals_the_default_minimum(): void
    {
        $result = (new MinimumExperienceRule())->evaluate($this->application(2));

        $this->assertTrue($result->passed());
    }

    public function test_passes_when_experience_exceeds_the_default_minimum(): void
    {
        $result = (new MinimumExperienceRule())->evaluate($this->application(10));

        $this->assertTrue($result->passed());
    }

    public function test_fails_when_experience_is_below_the_default_minimum(): void
    {
        $result = (new MinimumExperienceRule())->evaluate($this->application(1));

        $this->assertTrue($result->failed());
        $this->assertNotNull($result->reason());
    }

    public function test_minimum_years_is_configurable(): void
    {
        $rule = new MinimumExperienceRule(minimumYears: 5);

        $this->assertTrue($rule->evaluate($this->application(4))->failed());
        $this->assertTrue($rule->evaluate($this->application(5))->passed());
    }

    private function application(int $yearsOfExperience): CandidacyApplicationData
    {
        return new CandidacyApplicationData(
            fullName: 'Jane Doe',
            email: 'jane@example.com',
            yearsOfExperience: $yearsOfExperience,
            cvText: 'Some CV content.',
        );
    }
}
