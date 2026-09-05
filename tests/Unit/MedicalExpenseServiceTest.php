<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\FamilyMember;
use OCA\DeductibleLog\Db\FamilyMemberMapper;
use OCA\DeductibleLog\Db\MedicalExpenseMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\MedicalExpenseService;
use PHPUnit\Framework\TestCase;

class MedicalExpenseServiceTest extends TestCase {

    private MedicalExpenseService $service;

    protected function setUp(): void {
        $mapper = $this->createMock(MedicalExpenseMapper::class);
        $mapper->method('insert')->willReturnArgument(0);
        $family = $this->createMock(FamilyMemberMapper::class);
        $family->method('findById')->willReturn(new FamilyMember());
        $this->service = new MedicalExpenseService($mapper, $family, $this->createMock(ReceiptMapper::class));
    }

    public function testReimbursementReducesTheDeductibleAmount(): void {
        $e = $this->service->create('michael', ['date' => '2026-05-15', 'amount' => '120', 'reimbursed_amount' => '45.5', 'family_member_id' => 2]);
        $this->assertSame('120.00', $e->getAmount());
        $this->assertSame('45.50', $e->getReimbursedAmount());
        $this->assertSame('74.50', $e->getDeductibleAmount());
        $this->assertSame('74.50', $e->jsonSerialize()['deductible_amount']);
    }

    public function testMissingReimbursementIsZero(): void {
        $e = $this->service->create('michael', ['date' => '2026-05-15', 'amount' => '15.00']);
        $this->assertSame('0.00', $e->getReimbursedAmount());
        $this->assertSame('15.00', $e->getDeductibleAmount());
    }

    public function testReimbursementAboveAmountIsRejected(): void {
        try {
            $this->service->create('michael', ['date' => '2026-05-15', 'amount' => '10', 'reimbursed_amount' => '10.01']);
            $this->fail('expected 422');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reimbursed_amount', $e->getDetails()['errors']);
        }
    }
}
