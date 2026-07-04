<?php

namespace Tests\Unit\Candidacy\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\Rules\DisposableEmailRule;
use PHPUnit\Framework\TestCase;

class DisposableEmailRuleTest extends TestCase
{
    public function test_passes_for_a_non_disposable_domain(): void
    {
        $result = (new DisposableEmailRule())->evaluate($this->application('jane@example.com'));

        $this->assertTrue($result->passed());
    }

    public function test_fails_for_a_known_disposable_domain(): void
    {
        $result = (new DisposableEmailRule())->evaluate($this->application('jane@mailinator.com'));

        $this->assertTrue($result->failed());
        $this->assertNotNull($result->reason());
    }

    public function test_domain_matching_is_case_insensitive(): void
    {
        $result = (new DisposableEmailRule())->evaluate($this->application('jane@MAILINATOR.COM'));

        $this->assertTrue($result->failed());
    }

    public function test_disposable_domains_are_configurable(): void
    {
        $rule = new DisposableEmailRule(disposableDomains: ['example.com']);

        $this->assertTrue($rule->evaluate($this->application('jane@example.com'))->failed());
        $this->assertTrue($rule->evaluate($this->application('jane@mailinator.com'))->passed());
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
