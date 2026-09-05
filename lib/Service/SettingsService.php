<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\Setting;
use OCA\DeductibleLog\Db\SettingMapper;
use OCA\DeductibleLog\Db\TaxRate;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Http\Client\IClientService;

class SettingsService {

    private const RATES_URL = 'https://raw.githubusercontent.com/Spectre056/deductiblelog-data/main/rates.json';

    private const DEFAULTS = [
        'household_name'    => 'My Household',
        'last_update_check' => null,
    ];

    public function __construct(
        private SettingMapper $mapper,
        private TaxRateMapper $taxRateMapper,
        private IClientService $clientService,
    ) {}

    public function get(string $userId): array {
        $settings = array_merge(self::DEFAULTS, ['default_tax_year' => (string) date('Y')]);
        foreach ($this->mapper->findAllByUser($userId) as $row) {
            $settings[$row->getKey()] = $row->getValue();
        }
        return $settings;
    }

    public function save(string $userId, array $data): array {
        $v = new Validator();
        $values = [];
        if (array_key_exists('default_tax_year', $data)) {
            $year = (int) $data['default_tax_year'];
            if (!is_numeric($data['default_tax_year']) || $year < Validator::MIN_YEAR || $year > (int) date('Y') + 1) {
                $v->fail('default_tax_year', 'default_tax_year is out of range');
            }
            $values['default_tax_year'] = (string) $year;
        }
        if (array_key_exists('household_name', $data)) {
            $values['household_name'] = $v->optionalString($data['household_name'], 'household_name', 128);
        }
        if (array_key_exists('mando_theme', $data)) {
            $values['mando_theme'] = in_array($data['mando_theme'], ['1', 1, true, 'true'], true) ? '1' : '0';
        }
        $v->throwIfInvalid();

        foreach ($values as $key => $value) {
            $this->upsert($userId, $key, $value);
        }
        return $this->get($userId);
    }

    public function checkUpdates(string $userId): array {
        try {
            $client   = $this->clientService->newClient();
            $response = $client->get(self::RATES_URL, ['timeout' => 10]);
            $json     = json_decode((string) $response->getBody(), true);
        } catch (\Exception $e) {
            return ['error' => 'Failed to reach rates source: ' . $e->getMessage()];
        }

        if (!isset($json['rates']) || !is_array($json['rates'])) {
            return ['error' => 'Invalid rates.json format'];
        }

        $this->upsert($userId, 'last_update_check', (new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        $available = [];
        foreach ($json['rates'] as $rateData) {
            $year = (int) ($rateData['year'] ?? 0);
            if ($year < Validator::MIN_YEAR) {
                continue;
            }
            $new = [
                'charitable' => self::normalizeRate($rateData['charitable_cents'] ?? null),
                'medical'    => self::normalizeRate($rateData['medical_cents'] ?? null),
                'business'   => self::normalizeRate($rateData['business_cents'] ?? null),
            ];
            if (in_array(null, $new, true)) {
                continue;
            }

            try {
                $existing = $this->taxRateMapper->findByYear($year);
                $current  = [
                    'charitable' => self::normalizeRate($existing->getMileageCharitableCents()),
                    'medical'    => self::normalizeRate($existing->getMileageMedicalCents()),
                    'business'   => self::normalizeRate($existing->getMileageBusinessCents()),
                ];
                if ($current !== $new) {
                    $available[] = ['year' => $year, 'current' => $current, 'new' => $new];
                }
            } catch (DoesNotExistException) {
                $available[] = ['year' => $year, 'current' => null, 'new' => $new];
            }
        }

        return ['updates_available' => $available];
    }

    public function applyUpdates(string $userId, array $updates): void {
        $v    = new Validator();
        $rows = [];
        foreach (array_values($updates) as $i => $update) {
            $year = (int) ($update['year'] ?? 0);
            if ($year < Validator::MIN_YEAR || $year > (int) date('Y') + 1) {
                $v->fail("updates[{$i}].year", 'year is out of range');
                continue;
            }
            $rates = [];
            foreach (['charitable', 'medical', 'business'] as $key) {
                $rate = self::normalizeRate($update[$key] ?? null);
                if ($rate === null || (float) $rate <= 0 || (float) $rate > 200) {
                    $v->fail("updates[{$i}].{$key}", "{$key} must be a rate between 0.1 and 200 cents per mile");
                }
                $rates[$key] = $rate;
            }
            $rows[] = ['year' => $year, 'rates' => $rates];
        }
        $v->throwIfInvalid();

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($rows as $row) {
            try {
                $rate = $this->taxRateMapper->findByYear($row['year']);
            } catch (DoesNotExistException) {
                $rate = new TaxRate();
                $rate->setTaxYear($row['year']);
            }
            $rate->setMileageCharitableCents($row['rates']['charitable']);
            $rate->setMileageMedicalCents($row['rates']['medical']);
            $rate->setMileageBusinessCents($row['rates']['business']);
            $rate->setUpdatedAt($now);
            $rate->setSource('github:deductiblelog-data');

            if ($rate->getId() === null) {
                $this->taxRateMapper->insert($rate);
            } else {
                $this->taxRateMapper->update($rate);
            }
        }
    }

    /** "14", 14, 14.0 and "14.0" are the same rate; compare and store them in one form. */
    public static function normalizeRate(mixed $value): ?string {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return number_format((float) $value, 1, '.', '');
    }

    private function upsert(string $userId, string $key, ?string $value): void {
        try {
            $setting = $this->mapper->findByKey($userId, $key);
            $setting->setValue($value);
            $this->mapper->update($setting);
        } catch (DoesNotExistException) {
            $setting = new Setting();
            $setting->setUserId($userId);
            $setting->setKey($key);
            $setting->setValue($value);
            $this->mapper->insert($setting);
        }
    }
}
