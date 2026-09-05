<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\FamilyMemberMapper;
use OCA\DeductibleLog\Db\MileageLog;
use OCA\DeductibleLog\Db\MileageLogMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Db\TaxRate;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\MileageService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

class MileageServiceTest extends TestCase {

    private MileageLogMapper $mapper;
    private TaxRateMapper $rates;
    private MileageService $service;

    protected function setUp(): void {
        $this->mapper = $this->createMock(MileageLogMapper::class);
        $this->mapper->method('insert')->willReturnArgument(0);
        $this->mapper->method('update')->willReturnArgument(0);
        $this->rates  = $this->createMock(TaxRateMapper::class);
        $this->rates->method('findByYear')->willReturnCallback(function (int $year) {
            if ($year !== 2026) {
                throw new DoesNotExistException('no rate');
            }
            $r = new TaxRate();
            $r->setTaxYear(2026);
            $r->setMileageCharitableCents('14.0');
            $r->setMileageMedicalCents('20.5');
            $r->setMileageBusinessCents('72.5');
            return $r;
        });
        $this->service = new MileageService(
            $this->mapper,
            $this->rates,
            $this->createMock(FamilyMemberMapper::class),
            $this->createMock(ReceiptMapper::class),
        );
    }

    public function testCreateResolvesRateFromTableAndComputesInCents(): void {
        $log = $this->service->create('michael', [
            'date' => '2026-03-01', 'purpose_type' => 'medical', 'miles' => '12.3',
        ]);
        $this->assertSame(2026, $log->getTaxYear());
        $this->assertSame('20.5', $log->getRateCents());
        $this->assertSame('2.52', $log->getDeductionAmount());
        $this->assertSame('12.3', $log->getMiles());
        $this->assertSame('michael', $log->getUserId());
    }

    public function testCreateForYearWithoutRateIsRejectedNotZero(): void {
        $this->mapper->expects($this->never())->method('insert');
        try {
            $this->service->create('michael', ['date' => '2027-01-02', 'purpose_type' => 'charitable', 'miles' => '5']);
            $this->fail('expected 422');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rate_cents', $e->getDetails()['errors']);
        }
    }

    public function testZeroClientRateDoesNotOverrideTable(): void {
        $log = $this->service->create('michael', [
            'date' => '2026-03-01', 'purpose_type' => 'business', 'miles' => '10', 'rate_cents' => '0.0',
        ]);
        $this->assertSame('72.5', $log->getRateCents());
        $this->assertSame('7.25', $log->getDeductionAmount());
    }

    public function testManualRateAllowsAYearTheTableDoesNotKnow(): void {
        $log = $this->service->create('michael', [
            'date' => '2027-01-02', 'purpose_type' => 'charitable', 'miles' => '10', 'rate_cents' => '14',
        ]);
        $this->assertSame('14.0', $log->getRateCents());
        $this->assertSame('1.40', $log->getDeductionAmount());
    }

    public function testUpdateChangingYearReResolvesRateAndTaxYear(): void {
        $stored = new MileageLog();
        $stored->setId(9);
        $stored->setUserId('michael');
        $stored->setTaxYear(2025);
        $stored->setDate('2025-06-01');
        $stored->setPurposeType('business');
        $stored->setMiles('10.0');
        $stored->setRateCents('70.0');
        $stored->setDeductionAmount('7.00');
        $stored->setCreatedAt('x');
        $stored->setUpdatedAt('x');
        $stored->resetUpdatedFields();
        $this->mapper->method('findById')->willReturn($stored);

        $log = $this->service->update(9, 'michael', ['date' => '2026-06-01']);
        $this->assertSame(2026, $log->getTaxYear());
        $this->assertSame('72.5', $log->getRateCents());
        $this->assertSame('7.25', $log->getDeductionAmount());
    }

    public function testUpdateWithSameYearKeepsStoredRate(): void {
        $stored = new MileageLog();
        $stored->setId(9);
        $stored->setUserId('michael');
        $stored->setTaxYear(2025);
        $stored->setDate('2025-06-01');
        $stored->setPurposeType('business');
        $stored->setMiles('10.0');
        $stored->setRateCents('70.0');
        $stored->setDeductionAmount('7.00');
        $stored->setCreatedAt('x');
        $stored->setUpdatedAt('x');
        $stored->resetUpdatedFields();
        $this->mapper->method('findById')->willReturn($stored);

        $log = $this->service->update(9, 'michael', ['miles' => '20']);
        $this->assertSame('70.0', $log->getRateCents());
        $this->assertSame('14.00', $log->getDeductionAmount());
    }

    public function testUsDateFormatIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->create('michael', ['date' => '12/31/2025', 'purpose_type' => 'charitable', 'miles' => '5']);
    }

    public function testMismatchedExplicitTaxYearIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->create('michael', ['date' => '2026-01-01', 'tax_year' => 2025, 'purpose_type' => 'charitable', 'miles' => '5']);
    }
}
