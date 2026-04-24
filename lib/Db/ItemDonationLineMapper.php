<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ItemDonationLineMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deductiblelog_item_donation_lines', ItemDonationLine::class);
    }

    /** @return ItemDonationLine[] */
    public function findAllByDonation(int $donationId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('donation_id', $qb->createNamedParameter($donationId, IQueryBuilder::PARAM_INT)))
           ->orderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    public function deleteByDonation(int $donationId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
           ->where($qb->expr()->eq('donation_id', $qb->createNamedParameter($donationId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
