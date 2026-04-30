<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000101Date20260430000000 extends SimpleMigrationStep {

    public function __construct(private IDBConnection $db) {}

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
        $legacySeeder = new Version000100Date20260423000001($this->db);
        $this->invoke($legacySeeder, 'seedTaxRates');
        $this->invoke($legacySeeder, 'seedItemCategories');
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
        return null;
    }

    private function invoke(object $target, string $method): void {
        $ref = new \ReflectionMethod($target, $method);
        $ref->setAccessible(true);
        $ref->invoke($target);
    }
}
