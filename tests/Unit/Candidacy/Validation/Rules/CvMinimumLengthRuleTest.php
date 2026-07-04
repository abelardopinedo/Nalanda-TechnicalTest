<?php

namespace Tests\Unit\Candidacy\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\Rules\CvMinimumLengthRule;
use PHPUnit\Framework\TestCase;

class CvMinimumLengthRuleTest extends TestCase
{
    public function test_passes_when_cv_meets_the_default_minimum_length(): void
    {
        $result = (new CvMinimumLengthRule())->evaluate($this->application(str_repeat('a', 50)));

        $this->assertTrue($result->passed());
    }

    public function test_fails_when_cv_is_shorter_than_the_default_minimum_length(): void
    {
        $result = (new CvMinimumLengthRule())->evaluate($this->application('too short'));

        $this->assertTrue($result->failed());
        $this->assertNotNull($result->reason());
    }

    public function test_minimum_length_is_configurable(): void
    {
        $rule = new CvMinimumLengthRule(minimumLength: 5);

        $this->assertTrue($rule->evaluate($this->application('abcd'))->failed());
        $this->assertTrue($rule->evaluate($this->application('abcde'))->passed());
    }

    private function application(string $cvText): CandidacyApplicationData
    {
        return new CandidacyApplicationData(
            fullName: 'Jane Doe',
            email: 'jane@example.com',
            yearsOfExperience: 5,
            cvText: $cvText,
        );
    }
}
