<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

/**
 * @method int getDonationId()
 * @method void setDonationId(int $donationId)
 * @method int getItemCategoryId()
 * @method void setItemCategoryId(int $itemCategoryId)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method int getQuantity()
 * @method void setQuantity(int $quantity)
 * @method string getCondition()
 * @method void setCondition(string $condition)
 * @method string getUnitValue()
 * @method void setUnitValue(string $unitValue)
 * @method string getTotalValue()
 * @method void setTotalValue(string $totalValue)
 * @method string|null getDateAcquired()
 * @method void setDateAcquired(?string $dateAcquired)
 * @method string|null getHowAcquired()
 * @method void setHowAcquired(?string $howAcquired)
 * @method string|null getCostBasis()
 * @method void setCostBasis(?string $costBasis)
 * @method string|null getFmvMethod()
 * @method void setFmvMethod(?string $fmvMethod)
 * @method string|null getFmvSource()
 * @method void setFmvSource(?string $fmvSource)
 */
class ItemDonationLine extends BaseEntity {
    protected int $donationId = 0;
    protected int $itemCategoryId = 0;
    protected string $description = '';
    protected int $quantity = 1;
    protected string $condition = '';
    protected string $unitValue = '0.00';
    protected string $totalValue = '0.00';
    protected ?string $fmvSource = null;
    protected ?string $dateAcquired = null;
    protected ?string $howAcquired = null;
    protected ?string $costBasis = null;
    protected ?string $fmvMethod = null;

    public function __construct() {
        $this->addType('donationId', 'integer');
        $this->addType('itemCategoryId', 'integer');
        $this->addType('quantity', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'               => $this->id,
            'donation_id'      => $this->donationId,
            'item_category_id' => $this->itemCategoryId,
            'description'      => $this->description,
            'quantity'         => $this->quantity,
            'condition'        => $this->condition,
            'unit_value'       => $this->unitValue,
            'total_value'      => $this->totalValue,
            'fmv_source'       => $this->fmvSource,
            'date_acquired'    => $this->dateAcquired,
            'how_acquired'     => $this->howAcquired,
            'cost_basis'       => $this->costBasis,
            'fmv_method'       => $this->fmvMethod,
        ];
    }
}
