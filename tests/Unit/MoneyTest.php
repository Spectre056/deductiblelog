<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Service\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase {

    public function testCentsRoundTrip(): void {
        $this->assertSame(12345, Money::toCents('123.45'));
        $this->assertSame(1200, Money::toCents('12'));
        $this->assertSame(1250, Money::toCents('12.5'));
        $this->assertSame(0, Money::toCents(null));
        $this->assertSame(-199, Money::toCents('-1.99'));
        $this->assertSame('123.45', Money::fromCents(12345));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame('-1.99', Money::fromCents(-199));
    }

    public function testSumIsExactWhereFloatIsNot(): void {
        $this->assertSame('0.30', Money::sum('0.10', '0.20'));
        $this->assertSame('1686.50', Money::sum('1000.00', '686.50'));
        $this->assertSame('0.00', Money::sum());
        $args = array_fill(0, 1000, '0.01');
        $this->assertSame('10.00', Money::sum(...$args));
    }

    public function testMileageDeductionRoundsHalfUpAtTheCent(): void {
        $this->assertSame('2.52', Money::mileageDeduction('12.3', '20.5'));  // 252.15¢
        $this->assertSame('72.50', Money::mileageDeduction('100.0', '72.5'));
        $this->assertSame('0.32', Money::mileageDeduction('1.5', '21.0'));   // 31.5¢ → half up
        $this->assertSame('0.01', Money::mileageDeduction('0.1', '14.0'));   // 1.4¢
        $this->assertSame('0.00', Money::mileageDeduction('0.0', '14.0'));
    }

    public function testLineTotal(): void {
        $this->assertSame('7.68', Money::lineTotal('2.56', 3));
        $this->assertSame('0.00', Money::lineTotal('0.00', 5));
    }
}
