<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getCharityId()
 * @method void setCharityId(int $charityId)
 * @method int getTaxYear()
 * @method void setTaxYear(int $taxYear)
 * @method string getDate()
 * @method void setDate(string $date)
 * @method string getAmount()
 * @method void setAmount(string $amount)
 * @method string|null getPaymentMethod()
 * @method void setPaymentMethod(?string $paymentMethod)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class CashDonation extends Entity {
    protected string $userId = '';
    protected int $charityId = 0;
    protected int $taxYear = 0;
    protected string $date = '';
    protected string $amount = '0.00';
    protected ?string $paymentMethod = null;
    protected ?string $notes = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('charityId', 'integer');
        $this->addType('taxYear', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'             => $this->id,
            'user_id'        => $this->userId,
            'charity_id'     => $this->charityId,
            'tax_year'       => $this->taxYear,
            'date'           => $this->date,
            'amount'         => $this->amount,
            'payment_method' => $this->paymentMethod,
            'notes'          => $this->notes,
            'created_at'     => $this->createdAt,
            'updated_at'     => $this->updatedAt,
        ];
    }
}
