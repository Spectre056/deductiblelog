<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Runs after every migration pass (install and upgrade), so reference data is
 * repaired without adding a new migration each release. Replaces the
 * reflection-based re-seeding in Version000101 / Version000102.
 */
class SeedRepairStep implements IRepairStep {

    public function __construct(private IDBConnection $db) {}

    public function getName(): string {
        return 'Seed DeductibleLog IRS mileage rates and FMV item catalog';
    }

    public function run(IOutput $output): void {
        $seeder = new Seeder($this->db);
        $rates  = $seeder->seedTaxRates();
        $items  = $seeder->seedItemCategories();
        $output->info("DeductibleLog seed: {$rates} tax-rate row(s), {$items} catalog item(s) added");
    }
}
