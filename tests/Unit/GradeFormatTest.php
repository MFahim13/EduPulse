<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GradeFormatTest extends TestCase
{
    public function test_format_grade(): void
    {
        $this->assertSame('18 / 20 (90%)', format_grade(18, 20));
        $this->assertSame('0 / 50 (0%)', format_grade(0, 50));
        $this->assertSame('7 / 9 (78%)', format_grade(7, 9));
        $this->assertSame('1 / 8 (13%)', format_grade(1, 8));
        $this->assertSame('100 / 100 (100%)', format_grade(100, 100));
    }

    public function test_is_late_boundaries(): void
    {
        $due = '2026-03-10 12:00:00';
        $this->assertTrue(is_late('2026-03-10 12:01:00', $due));
        $this->assertFalse(is_late('2026-03-10 11:59:00', $due));
        $this->assertFalse(is_late($due, $due));
    }
}
