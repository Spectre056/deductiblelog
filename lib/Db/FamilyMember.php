<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getRelationship()
 * @method void setRelationship(string $relationship)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class FamilyMember extends Entity {
    protected string $userId = '';
    protected string $name = '';
    protected string $relationship = '';
    protected string $createdAt = '';

    public function jsonSerialize(): array {
        return [
            'id'           => $this->id,
            'user_id'      => $this->userId,
            'name'         => $this->name,
            'relationship' => $this->relationship,
            'created_at'   => $this->createdAt,
        ];
    }
}
