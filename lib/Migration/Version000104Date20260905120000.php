<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * receipts.file_id: resolve receipts by Nextcloud file id so a rename or move
 * in Files no longer breaks download. Existing rows keep nc_file_path as a
 * fallback and are upgraded lazily on first access.
 *
 * item_donation_lines.fmv_source: which catalog edition priced the line, so
 * values keep their provenance when the Salvation Army guide is refreshed.
 */
class Version000104Date20260905120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema  = $schemaClosure();
        $changed = false;

        if ($schema->hasTable('deductiblelog_receipts')) {
            $t = $schema->getTable('deductiblelog_receipts');
            if (!$t->hasColumn('file_id')) {
                $t->addColumn('file_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'default' => null]);
                $changed = true;
            }
        }

        if ($schema->hasTable('deductiblelog_item_donation_lines')) {
            $t = $schema->getTable('deductiblelog_item_donation_lines');
            if (!$t->hasColumn('fmv_source')) {
                $t->addColumn('fmv_source', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
                $changed = true;
            }
        }

        return $changed ? $schema : null;
    }
}
