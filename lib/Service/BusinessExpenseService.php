<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\BusinessExpense;
use OCA\DeductibleLog\Db\BusinessExpenseMapper;
use OCA\DeductibleLog\Db\FamilyMemberMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class BusinessExpenseService {

    public function __construct(
        private BusinessExpenseMapper $mapper,
        private FamilyMemberMapper $familyMapper,
        private ReceiptMapper $receiptMapper,
    ) {}

    /** @return BusinessExpense[] */
    public function findAll(string $userId, int $taxYear): array {
        return $this->mapper->findAllByYear($userId, $taxYear);
    }

    public function yearTotal(string $userId, int $taxYear): string {
        return $this->mapper->sumByYear($userId, $taxYear);
    }

    public function create(string $userId, array $data): BusinessExpense {
        $now     = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $expense = new BusinessExpense();
        $expense->setUserId($userId);
        $expense->setCreatedAt($now);
        $this->apply($expense, $userId, $data);
        $expense->setUpdatedAt($now);
        return $this->mapper->insert($expense);
    }

    public function update(int $id, string $userId, array $data): BusinessExpense {
        $expense = $this->mapper->findById($id, $userId);
        $this->apply($expense, $userId, Merge::forUpdate($expense->jsonSerialize(), $data));
        $expense->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        return $this->mapper->update($expense);
    }

    public function delete(int $id, string $userId): void {
        $expense = $this->mapper->findById($id, $userId);
        $this->receiptMapper->deleteByEntity('business', $expense->getId(), $userId);
        $this->mapper->delete($expense);
    }

    private function apply(BusinessExpense $expense, string $userId, array $data): void {
        $v           = new Validator();
        $memberId    = $v->optionalInt($data['family_member_id'] ?? null, 'family_member_id');
        $date        = $v->date($data['date'] ?? null);
        $taxYear     = $v->taxYear($data['tax_year'] ?? null, $date);
        $description = $v->requiredString($data['description'] ?? null, 'description', 512);
        $amount      = $v->amount($data['amount'] ?? null);
        $category    = $v->optionalString($data['category'] ?? null, 'category', 64);
        $notes       = $v->optionalString($data['notes'] ?? null, 'notes', 10000);

        if ($memberId !== null) {
            try {
                $this->familyMapper->findById($memberId, $userId);
            } catch (DoesNotExistException) {
                $v->fail('family_member_id', 'Unknown family member');
            }
        }
        $v->throwIfInvalid();

        $expense->setFamilyMemberId($memberId);
        $expense->setTaxYear($taxYear);
        $expense->setDate($date);
        $expense->setDescription($description);
        $expense->setAmount($amount);
        $expense->setCategory($category);
        $expense->setNotes($notes);
    }
}
