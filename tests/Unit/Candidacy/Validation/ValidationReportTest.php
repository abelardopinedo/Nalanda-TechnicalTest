<?php

namespace Tests\Unit\Candidacy\Validation;

use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationReport;
use PHPUnit\Framework\TestCase;

class ValidationReportTest extends TestCase
{
    public function test_is_valid_when_there_are_no_failures(): void
    {
        $report = new ValidationReport([RuleResult::pass('RuleA')], []);

        $this->assertTrue($report->isValid());
        $this->assertSame([], $report->failed());
        $this->assertSame([], $report->reasons());
    }

    public function test_is_not_valid_when_there_is_at_least_one_failure(): void
    {
        $failure = RuleResult::fail('RuleA', 'Reason A');

        $report = new ValidationReport([], [$failure]);

        $this->assertFalse($report->isValid());
        $this->assertSame([$failure], $report->failed());
    }

    public function test_reasons_collects_the_reason_of_every_failure(): void
    {
        $failureA = RuleResult::fail('RuleA', 'Reason A');
        $failureB = RuleResult::fail('RuleB', 'Reason B');

        $report = new ValidationReport([], [$failureA, $failureB]);

        $this->assertSame(['Reason A', 'Reason B'], $report->reasons());
    }
}
