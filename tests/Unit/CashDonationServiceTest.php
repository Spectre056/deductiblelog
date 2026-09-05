<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\CashDonation;
use OCA\DeductibleLog\Db\CashDonationMapper;
use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Db\CharityMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\CashDonationService;
use PHPUnit\Framework\TestCase;

class CashDonationServiceTest extends TestCase {

    private CashDonationMapper $mapper;
    private CashDonationService $service;

    protected function setUp(): void {
        $this->mapper = $this->createMock(CashDonationMapper::class);
        $this->mapper->method('insert')->willReturnArgument(0);
        $this->mapper->method('update')->willReturnArgument(0);
        $charities = $this->createMock(CharityMapper::class);
        $charities->method('findById')->willReturn(new Charity());
        $this->service = new CashDonationService($this->mapper, $charities, $this->createMock(ReceiptMapper::class));
    }

    public function testCreateNormalizesAndDefaultsAcknowledgedToFalse(): void {
        $d = $this->service->create('michael', ['charity_id' => '5', 'date' => '2026-02-14', 'amount' => 1000, 'payment_method' => '', 'notes' => null]);
        $this->assertSame(5, $d->getCharityId());
        $this->assertSame(2026, $d->getTaxYear());
        $this->assertSame('1000.00', $d->getAmount());
        $this->assertNull($d->getPaymentMethod());
        $this->assertSame(0, $d->getAcknowledged());
        $this->assertFalse($d->jsonSerialize()['acknowledged']);
    }

    public function testAcknowledgedAcceptsBooleanShapes(): void {
        foreach ([true, 1, '1', 'true'] as $yes) {
            $d = $this->service->create('michael', ['charity_id' => 5, 'date' => '2026-02-14', 'amount' => '10', 'acknowledged' => $yes]);
            $this->assertSame(1, $d->getAcknowledged(), var_export($yes, true));
        }
    }

    public function testUpdateChangingDateReDerivesTaxYear(): void {
        $stored = new CashDonation();
        $stored->setId(3);
        $stored->setUserId('michael');
        $stored->setCharityId(5);
        $stored->setTaxYear(2025);
        $stored->setDate('2025-12-31');
        $stored->setAmount('50.00');
        $stored->setCreatedAt('x');
        $stored->setUpdatedAt('x');
        $stored->resetUpdatedFields();
        $this->mapper->method('findById')->willReturn($stored);

        $d = $this->service->update(3, 'michael', ['date' => '2026-01-01']);
        $this->assertSame(2026, $d->getTaxYear());
        $this->assertSame('50.00', $d->getAmount());
    }

    public function testUsFormatDateIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->create('michael', ['charity_id' => 5, 'date' => '12/31/2025', 'amount' => '10']);
    }
}
