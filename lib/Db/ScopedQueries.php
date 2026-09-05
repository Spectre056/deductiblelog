<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;

/** Shared read helpers for the per-user, per-year tables. */
trait ScopedQueries {

    /** @return int[] distinct tax years this user has rows for, ascending */
    public function distinctYears(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct('tax_year')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->orderBy('tax_year', 'ASC');
        $result = $qb->executeQuery();
        $years  = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
        $result->closeCursor();
        return $years;
    }

    /** Rows for this user where $column = $value. */
    public function countWhere(string $column, int $value, string $userId): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('id'))
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $count  = (int) $result->fetchOne();
        $result->closeCursor();
        return $count;
    }
}
