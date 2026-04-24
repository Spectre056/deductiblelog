<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getTaxYear()
 * @method void setTaxYear(int $taxYear)
 * @method string getMileageCharitableCents()
 * @method void setMileageCharitableCents(string $mileageCharitableCents)
 * @method string getMileageMedicalCents()
 * @method void setMileageMedicalCents(string $mileageMedicalCents)
 * @method string getMileageBusinessCents()
 * @method void setMileageBusinessCents(string $mileageBusinessCents)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 * @method string getSource()
 * @method void setSource(string $source)
 */
class TaxRate extends Entity {
    protected int $taxYear = 0;
    protected string $mileageCharitableCents = '14.0';
    protected string $mileageMedicalCents = '21.0';
    protected string $mileageBusinessCents = '67.0';
    protected string $updatedAt = '';
    protected string $source = '';

    public function __construct() {
        $this->addType('taxYear', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'tax_year'   => $this->taxYear,
            'charitable' => $this->mileageCharitableCents,
            'medical'    => $this->mileageMedicalCents,
            'business'   => $this->mileageBusinessCents,
        ];
    }
}
