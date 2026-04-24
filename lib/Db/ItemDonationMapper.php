<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ItemDonationMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_item_donations', ItemDonation::class);
    }

    /** @return ItemDonation[] */
    public function findAllByYear(string $userId, int $taxYear): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)))
           ->orderBy('date', 'DESC');
        return $this->findEntities($qb);
    }

    public function findById(int $id, string $userId): ItemDonation {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntity($qb);
    }

    public function sumByYear(string $userId, int $taxYear): string {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->sum('total_value'))
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $sum    = $result->fetchOne();
        $result->closeCursor();
        return $sum ?: '0.00';
    }
}
