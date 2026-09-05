<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Exception\ValidationException;

/**
 * Field-level validation shared by every module. Each method returns the
 * normalized value or records an error; throwIfInvalid() raises a 422 with
 * every message collected so far.
 */
class Validator {

    public const CONDITIONS      = ['poor', 'good', 'excellent'];
    public const PURPOSE_TYPES   = ['charitable', 'medical', 'business'];
    public const RELATIONSHIPS   = ['self', 'spouse', 'dependent'];
    public const RECEIPT_ENTITY_TYPES = ['cash_donation', 'item_donation', 'mileage', 'medical', 'business'];
    public const HOW_ACQUIRED  = ['purchase', 'gift', 'inheritance', 'exchange', 'other'];
    public const FMV_METHODS   = ['thrift_shop_value', 'comparable_sales', 'appraisal', 'catalog', 'other'];

    /** Single contributions at or above this need a contemporaneous written acknowledgment (IRC §170(f)(8)). */
    public const ACKNOWLEDGMENT_THRESHOLD = '250.00';
    /** Annual aggregate non-cash gifts above this require Form 8283 Section A. */
    public const FORM_8283_THRESHOLD = '500.00';

    /** Earliest tax year accepted anywhere; far enough back for amended returns. */
    public const MIN_YEAR = 2000;

    /** @var array<string,string> */
    private array $errors = [];

    public function fail(string $field, string $message): void {
        $this->errors[$field] = $message;
    }

    public function throwIfInvalid(): void {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }

    public function hasErrors(): bool {
        return $this->errors !== [];
    }

    /** ISO calendar date, returned unchanged on success. */
    public function date(mixed $value, string $field = 'date'): ?string {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            $this->fail($field, "{$field} must be a date in YYYY-MM-DD form");
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < self::MIN_YEAR) {
            $this->fail($field, "{$field} is not a valid calendar date");
            return null;
        }
        return $value;
    }

    /**
     * The tax year a record belongs to. Derived from the date; an explicit
     * value is accepted only when it agrees, because a record whose tax_year
     * disagrees with its date appears in the wrong return.
     */
    public function taxYear(mixed $explicit, ?string $date, string $field = 'tax_year'): ?int {
        if ($date === null) {
            return null;
        }
        $fromDate = (int) substr($date, 0, 4);
        if ($explicit === null || $explicit === '') {
            return $fromDate;
        }
        if (!is_numeric($explicit) || (int) $explicit !== $fromDate) {
            $this->fail($field, "{$field} must match the year of the date ({$fromDate})");
            return null;
        }
        return $fromDate;
    }

    /**
     * A positive money amount with at most two decimals, normalized to "0.00"
     * form. Rejects negatives, exponents and anything the DB would round.
     */
    public function amount(mixed $value, string $field = 'amount', bool $allowZero = false): ?string {
        $s = is_int($value) || is_float($value) ? (string) $value : (is_string($value) ? trim($value) : '');
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $s)) {
            $this->fail($field, "{$field} must be a positive amount with at most two decimals");
            return null;
        }
        $normalized = number_format((float) $s, 2, '.', '');
        if (!$allowZero && $normalized === '0.00') {
            $this->fail($field, "{$field} must be greater than 0");
            return null;
        }
        return $normalized;
    }

    /** A positive decimal with one decimal place (miles, cents per mile). */
    public function oneDecimal(mixed $value, string $field, bool $allowZero = false): ?string {
        $s = is_int($value) || is_float($value) ? (string) $value : (is_string($value) ? trim($value) : '');
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $s)) {
            $this->fail($field, "{$field} must be a positive number");
            return null;
        }
        $normalized = number_format((float) $s, 1, '.', '');
        if (!$allowZero && (float) $normalized <= 0) {
            $this->fail($field, "{$field} must be greater than 0");
            return null;
        }
        return $normalized;
    }

    public function positiveInt(mixed $value, string $field): ?int {
        $n = null;
        if (is_int($value)) {
            $n = $value;
        } elseif (is_float($value) && floor($value) === $value) {
            $n = (int) $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $n = (int) trim($value);
        }
        if ($n === null || $n <= 0) {
            $this->fail($field, "{$field} must be a positive integer");
            return null;
        }
        return $n;
    }

    public function optionalInt(mixed $value, string $field): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->positiveInt($value, $field);
    }

    /** Optional YYYY-MM (Form 8283 wants month and year acquired). */
    public function yearMonth(mixed $value, string $field): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        $s = is_string($value) ? trim($value) : '';
        if (!preg_match('/^(\d{4})-(\d{2})$/', $s, $m) || (int) $m[2] < 1 || (int) $m[2] > 12 || (int) $m[1] < 1900) {
            $this->fail($field, "{$field} must be a month in YYYY-MM form");
            return null;
        }
        return $s;
    }

    /** Optional enum: empty means "not given". */
    public function optionalEnum(mixed $value, array $allowed, string $field): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->enum($value, $allowed, $field);
    }

    /** true/false/1/0/"1"/"0"/"true"/"false" → 1 or 0. */
    public function flag(mixed $value, string $field): int {
        if (in_array($value, [true, 1, '1', 'true', 'on'], true)) {
            return 1;
        }
        if ($value === null || in_array($value, [false, 0, '0', 'false', '', 'off'], true)) {
            return 0;
        }
        $this->fail($field, "{$field} must be true or false");
        return 0;
    }

    public function enum(mixed $value, array $allowed, string $field): ?string {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            $this->fail($field, "{$field} must be one of: " . implode(', ', $allowed));
            return null;
        }
        return $value;
    }

    public function requiredString(mixed $value, string $field, int $maxLength = 256): ?string {
        $s = is_string($value) ? trim($value) : '';
        if ($s === '') {
            $this->fail($field, "{$field} is required");
            return null;
        }
        if (mb_strlen($s) > $maxLength) {
            $this->fail($field, "{$field} must be at most {$maxLength} characters");
            return null;
        }
        return $s;
    }

    public function optionalString(mixed $value, string $field, int $maxLength = 256): ?string {
        if ($value === null) {
            return null;
        }
        $s = is_string($value) ? trim($value) : (is_scalar($value) ? (string) $value : '');
        if ($s === '') {
            return null;
        }
        if (mb_strlen($s) > $maxLength) {
            $this->fail($field, "{$field} must be at most {$maxLength} characters");
            return null;
        }
        return $s;
    }
}
