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
        foreach (['default_tax_year', 'household_name', 'mando_theme'] as $key) {
            if (array_key_exists($key, $data)) {
                $this->upsert($userId, $key, $data[$key] !== null ? (string) $data[$key] : null);
            }
        }
        return $this->get($userId);
    }

    public function checkUpdates(string $userId): array {
        try {
            $client   = $this->clientService->newClient();
            $response = $client->get(self::RATES_URL, ['timeout' => 10]);
            $json     = json_decode($response->getBody(), true);
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
            if ($year < 2020) {
                continue;
            }
            $newCharitable = (string) ($rateData['charitable_cents'] ?? '');
            $newMedical    = (string) ($rateData['medical_cents'] ?? '');
            $newBusiness   = (string) ($rateData['business_cents'] ?? '');

            try {
                $existing = $this->taxRateMapper->findByYear($year);
                if (
                    $existing->getMileageCharitableCents() !== $newCharitable ||
                    $existing->getMileageMedicalCents()    !== $newMedical    ||
                    $existing->getMileageBusinessCents()   !== $newBusiness
                ) {
                    $available[] = [
                        'year'    => $year,
                        'current' => [
                            'charitable' => $existing->getMileageCharitableCents(),
                            'medical'    => $existing->getMileageMedicalCents(),
                            'business'   => $existing->getMileageBusinessCents(),
                        ],
                        'new' => [
                            'charitable' => $newCharitable,
                            'medical'    => $newMedical,
                            'business'   => $newBusiness,
                        ],
                    ];
                }
            } catch (DoesNotExistException) {
                $available[] = [
                    'year'    => $year,
                    'current' => null,
                    'new'     => [
                        'charitable' => $newCharitable,
                        'medical'    => $newMedical,
                        'business'   => $newBusiness,
                    ],
                ];
            }
        }

        return ['updates_available' => $available];
    }

    public function applyUpdates(string $userId, array $updates): void {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($updates as $update) {
            $year = (int) ($update['year'] ?? 0);
            if ($year < 2020) {
                continue;
            }
            try {
                $rate = $this->taxRateMapper->findByYear($year);
            } catch (DoesNotExistException) {
                $rate = new TaxRate();
                $rate->setTaxYear($year);
            }
            $rate->setMileageCharitableCents((string) $update['charitable']);
            $rate->setMileageMedicalCents((string) $update['medical']);
            $rate->setMileageBusinessCents((string) $update['business']);
            $rate->setUpdatedAt($now);
            $rate->setSource('github:deductiblelog-data');

            if ($rate->getId() === null) {
                $this->taxRateMapper->insert($rate);
            } else {
                $this->taxRateMapper->update($rate);
            }
        }
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
