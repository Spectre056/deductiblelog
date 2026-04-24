<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000100Date20260423000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('deductiblelog_family_members')) {
            $t = $schema->createTable('deductiblelog_family_members');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
            $t->addColumn('relationship', Types::STRING, ['notnull' => true, 'length' => 16]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id'], 'dl_fm_user_id');
        }

        if (!$schema->hasTable('deductiblelog_charities')) {
            $t = $schema->createTable('deductiblelog_charities');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 256]);
            $t->addColumn('ein', Types::STRING, ['notnull' => false, 'length' => 12, 'default' => null]);
            $t->addColumn('address', Types::STRING, ['notnull' => false, 'length' => 256, 'default' => null]);
            $t->addColumn('city', Types::STRING, ['notnull' => false, 'length' => 128, 'default' => null]);
            $t->addColumn('state', Types::STRING, ['notnull' => false, 'length' => 2, 'default' => null]);
            $t->addColumn('zip', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => null]);
            $t->addColumn('notes', Types::TEXT, ['notnull' => false, 'default' => null]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id'], 'dl_ch_user_id');
        }

        if (!$schema->hasTable('deductiblelog_cash_donations')) {
            $t = $schema->createTable('deductiblelog_cash_donations');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('charity_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('tax_year', Types::SMALLINT, ['notnull' => true]);
            $t->addColumn('date', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
            $t->addColumn('payment_method', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => null]);
            $t->addColumn('notes', Types::TEXT, ['notnull' => false, 'default' => null]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id', 'tax_year'], 'dl_cd_user_year');
        }

        if (!$schema->hasTable('deductiblelog_item_donations')) {
            $t = $schema->createTable('deductiblelog_item_donations');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('charity_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('tax_year', Types::SMALLINT, ['notnull' => true]);
            $t->addColumn('date', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('notes', Types::TEXT, ['notnull' => false, 'default' => null]);
            $t->addColumn('total_value', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0.00']);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id', 'tax_year'], 'dl_id_user_year');
        }

        if (!$schema->hasTable('deductiblelog_item_donation_lines')) {
            $t = $schema->createTable('deductiblelog_item_donation_lines');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('donation_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('item_category_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('description', Types::STRING, ['notnull' => false, 'length' => 256, 'default' => null]);
            $t->addColumn('quantity', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $t->addColumn('condition', Types::STRING, ['notnull' => true, 'length' => 16]);
            $t->addColumn('unit_value', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
            $t->addColumn('total_value', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['donation_id'], 'dl_idl_donation_id');
        }

        if (!$schema->hasTable('deductiblelog_item_categories')) {
            $t = $schema->createTable('deductiblelog_item_categories');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('category', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 256]);
            $t->addColumn('min_value', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 2]);
            $t->addColumn('max_value', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 2]);
            $t->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 8]);
            $t->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['category'], 'dl_ic_category');
        }

        if (!$schema->hasTable('deductiblelog_mileage_logs')) {
            $t = $schema->createTable('deductiblelog_mileage_logs');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('family_member_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'default' => null]);
            $t->addColumn('tax_year', Types::SMALLINT, ['notnull' => true]);
            $t->addColumn('date', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('purpose_type', Types::STRING, ['notnull' => true, 'length' => 16]);
            $t->addColumn('description', Types::STRING, ['notnull' => false, 'length' => 512, 'default' => null]);
            $t->addColumn('miles', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 1]);
            $t->addColumn('rate_cents', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 1]);
            $t->addColumn('deduction_amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id', 'tax_year'], 'dl_ml_user_year');
        }

        if (!$schema->hasTable('deductiblelog_medical_expenses')) {
            $t = $schema->createTable('deductiblelog_medical_expenses');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('family_member_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'default' => null]);
            $t->addColumn('tax_year', Types::SMALLINT, ['notnull' => true]);
            $t->addColumn('date', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('provider', Types::STRING, ['notnull' => false, 'length' => 256, 'default' => null]);
            $t->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
            $t->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
            $t->addColumn('notes', Types::TEXT, ['notnull' => false, 'default' => null]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id', 'tax_year'], 'dl_me_user_year');
        }

        if (!$schema->hasTable('deductiblelog_business_expenses')) {
            $t = $schema->createTable('deductiblelog_business_expenses');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('family_member_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'default' => null]);
            $t->addColumn('tax_year', Types::SMALLINT, ['notnull' => true]);
            $t->addColumn('date', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 512]);
            $t->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
            $t->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
            $t->addColumn('notes', Types::TEXT, ['notnull' => false, 'default' => null]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id', 'tax_year'], 'dl_be_user_year');
        }

        if (!$schema->hasTable('deductiblelog_receipts')) {
            $t = $schema->createTable('deductiblelog_receipts');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->addColumn('entity_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('nc_file_path', Types::STRING, ['notnull' => true, 'length' => 1024]);
            $t->addColumn('original_filename', Types::STRING, ['notnull' => true, 'length' => 256]);
            $t->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['user_id', 'entity_type', 'entity_id'], 'dl_re_entity');
        }

        if (!$schema->hasTable('deductiblelog_tax_rates')) {
            $t = $schema->createTable('deductiblelog_tax_rates');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('tax_year', Types::SMALLINT, ['notnull' => true]);
            $t->addColumn('mileage_charitable_cents', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 1]);
            $t->addColumn('mileage_medical_cents', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 1]);
            $t->addColumn('mileage_business_cents', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 1]);
            $t->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $t->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['tax_year'], 'dl_tr_tax_year');
        }

        if (!$schema->hasTable('deductiblelog_settings')) {
            $t = $schema->createTable('deductiblelog_settings');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('key', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('value', Types::TEXT, ['notnull' => false, 'default' => null]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['user_id', 'key'], 'dl_se_user_key');
        }

        return $schema;
    }
}
