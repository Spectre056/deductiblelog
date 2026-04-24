<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class ItemCategoryMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_item_categories', ItemCategory::class);
    }

    /** @return ItemCategory[] */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->orderBy('category', 'ASC')
           ->addOrderBy('name', 'ASC');
        return $this->findEntities($qb);
    }

    /** @return ItemCategory[] */
    public function search(string $query, int $limit = 20): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->like(
               $qb->func()->lower('name'),
               $qb->createNamedParameter('%' . strtolower($this->db->escapeLikeParameter($query)) . '%'),
           ))
           ->orderBy('category', 'ASC')
           ->addOrderBy('name', 'ASC')
           ->setMaxResults($limit);
        return $this->findEntities($qb);
    }
}
