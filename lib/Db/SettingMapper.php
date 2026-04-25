<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SettingMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_settings', Setting::class);
    }

    public function findByKey(string $userId, string $key): Setting {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('key', $qb->createNamedParameter($key)));
        return $this->findEntity($qb);
    }

    /** @return Setting[] */
    public function findAllByUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntities($qb);
    }
}
