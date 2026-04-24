<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ReceiptMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_receipts', Receipt::class);
    }

    /** @return Receipt[] */
    public function findByEntity(string $entityType, int $entityId, string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType)))
           ->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT)))
           ->orderBy('created_at', 'ASC');
        return $this->findEntities($qb);
    }

    public function findById(int $id, string $userId): Receipt {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntity($qb);
    }

    public function deleteByEntity(string $entityType, int $entityId, string $userId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType)))
           ->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
