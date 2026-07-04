<?php

namespace Tests\Unit\Candidacy;

use Candidacy\Domain\CvText;
use PHPUnit\Framework\TestCase;

class CvTextTest extends TestCase
{
    public function test_reports_empty_for_an_empty_string(): void
    {
        $cv = new CvText('');

        $this->assertTrue($cv->isEmpty());
    }

    public function test_reports_empty_for_whitespace_only(): void
    {
        $cv = new CvText("   \n\t");

        $this->assertTrue($cv->isEmpty());
    }

    public function test_reports_not_empty_when_content_is_present(): void
    {
        $cv = new CvText('Experienced PHP developer.');

        $this->assertFalse($cv->isEmpty());
        $this->assertSame('Experienced PHP developer.', $cv->value());
    }
}
