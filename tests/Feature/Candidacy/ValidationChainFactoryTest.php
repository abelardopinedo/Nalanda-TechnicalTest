<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Validation\ValidationChainFactory;
use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationChain;
use Candidacy\Application\Validation\ValidationRule;
use Tests\TestCase;

class ValidationChainFactoryTest extends TestCase
{
    public function test_it_builds_a_chain_from_the_configured_rule_classes(): void
    {
        $chain = $this->app->make(ValidationChainFactory::class)->make();

        $this->assertInstanceOf(ValidationChain::class, $chain);

        $report = $chain->run(new CandidacyApplicationData(
            fullName: 'Jane Doe',
            email: 'not-an-email',
            yearsOfExperience: 0,
            cvText: '',
        ));

        $this->assertFalse($report->isValid());
        $this->assertNotEmpty($report->reasons());
    }

    public function test_a_new_rule_is_picked_up_by_only_adding_it_to_config(): void
    {
        config()->set('candidacy_validation.rules', array_merge(
            config('candidacy_validation.rules'),
            [AlwaysFailsTestRule::class],
        ));

        $chain = $this->app->make(ValidationChainFactory::class)->make();

        $report = $chain->run(new CandidacyApplicationData(
            fullName: 'Jane Doe',
            email: 'jane@example.com',
            yearsOfExperience: 10,
            cvText: str_repeat('a', 100),
        ));

        $this->assertFalse($report->isValid());
        $this->assertSame(['Injected failure for extensibility test.'], $report->reasons());
    }
}

class AlwaysFailsTestRule implements ValidationRule
{
    public function evaluate(CandidacyApplicationData $application): RuleResult
    {
        return RuleResult::fail(self::class, 'Injected failure for extensibility test.');
    }
}
