<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\MileageLog;
use OCA\DeductibleLog\Db\MileageLogMapper;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class MileageService {

    public function __construct(
        private MileageLogMapper $mapper,
        private TaxRateMapper $taxRateMapper,
    ) {}

    /** @return MileageLog[] */
    public function findAll(string $userId, int $taxYear): array {
        return $this->mapper->findAllByYear($userId, $taxYear);
    }

    public function yearTotals(string $userId, int $taxYear): array {
        return [
            'deduction' => $this->mapper->sumDeductionByYear($userId, $taxYear),
            'miles'     => $this->mapper->sumMilesByYear($userId, $taxYear),
        ];
    }

    public function allRates(): array {
        $rates = $this->taxRateMapper->findAll();
        $map   = [];
        foreach ($rates as $rate) {
            $map[$rate->getTaxYear()] = $rate->jsonSerialize();
        }
        return $map;
    }

    public function create(string $userId, array $data): MileageLog {
        $now         = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $taxYear     = (int) $data['tax_year'];
        $purposeType = $data['purpose_type'];
        $miles       = (float) $data['miles'];
        $rateCents   = $this->resolveRate($data, $taxYear, $purposeType);
        $deduction   = number_format($miles * $rateCents / 100, 2, '.', '');

        $log = new MileageLog();
        $log->setUserId($userId);
        $log->setFamilyMemberId(isset($data['family_member_id']) && $data['family_member_id'] !== '' ? (int) $data['family_member_id'] : null);
        $log->setTaxYear($taxYear);
        $log->setDate($data['date']);
        $log->setPurposeType($purposeType);
        $log->setDescription($data['description'] ?? null);
        $log->setMiles(number_format($miles, 1, '.', ''));
        $log->setRateCents(number_format($rateCents, 1, '.', ''));
        $log->setDeductionAmount($deduction);
        $log->setCreatedAt($now);
        $log->setUpdatedAt($now);

        return $this->mapper->insert($log);
    }

    public function update(int $id, string $userId, array $data): MileageLog {
        $log = $this->mapper->findById($id, $userId);

        if (isset($data['tax_year']))     { $log->setTaxYear((int) $data['tax_year']); }
        if (isset($data['date']))         { $log->setDate($data['date']); }
        if (isset($data['purpose_type'])) { $log->setPurposeType($data['purpose_type']); }
        if (array_key_exists('description', $data))      { $log->setDescription($data['description']); }
        if (array_key_exists('family_member_id', $data)) {
            $log->setFamilyMemberId($data['family_member_id'] !== '' && $data['family_member_id'] !== null ? (int) $data['family_member_id'] : null);
        }

        if (isset($data['miles']) || isset($data['rate_cents']) || isset($data['purpose_type'])) {
            $miles     = (float) ($data['miles'] ?? $log->getMiles());
            $rateCents = $this->resolveRate($data, $log->getTaxYear(), $log->getPurposeType());
            $log->setMiles(number_format($miles, 1, '.', ''));
            $log->setRateCents(number_format($rateCents, 1, '.', ''));
            $log->setDeductionAmount(number_format($miles * $rateCents / 100, 2, '.', ''));
        }

        $log->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        return $this->mapper->update($log);
    }

    public function delete(int $id, string $userId): void {
        $log = $this->mapper->findById($id, $userId);
        $this->mapper->delete($log);
    }

    private function resolveRate(array $data, int $taxYear, string $purposeType): float {
        if (isset($data['rate_cents']) && $data['rate_cents'] !== '') {
            return (float) $data['rate_cents'];
        }
        try {
            $rate = $this->taxRateMapper->findByYear($taxYear);
            return (float) match($purposeType) {
                'charitable' => $rate->getMileageCharitableCents(),
                'medical'    => $rate->getMileageMedicalCents(),
                'business'   => $rate->getMileageBusinessCents(),
                default      => '0.0',
            };
        } catch (DoesNotExistException) {
            return 0.0;
        }
    }
}
