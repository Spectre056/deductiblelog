<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getKey()
 * @method void setKey(string $key)
 * @method string|null getValue()
 * @method void setValue(?string $value)
 */
class Setting extends BaseEntity {
    protected string $userId = '';
    protected string $key    = '';
    protected ?string $value = null;

    public function jsonSerialize(): array {
        return [
            'id'    => $this->id,
            'key'   => $this->key,
            'value' => $this->value,
        ];
    }
}
