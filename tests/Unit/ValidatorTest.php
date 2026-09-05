<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase {

    public function testDateAcceptsIsoAndRejectsEverythingElse(): void {
        $v = new Validator();
        $this->assertSame('2025-12-31', $v->date('2025-12-31'));
        $this->assertNull($v->date('12/31/2025'));
        $this->assertNull($v->date('2025-13-01'));
        $this->assertNull($v->date('2025-02-30'));
        $this->assertNull($v->date('2025-12-31T23:00'));
        $this->assertNull($v->date(null));
        $this->assertNull($v->date(20251231));
        $this->assertTrue($v->hasErrors());
    }

    public function testTaxYearIsDerivedFromDateAndMustAgree(): void {
        $v = new Validator();
        $this->assertSame(2025, $v->taxYear(null, '2025-12-31'));
        $this->assertSame(2025, $v->taxYear('', '2025-12-31'));
        $this->assertSame(2025, $v->taxYear('2025', '2025-12-31'));
        $this->assertSame(2025, $v->taxYear(2025, '2025-12-31'));
        $this->assertFalse($v->hasErrors());
        $this->assertNull($v->taxYear(2026, '2025-12-31'));
        $this->assertTrue($v->hasErrors());
    }

    public function testAmountNormalizesAndRejectsWhatTheDbWouldRound(): void {
        $v = new Validator();
        $this->assertSame('12.50', $v->amount('12.5'));
        $this->assertSame('12.00', $v->amount(12));
        $this->assertSame('1686.50', $v->amount('1686.50'));
        $this->assertFalse($v->hasErrors());
        foreach (['12.345', '-5', '1e3', 'abc', '', null, '$5', '0', '0.00'] as $bad) {
            $this->assertNull((new Validator())->amount($bad), var_export($bad, true));
        }
        $this->assertSame('0.00', (new Validator())->amount('0', 'x', true));
    }

    public function testOneDecimalAndInts(): void {
        $v = new Validator();
        $this->assertSame('12.3', $v->oneDecimal('12.3', 'miles'));
        $this->assertSame('12.0', $v->oneDecimal(12, 'miles'));
        $this->assertNull($v->oneDecimal('0', 'miles'));
        $this->assertNull($v->oneDecimal('-1', 'miles'));
        $this->assertSame(7, $v->positiveInt('7', 'id'));
        $this->assertSame(7, $v->positiveInt(7, 'id'));
        $this->assertSame(7, $v->positiveInt(7.0, 'id'));
        $this->assertNull($v->positiveInt('7.5', 'id'));
        $this->assertNull($v->positiveInt(0, 'id'));
        $this->assertNull($v->optionalInt('', 'id'));
        $this->assertNull($v->optionalInt(null, 'id'));
    }

    public function testYearMonthAcceptsVarious(): void {
        $v = new Validator();
        $this->assertSame('Various', $v->yearMonth('various', 'd'));
        $this->assertSame('Various', $v->yearMonth('VARIOUS', 'd'));
        $this->assertSame('2021-06', $v->yearMonth('2021-06', 'd'));
        $this->assertNull($v->yearMonth('', 'd'));
        $this->assertFalse($v->hasErrors());
        $this->assertNull($v->yearMonth('06/2021', 'd'));
        $this->assertTrue($v->hasErrors());
    }

    public function testThrowCarriesAllFieldErrors(): void {
        $v = new Validator();
        $v->date('nope');
        $v->amount('nope');
        try {
            $v->throwIfInvalid();
            $this->fail('expected exception');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatus());
            $this->assertSame(['date', 'amount'], array_keys($e->getDetails()['errors']));
        }
    }
}
