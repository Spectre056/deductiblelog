<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getEin()
 * @method void setEin(?string $ein)
 * @method string|null getAddress()
 * @method void setAddress(?string $address)
 * @method string|null getCity()
 * @method void setCity(?string $city)
 * @method string|null getState()
 * @method void setState(?string $state)
 * @method string|null getZip()
 * @method void setZip(?string $zip)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class Charity extends Entity {
    protected string $userId = '';
    protected string $name = '';
    protected ?string $ein = null;
    protected ?string $address = null;
    protected ?string $city = null;
    protected ?string $state = null;
    protected ?string $zip = null;
    protected ?string $notes = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function jsonSerialize(): array {
        return [
            'id'         => $this->id,
            'user_id'    => $this->userId,
            'name'       => $this->name,
            'ein'        => $this->ein,
            'address'    => $this->address,
            'city'       => $this->city,
            'state'      => $this->state,
            'zip'        => $this->zip,
            'notes'      => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
