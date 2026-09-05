<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Nextcloud's Entity only marks a field dirty when the new value differs from
 * the property's current value, so a field set to its own default is silently
 * left out of the INSERT. On a notnull column with no DB default that is a
 * constraint violation (v0.1.3's item_category_id, and the charitable mileage
 * rate that is 14.0 every year). Marking every set field dirty closes the whole
 * class. fromRow() resets the dirty set after hydration, so reads are unaffected.
 */
abstract class BaseEntity extends Entity {

    protected function setter(string $name, array $args): void {
        if (property_exists($this, $name) && $args[0] === $this->$name) {
            $this->markFieldUpdated($name);
            return;
        }
        parent::setter($name, $args);
    }
}
