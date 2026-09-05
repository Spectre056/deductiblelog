<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class Seeder {

    public function __construct(private IDBConnection $db) {}

    /** @return int rows inserted */
    public function seedTaxRates(): int {
        $now      = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $inserted = 0;

        foreach (SeedData::taxRates() as [$year, $charitable, $medical, $business]) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')
               ->from('deductiblelog_tax_rates')
               ->where($qb->expr()->eq('tax_year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)));
            $result = $qb->executeQuery();
            $exists = $result->fetch();
            $result->closeCursor();
            if ($exists) {
                continue;
            }

            $qb = $this->db->getQueryBuilder();
            $qb->insert('deductiblelog_tax_rates')->values([
                'tax_year'                 => $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT),
                'mileage_charitable_cents' => $qb->createNamedParameter($charitable),
                'mileage_medical_cents'    => $qb->createNamedParameter($medical),
                'mileage_business_cents'   => $qb->createNamedParameter($business),
                'updated_at'               => $qb->createNamedParameter($now),
                'source'                   => $qb->createNamedParameter('irs_hardcoded'),
            ]);
            $qb->executeStatement();
            $inserted++;
        }
        return $inserted;
    }

    /** @return int rows inserted */
    public function seedItemCategories(): int {
        $inserted = 0;
        foreach (SeedData::itemCategories() as [$category, $name, $minValue, $maxValue, $unit]) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')
               ->from('deductiblelog_item_categories')
               ->where($qb->expr()->eq('category', $qb->createNamedParameter($category)))
               ->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)))
               ->setMaxResults(1);
            $result = $qb->executeQuery();
            $exists = $result->fetch();
            $result->closeCursor();
            if ($exists) {
                continue;
            }

            $qb = $this->db->getQueryBuilder();
            $qb->insert('deductiblelog_item_categories')->values([
                'category'  => $qb->createNamedParameter($category),
                'name'      => $qb->createNamedParameter($name),
                'min_value' => $qb->createNamedParameter($minValue),
                'max_value' => $qb->createNamedParameter($maxValue),
                'unit'      => $qb->createNamedParameter($unit),
                'source'    => $qb->createNamedParameter('salvation_army'),
            ]);
            $qb->executeStatement();
            $inserted++;
        }
        return $inserted;
    }
}
