<?php

namespace Tests\Unit\Candidacy\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\Rules\HasCvRule;
use PHPUnit\Framework\TestCase;

class HasCvRuleTest extends TestCase
{
    public function test_passes_when_cv_text_is_present(): void
    {
        $result = (new HasCvRule())->evaluate($this->application(cvText: 'Some CV content.'));

        $this->assertTrue($result->passed());
    }

    public function test_fails_when_cv_text_is_empty(): void
    {
        $result = (new HasCvRule())->evaluate($this->application(cvText: ''));

        $this->assertTrue($result->failed());
        $this->assertNotNull($result->reason());
    }

    public function test_fails_when_cv_text_is_only_whitespace(): void
    {
        $result = (new HasCvRule())->evaluate($this->application(cvText: "   \n\t"));

        $this->assertTrue($result->failed());
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
