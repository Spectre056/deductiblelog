<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\BusinessExpense;
use OCA\DeductibleLog\Db\BusinessExpenseMapper;

class BusinessExpenseService {

    public function __construct(private BusinessExpenseMapper $mapper) {}

    /** @return BusinessExpense[] */
    public function findAll(string $userId, int $taxYear): array {
        return $this->mapper->findAllByYear($userId, $taxYear);
    }

    public function yearTotal(string $userId, int $taxYear): string {
        return $this->mapper->sumByYear($userId, $taxYear);
    }

    public function create(string $userId, array $data): BusinessExpense {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $expense = new BusinessExpense();
        $expense->setUserId($userId);
        $expense->setFamilyMemberId(isset($data['family_member_id']) && $data['family_member_id'] !== '' ? (int) $data['family_member_id'] : null);
        $expense->setTaxYear((int) $data['tax_year']);
        $expense->setDate($data['date']);
        $expense->setDescription($data['description']);
        $expense->setCategory($data['category'] ?? null);
        $expense->setAmount($data['amount']);
        $expense->setNotes($data['notes'] ?? null);
        $expense->setCreatedAt($now);
        $expense->setUpdatedAt($now);

        return $this->mapper->insert($expense);
    }

    public function update(int $id, string $userId, array $data): BusinessExpense {
        $expense = $this->mapper->findById($id, $userId);

        if (isset($data['tax_year']))     { $expense->setTaxYear((int) $data['tax_year']); }
        if (isset($data['date']))         { $expense->setDate($data['date']); }
        if (isset($data['description']))  { $expense->setDescription($data['description']); }
        if (isset($data['amount']))       { $expense->setAmount($data['amount']); }
        if (array_key_exists('family_member_id', $data)) {
            $expense->setFamilyMemberId($data['family_member_id'] !== '' && $data['family_member_id'] !== null ? (int) $data['family_member_id'] : null);
        }
        if (array_key_exists('category', $data)) { $expense->setCategory($data['category']); }
        if (array_key_exists('notes', $data))    { $expense->setNotes($data['notes']); }

        $expense->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        return $this->mapper->update($expense);
    }

    public function delete(int $id, string $userId): void {
        $expense = $this->mapper->findById($id, $userId);
        $this->mapper->delete($expense);
    }
}
