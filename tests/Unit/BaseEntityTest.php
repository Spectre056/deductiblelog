<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\BaseEntity;
use PHPUnit\Framework\TestCase;

/**
 * Guards the whole "default equals value, column omitted from INSERT" class.
 * For every entity, every property set to its own default must still be
 * reported as an updated field, or QBMapper::insert() leaves it out.
 */
class BaseEntityTest extends TestCase {

    /** @return iterable<string, array{class-string<BaseEntity>}> */
    public static function entities(): iterable {
        foreach (glob(__DIR__ . '/../../lib/Db/*.php') as $file) {
            $name = basename($file, '.php');
            if (str_ends_with($name, 'Mapper') || in_array($name, ['BaseEntity', 'ScopedQueries'], true)) {
                continue;
            }
            yield $name => ['OCA\\DeductibleLog\\Db\\' . $name];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('entities')]
    public function testSettingAFieldToItsDefaultMarksItUpdated(string $class): void {
        $entity = new $class();
        $this->assertInstanceOf(BaseEntity::class, $entity, "$class must extend BaseEntity");

        $ref = new \ReflectionClass($class);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PROTECTED) as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $name    = $prop->getName();
            $default = $prop->getDefaultValue();
            $setter  = 'set' . ucfirst($name);
            $entity->$setter($default);
            $this->assertArrayHasKey($name, $entity->getUpdatedFields(), "$class::$name set to its default was not marked updated");
        }
    }

    public function testHydrationFromRowLeavesNothingDirty(): void {
        $entity = \OCA\DeductibleLog\Db\TaxRate::fromRow([
            'id' => 5, 'tax_year' => 2026, 'mileage_charitable_cents' => '14.0',
            'mileage_medical_cents' => '20.5', 'mileage_business_cents' => '72.5',
            'updated_at' => '2026-01-01 00:00:00', 'source' => 'irs_hardcoded',
        ]);
        $this->assertSame([], $entity->getUpdatedFields());
        $this->assertSame(2026, $entity->getTaxYear());
    }
}
