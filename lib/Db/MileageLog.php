<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getFamilyMemberId()
 * @method void setFamilyMemberId(?int $familyMemberId)
 * @method int getTaxYear()
 * @method void setTaxYear(int $taxYear)
 * @method string getDate()
 * @method void setDate(string $date)
 * @method string getPurposeType()
 * @method void setPurposeType(string $purposeType)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getMiles()
 * @method void setMiles(string $miles)
 * @method string getRateCents()
 * @method void setRateCents(string $rateCents)
 * @method string getDeductionAmount()
 * @method void setDeductionAmount(string $deductionAmount)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class MileageLog extends Entity {
    protected string $userId = '';
    protected ?int $familyMemberId = null;
    protected int $taxYear = 0;
    protected string $date = '';
    protected string $purposeType = 'charitable';
    protected ?string $description = null;
    protected string $miles = '0.0';
    protected string $rateCents = '0.0';
    protected string $deductionAmount = '0.00';
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('familyMemberId', 'integer');
        $this->addType('taxYear', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'               => $this->id,
            'user_id'          => $this->userId,
            'family_member_id' => $this->familyMemberId,
            'tax_year'         => $this->taxYear,
            'date'             => $this->date,
            'purpose_type'     => $this->purposeType,
            'description'      => $this->description,
            'miles'            => $this->miles,
            'rate_cents'       => $this->rateCents,
            'deduction_amount' => $this->deductionAmount,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }
}
