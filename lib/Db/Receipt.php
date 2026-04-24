<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getEntityType()
 * @method void setEntityType(string $entityType)
 * @method int getEntityId()
 * @method void setEntityId(int $entityId)
 * @method string getNcFilePath()
 * @method void setNcFilePath(string $ncFilePath)
 * @method string getOriginalFilename()
 * @method void setOriginalFilename(string $originalFilename)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Receipt extends Entity {
    protected string $userId = '';
    protected string $entityType = '';
    protected int $entityId = 0;
    protected string $ncFilePath = '';
    protected string $originalFilename = '';
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('entityId', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id'                => $this->id,
            'user_id'           => $this->userId,
            'entity_type'       => $this->entityType,
            'entity_id'         => $this->entityId,
            'nc_file_path'      => $this->ncFilePath,
            'original_filename' => $this->originalFilename,
            'created_at'        => $this->createdAt,
        ];
    }
}
