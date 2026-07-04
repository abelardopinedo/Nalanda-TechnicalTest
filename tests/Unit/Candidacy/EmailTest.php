<?php

namespace Tests\Unit\Candidacy;

use Candidacy\Domain\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function test_accepts_a_valid_email(): void
    {
        $email = new Email('candidate@example.com');

        $this->assertSame('candidate@example.com', $email->value());
        $this->assertSame('candidate@example.com', (string) $email);
    }

    public function test_rejects_an_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Email('not-an-email');
    }

    public function test_rejects_an_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Email('');
    }

    public function test_two_emails_with_the_same_value_are_equal(): void
    {
        $a = new Email('candidate@example.com');
        $b = new Email('candidate@example.com');

        $this->assertTrue($a->equals($b));
    }

    public function test_two_emails_with_different_values_are_not_equal(): void
    {
        $a = new Email('candidate@example.com');
        $b = new Email('other@example.com');

        $this->assertFalse($a->equals($b));
    }
}
