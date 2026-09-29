<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Money is the only place money arithmetic is allowed to happen, so its edges
 * are pinned here rather than inferred from the services that use it.
 */
class MoneyTest extends TestCase
{
    public function test_it_stores_amounts_as_integer_minor_units(): void
    {
        $this->assertSame(12345, Money::fromDecimal('123.45')->toCents());
        $this->assertSame('123.45', Money::fromDecimal('123.45')->toDecimal());
    }

    public function test_it_always_formats_to_two_decimals(): void
    {
        $this->assertSame('5.00', Money::fromDecimal(5)->toDecimal());
        $this->assertSame('5.10', Money::fromDecimal('5.1')->toDecimal());
        $this->assertSame('0.00', Money::zero()->toDecimal());
    }

    public function test_it_treats_null_and_empty_as_zero(): void
    {
        $this->assertTrue(Money::fromDecimal(null)->isZero());
        $this->assertTrue(Money::fromDecimal('')->isZero());
    }

    public function test_it_rejects_a_non_numeric_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('not-a-number');
    }

    public function test_addition_does_not_drift_the_way_floats_do(): void
    {
        // 0.1 + 0.2 !== 0.3 in binary floating point.
        $sum = Money::fromDecimal('0.1')->add(Money::fromDecimal('0.2'));

        $this->assertSame('0.30', $sum->toDecimal());
        $this->assertSame(30, $sum->toCents());
    }

    public function test_repeated_addition_stays_exact(): void
    {
        $total = Money::zero();

        for ($i = 0; $i < 1000; $i++) {
            $total = $total->add(Money::fromDecimal('0.01'));
        }

        $this->assertSame('10.00', $total->toDecimal());
    }

    public function test_subtraction_can_go_negative(): void
    {
        $result = Money::fromDecimal('10.00')->subtract(Money::fromDecimal('25.50'));

        $this->assertTrue($result->isNegative());
        $this->assertSame('-15.50', $result->toDecimal());
        $this->assertSame('15.50', $result->absolute()->toDecimal());
    }

    public function test_an_amount_is_quantised_to_cents_on_construction(): void
    {
        // Money carries two decimals, so a sub-cent input is rounded as it is
        // built — not carried at full precision and rounded later. 0.335
        // becomes 0.34, so 0.335 × 3 reads 1.02 rather than 1.005 -> 1.01.
        $this->assertSame(34, Money::fromDecimal('0.335')->toCents());
        $this->assertSame('1.02', Money::fromDecimal('0.335')->multiplyBy(3)->toDecimal());
    }

    public function test_multiplication_rounds_to_the_nearest_minor_unit(): void
    {
        $this->assertSame('250.00', Money::fromDecimal('12.50')->multiplyBy(20)->toDecimal());
        $this->assertSame('0.03', Money::fromDecimal('0.01')->multiplyBy('2.5')->toDecimal());
    }

    public function test_multiplication_handles_fractional_quantities(): void
    {
        $this->assertSame('18.75', Money::fromDecimal('12.50')->multiplyBy('1.5')->toDecimal());
    }

    public function test_percentage_is_taken_of_the_amount(): void
    {
        $this->assertSame('180.00', Money::fromDecimal('1800.00')->percentage(10)->toDecimal());
        $this->assertSame('0.00', Money::fromDecimal('1800.00')->percentage(0)->toDecimal());
        $this->assertSame('1800.00', Money::fromDecimal('1800.00')->percentage(100)->toDecimal());
    }

    public function test_it_survives_amounts_at_the_column_limit(): void
    {
        // decimal(15,2) tops out at 9,999,999,999,999.99
        $large = Money::fromDecimal('9999999999999.99');

        $this->assertSame('9999999999999.99', $large->toDecimal());
    }

    public function test_immutability(): void
    {
        $original = Money::fromDecimal('10.00');
        $original->add(Money::fromDecimal('5.00'));

        $this->assertSame('10.00', $original->toDecimal());
    }
}
