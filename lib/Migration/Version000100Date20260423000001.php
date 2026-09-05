<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Original seed step. The data now lives in SeedData and is applied by
 * SeedRepairStep on every upgrade; this class remains so the migration
 * history stays valid, and its private methods are still invoked by
 * Version000101 / Version000102 via reflection.
 */
class Version000100Date20260423000001 extends SimpleMigrationStep {

    public function __construct(private IDBConnection $db) {}

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
        $this->seedTaxRates();
        $this->seedItemCategories();
    }

    private function seedTaxRates(): void {
        (new Seeder($this->db))->seedTaxRates();
    }

    private function seedItemCategories(): void {
        (new Seeder($this->db))->seedItemCategories();
    }
}
