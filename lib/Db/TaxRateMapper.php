<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TaxRateMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_tax_rates', TaxRate::class);
    }

    public function findByYear(int $taxYear): TaxRate {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('tax_year', $qb->createNamedParameter($taxYear, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /** @return TaxRate[] */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->orderBy('tax_year', 'ASC');
        return $this->findEntities($qb);
    }
}
