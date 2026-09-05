<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Return-readiness fields.
 *
 * acknowledged (cash + item donations): the IRS requires a contemporaneous
 * written acknowledgment for any single contribution of $250 or more; the
 * report flags gifts that lack one.
 *
 * Form 8283 Section A fields on item lines: date acquired (month/year), how
 * acquired, cost basis, FMV method. Required once non-cash gifts exceed $500
 * for the year, and TXF cannot carry them.
 *
 * reimbursed_amount (medical): insurance/HSA/FSA reimbursements are not
 * deductible; totals use amount - reimbursed_amount.
 */
class Version000105Date20260905140000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema  = $schemaClosure();
        $changed = false;

        foreach (['deductiblelog_cash_donations', 'deductiblelog_item_donations'] as $table) {
            if ($schema->hasTable($table)) {
                $t = $schema->getTable($table);
                if (!$t->hasColumn('acknowledged')) {
                    $t->addColumn('acknowledged', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
                    $changed = true;
                }
            }
        }

        if ($schema->hasTable('deductiblelog_item_donation_lines')) {
            $t = $schema->getTable('deductiblelog_item_donation_lines');
            $cols = [
                'date_acquired' => [Types::STRING,  ['notnull' => false, 'length' => 7, 'default' => null]],
                'how_acquired'  => [Types::STRING,  ['notnull' => false, 'length' => 16, 'default' => null]],
                'cost_basis'    => [Types::DECIMAL, ['notnull' => false, 'precision' => 10, 'scale' => 2, 'default' => null]],
                'fmv_method'    => [Types::STRING,  ['notnull' => false, 'length' => 32, 'default' => null]],
            ];
            foreach ($cols as $name => [$type, $opts]) {
                if (!$t->hasColumn($name)) {
                    $t->addColumn($name, $type, $opts);
                    $changed = true;
                }
            }
        }

        if ($schema->hasTable('deductiblelog_medical_expenses')) {
            $t = $schema->getTable('deductiblelog_medical_expenses');
            if (!$t->hasColumn('reimbursed_amount')) {
                $t->addColumn('reimbursed_amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0.00']);
                $changed = true;
            }
        }

        return $changed ? $schema : null;
    }
}
