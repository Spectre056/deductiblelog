<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MileageLogMapper extends QBMapper {

    use ScopedQueries;

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_mileage_logs', MileageLog::class);
    }

    /** @return MileageLog[] */
    public function findAllByYear(string $userId, int $taxYear): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)))
           ->orderBy('date', 'DESC');
        return $this->findEntities($qb);
    }

    public function findById(int $id, string $userId): MileageLog {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntity($qb);
    }

    public function sumDeductionByYear(string $userId, int $taxYear): string {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->sum('deduction_amount'))
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $sum    = $result->fetchOne();
        $result->closeCursor();
        return $sum ?: '0.00';
    }

    public function sumMilesByYear(string $userId, int $taxYear): string {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->sum('miles'))
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $sum    = $result->fetchOne();
        $result->closeCursor();
        return $sum ?: '0.0';
    }

    /**
     * Deduction and miles per purpose for a year.
     * @return array<string, array{deduction: string, miles: string}> keyed by purpose_type
     */
    public function sumByYearAndPurpose(string $userId, int $taxYear): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('purpose_type')
           ->selectAlias($qb->func()->sum('deduction_amount'), 'deduction')
           ->selectAlias($qb->func()->sum('miles'), 'miles')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)))
           ->groupBy('purpose_type');
        $result = $qb->executeQuery();
        $rows   = [];
        while ($row = $result->fetch()) {
            $rows[(string) $row['purpose_type']] = [
                'deduction' => (string) ($row['deduction'] ?? '0.00'),
                'miles'     => (string) ($row['miles'] ?? '0.0'),
            ];
        }
        $result->closeCursor();
        return $rows;
    }
}
