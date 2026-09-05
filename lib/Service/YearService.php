<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\BusinessExpenseMapper;
use OCA\DeductibleLog\Db\CashDonationMapper;
use OCA\DeductibleLog\Db\ItemDonationMapper;
use OCA\DeductibleLog\Db\MedicalExpenseMapper;
use OCA\DeductibleLog\Db\MileageLogMapper;

/** Tax years the UI should offer: every year with data, plus the current year and the user's default. */
class YearService {

    public function __construct(
        private CashDonationMapper $cash,
        private ItemDonationMapper $items,
        private MileageLogMapper $mileage,
        private MedicalExpenseMapper $medical,
        private BusinessExpenseMapper $business,
        private SettingsService $settings,
    ) {}

    /** @return int[] descending */
    public function years(string $userId): array {
        $years = array_merge(
            $this->cash->distinctYears($userId),
            $this->items->distinctYears($userId),
            $this->mileage->distinctYears($userId),
            $this->medical->distinctYears($userId),
            $this->business->distinctYears($userId),
            [(int) date('Y')],
        );
        $default = (int) ($this->settings->get($userId)['default_tax_year'] ?? 0);
        if ($default >= Validator::MIN_YEAR) {
            $years[] = $default;
        }
        $years = array_values(array_unique(array_filter($years, fn($y) => $y >= Validator::MIN_YEAR)));
        rsort($years);
        return $years;
    }
}
