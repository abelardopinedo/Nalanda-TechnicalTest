<?php

namespace Tests\Unit\Candidacy\Validation;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationChain;
use Candidacy\Application\Validation\ValidationRule;
use PHPUnit\Framework\TestCase;

class ValidationChainTest extends TestCase
{
    public function test_report_is_valid_when_every_rule_passes(): void
    {
        $chain = new ValidationChain([
            $this->alwaysPasses('RuleA'),
            $this->alwaysPasses('RuleB'),
        ]);

        $report = $chain->run($this->anyApplication());

        $this->assertTrue($report->isValid());
        $this->assertCount(2, $report->passed());
        $this->assertCount(0, $report->failed());
    }

    public function test_it_accumulates_every_failure_instead_of_stopping_at_the_first(): void
    {
        $chain = new ValidationChain([
            $this->alwaysFails('RuleA', 'Reason A'),
            $this->alwaysPasses('RuleB'),
            $this->alwaysFails('RuleC', 'Reason C'),
        ]);

        $report = $chain->run($this->anyApplication());

        $this->assertFalse($report->isValid());
        $this->assertCount(1, $report->passed());
        $this->assertCount(2, $report->failed());
        $this->assertSame(['Reason A', 'Reason C'], $report->reasons());
    }

    public function test_every_rule_is_evaluated_exactly_once_even_after_a_failure(): void
    {
        $recorder = new \ArrayObject();

        $recordCall = static function (string $name) use ($recorder): ValidationRule {
            return new class($name, $recorder) implements ValidationRule
            {
                public function __construct(private readonly string $name, private readonly \ArrayObject $recorder)
                {
                }

                public function evaluate(CandidacyApplicationData $application): RuleResult
                {
                    $this->recorder[] = $this->name;

                    return RuleResult::fail($this->name, "{$this->name} failed");
                }
            };
        };

        $chain = new ValidationChain([
            $recordCall('RuleA'),
            $recordCall('RuleB'),
            $recordCall('RuleC'),
        ]);

        $report = $chain->run($this->anyApplication());

        $this->assertCount(3, $report->failed());
        $this->assertSame(['RuleA failed', 'RuleB failed', 'RuleC failed'], $report->reasons());
        $this->assertSame(['RuleA', 'RuleB', 'RuleC'], (array) $recorder);
    }

    private function alwaysPasses(string $name): ValidationRule
    {
        return new class($name) implements ValidationRule
        {
            public function __construct(private readonly string $name)
            {
            }

            public function evaluate(CandidacyApplicationData $application): RuleResult
            {
                return RuleResult::pass($this->name);
            }
        };
    }

    private function alwaysFails(string $name, string $reason): ValidationRule
    {
        return new class($name, $reason) implements ValidationRule
        {
            public function __construct(private readonly string $name, private readonly string $reason)
            {
            }

            public function evaluate(CandidacyApplicationData $application): RuleResult
            {
                return RuleResult::fail($this->name, $this->reason);
            }
        };
    }

    private function anyApplication(): CandidacyApplicationData
    {
        return new CandidacyApplicationData(
            fullName: 'Jane Doe',
            email: 'jane@example.com',
            yearsOfExperience: 5,
            cvText: 'A sufficiently long curriculum vitae body for testing purposes.',
        );
    }
}
