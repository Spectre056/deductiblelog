<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getCharityId()
 * @method void setCharityId(int $charityId)
 * @method int getTaxYear()
 * @method void setTaxYear(int $taxYear)
 * @method string getDate()
 * @method void setDate(string $date)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getTotalValue()
 * @method void setTotalValue(string $totalValue)
 * @method int getAcknowledged()
 * @method void setAcknowledged(int $acknowledged)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class ItemDonation extends BaseEntity {
    protected string $userId = '';
    protected int $charityId = 0;
    protected int $taxYear = 0;
    protected string $date = '';
    protected ?string $notes = null;
    protected string $totalValue = '0.00';
    protected int $acknowledged = 0;
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('charityId', 'integer');
        $this->addType('taxYear', 'integer');
        $this->addType('acknowledged', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'          => $this->id,
            'user_id'     => $this->userId,
            'charity_id'  => $this->charityId,
            'tax_year'    => $this->taxYear,
            'date'        => $this->date,
            'notes'       => $this->notes,
            'total_value' => $this->totalValue,
            'acknowledged'   => $this->acknowledged === 1,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
