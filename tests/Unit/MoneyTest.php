<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_converts_decimal_strings_to_cents_exactly(): void
    {
        $this->assertSame(125050, Money::toCents('1250.50'));
        $this->assertSame(125050, Money::toCents('1,250.5'));
        $this->assertSame(-310, Money::toCents('-3.1'));
        $this->assertSame(1200, Money::toCents(12));
        $this->assertSame(0, Money::toCents(null));
        $this->assertSame(10, Money::toCents('0.100'));
    }

    public function test_rejects_invalid_or_over_precise_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toCents('12.345');
    }

    public function test_rejects_non_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toCents('12abc');
    }

    public function test_sum_avoids_floating_point_errors(): void
    {
        // 0.1 + 0.2 in floating point is 0.30000000000000004
        $this->assertSame('0.30', Money::sum(['0.10', '0.20']));
        $this->assertSame('1000000.03', Money::sum(array_merge(array_fill(0, 3, '0.01'), ['1000000.00'])));
    }

    public function test_multiply_rounds_half_up_to_the_cent(): void
    {
        $this->assertSame('30.00', Money::multiply('3', '10.00'));
        $this->assertSame('3.71', Money::multiply('1.5', '2.47')); // 3.705 rounds half-up
    }

    public function test_format_and_percent(): void
    {
        $this->assertSame('USD 1,234,567.89', Money::format('1234567.89', 'USD'));
        $this->assertSame('-5.00', Money::format(-500));
        $this->assertSame(25.0, Money::percent('25.00', '100.00'));
        $this->assertSame(0.0, Money::percent('25.00', '0'));
    }
}
