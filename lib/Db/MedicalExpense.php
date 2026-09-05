<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCA\DeductibleLog\Service\Money;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getFamilyMemberId()
 * @method void setFamilyMemberId(?int $familyMemberId)
 * @method int getTaxYear()
 * @method void setTaxYear(int $taxYear)
 * @method string getDate()
 * @method void setDate(string $date)
 * @method string|null getProvider()
 * @method void setProvider(?string $provider)
 * @method string|null getCategory()
 * @method void setCategory(?string $category)
 * @method string getAmount()
 * @method void setAmount(string $amount)
 * @method string getReimbursedAmount()
 * @method void setReimbursedAmount(string $reimbursedAmount)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class MedicalExpense extends BaseEntity {
    protected string $userId = '';
    protected ?int $familyMemberId = null;
    protected int $taxYear = 0;
    protected string $date = '';
    protected ?string $provider = null;
    protected ?string $category = null;
    protected string $amount = '0.00';
    protected string $reimbursedAmount = '0.00';
    protected ?string $notes = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('familyMemberId', 'integer');
        $this->addType('taxYear', 'integer');
    }

    /** What actually counts toward Schedule A: the out-of-pocket part. */
    public function getDeductibleAmount(): string {
        return Money::fromCents(Money::toCents($this->amount) - Money::toCents($this->reimbursedAmount));
    }

    public function jsonSerialize(): array {
        return [
            'id'               => $this->id,
            'user_id'          => $this->userId,
            'family_member_id' => $this->familyMemberId,
            'tax_year'         => $this->taxYear,
            'date'             => $this->date,
            'provider'         => $this->provider,
            'category'         => $this->category,
            'amount'            => $this->amount,
            'reimbursed_amount' => $this->reimbursedAmount,
            'deductible_amount' => $this->getDeductibleAmount(),
            'notes'            => $this->notes,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }
}
