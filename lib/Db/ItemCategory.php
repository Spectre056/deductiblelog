<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getMinValue()
 * @method void setMinValue(string $minValue)
 * @method string getMaxValue()
 * @method void setMaxValue(string $maxValue)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method string getSource()
 * @method void setSource(string $source)
 */
class ItemCategory extends Entity {
    protected string $category = '';
    protected string $name = '';
    protected string $minValue = '0.00';
    protected string $maxValue = '0.00';
    protected string $unit = 'each';
    protected string $source = 'salvation_army';

    public function jsonSerialize(): array {
        return [
            'id'        => $this->id,
            'category'  => $this->category,
            'name'      => $this->name,
            'min_value' => $this->minValue,
            'max_value' => $this->maxValue,
            'unit'      => $this->unit,
            'source'    => $this->source,
        ];
    }
}
