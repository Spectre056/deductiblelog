<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Give item_donation_lines.item_category_id a DB default of 0.
 *
 * Free-text lines (an item with no FMV catalog match) carry category id 0, which
 * is also the ItemDonationLine entity's property default. Nextcloud's Entity only
 * marks a field dirty when the value changes, so the column was omitted from the
 * INSERT entirely — and with notnull and no default, Postgres rejected the row.
 * Every free-text line therefore failed with a 500, from the API and from the
 * "create option" path in the UI alike.
 */
class Version000103Date20260905000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('deductiblelog_item_donation_lines')) {
            return null;
        }

        $table  = $schema->getTable('deductiblelog_item_donation_lines');
        $column = $table->getColumn('item_category_id');

        if ($column->getDefault() === null) {
            $column->setDefault(0);
            return $schema;
        }

        return null;
    }
}
