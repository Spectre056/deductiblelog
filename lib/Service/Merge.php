<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

final class Merge {

    /**
     * Overlay a partial update on the stored record so the full record can be
     * validated once. When the date changes and no explicit tax_year comes with
     * it, the year is re-derived from the new date instead of being carried
     * over stale from the stored row.
     */
    public static function forUpdate(array $stored, array $data): array {
        $merged = array_merge($stored, $data);
        if (array_key_exists('date', $data) && !array_key_exists('tax_year', $data)) {
            unset($merged['tax_year']);
        }
        return $merged;
    }
}
