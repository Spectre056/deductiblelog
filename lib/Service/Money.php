<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

/**
 * Integer-cents arithmetic for DECIMAL(10,2) values that travel as strings.
 * Every total the app persists or reports goes through here so no float ever
 * reaches a stored or displayed dollar figure.
 */
final class Money {

    public static function toCents(string|int|float|null $amount): int {
        if ($amount === null || $amount === '') {
            return 0;
        }
        $s = trim((string) $amount);
        $negative = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        [$whole, $frac] = array_pad(explode('.', $s, 2), 2, '');
        $frac = substr(str_pad($frac, 2, '0'), 0, 2);
        $cents = (int) $whole * 100 + (int) $frac;
        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    public static function sum(string|int|float|null ...$amounts): string {
        $total = 0;
        foreach ($amounts as $a) {
            $total += self::toCents($a);
        }
        return self::fromCents($total);
    }

    /**
     * miles (one decimal) × rate in cents per mile (one decimal), rounded half
     * up to the cent. 12.3 mi × 20.5¢ = 252.15¢ → "2.52".
     */
    public static function mileageDeduction(string $miles, string $rateCents): string {
        $miles10 = (int) round((float) $miles * 10);
        $rate10  = (int) round((float) $rateCents * 10);
        $hundredthsOfCent = $miles10 * $rate10;
        return self::fromCents(intdiv($hundredthsOfCent + 50, 100));
    }

    public static function lineTotal(string $unitValue, int $quantity): string {
        return self::fromCents(self::toCents($unitValue) * $quantity);
    }
}
