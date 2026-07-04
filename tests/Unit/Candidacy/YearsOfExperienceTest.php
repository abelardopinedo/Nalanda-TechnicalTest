<?php

namespace Tests\Unit\Candidacy;

use Candidacy\Domain\YearsOfExperience;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class YearsOfExperienceTest extends TestCase
{
    public function test_accepts_zero(): void
    {
        $years = new YearsOfExperience(0);

        $this->assertSame(0, $years->value());
    }

    public function test_rejects_a_negative_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new YearsOfExperience(-1);
    }

    public function test_is_at_least_returns_true_when_equal(): void
    {
        $years = new YearsOfExperience(5);

        $this->assertTrue($years->isAtLeast(5));
    }

    public function test_is_at_least_returns_true_when_greater(): void
    {
        $years = new YearsOfExperience(10);

        $this->assertTrue($years->isAtLeast(5));
    }

    public function test_is_at_least_returns_false_when_lower(): void
    {
        $years = new YearsOfExperience(3);

        $this->assertFalse($years->isAtLeast(5));
    }
}
