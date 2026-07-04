<?php

namespace Tests\Unit\Candidacy\Validation;

use Candidacy\Application\Validation\RuleResult;
use PHPUnit\Framework\TestCase;

class RuleResultTest extends TestCase
{
    public function test_pass_reports_passed_with_no_reason(): void
    {
        $result = RuleResult::pass('SomeRule');

        $this->assertTrue($result->passed());
        $this->assertFalse($result->failed());
        $this->assertSame('SomeRule', $result->rule());
        $this->assertNull($result->reason());
    }

    public function test_fail_reports_failed_with_a_reason(): void
    {
        $result = RuleResult::fail('SomeRule', 'It went wrong.');

        $this->assertFalse($result->passed());
        $this->assertTrue($result->failed());
        $this->assertSame('SomeRule', $result->rule());
        $this->assertSame('It went wrong.', $result->reason());
    }
}
