<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

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
 */
class ItemDonationLine extends Entity {
    protected int $donationId = 0;
    protected int $itemCategoryId = 0;
    protected string $description = '';
    protected int $quantity = 1;
    protected string $condition = '';
    protected string $unitValue = '0.00';
    protected string $totalValue = '0.00';

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
        ];
    }
}
