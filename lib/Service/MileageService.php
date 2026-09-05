<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\FamilyMemberMapper;
use OCA\DeductibleLog\Db\MileageLog;
use OCA\DeductibleLog\Db\MileageLogMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class MileageService {

    public function __construct(
        private MileageLogMapper $mapper,
        private TaxRateMapper $taxRateMapper,
        private FamilyMemberMapper $familyMapper,
        private ReceiptMapper $receiptMapper,
    ) {}

    /** @return MileageLog[] */
    public function findAll(string $userId, int $taxYear): array {
        return $this->mapper->findAllByYear($userId, $taxYear);
    }

    public function yearTotals(string $userId, int $taxYear): array {
        return [
            'deduction'  => $this->mapper->sumDeductionByYear($userId, $taxYear),
            'miles'      => $this->mapper->sumMilesByYear($userId, $taxYear),
            'by_purpose' => $this->byPurpose($userId, $taxYear),
        ];
    }

    /** @return array<string, array{deduction: string, miles: string}> every purpose present, zero-filled */
    public function byPurpose(string $userId, int $taxYear): array {
        $rows = $this->mapper->sumByYearAndPurpose($userId, $taxYear);
        $out  = [];
        foreach (Validator::PURPOSE_TYPES as $purpose) {
            $out[$purpose] = $rows[$purpose] ?? ['deduction' => '0.00', 'miles' => '0.0'];
        }
        return $out;
    }

    public function allRates(): array {
        $map = [];
        foreach ($this->taxRateMapper->findAll() as $rate) {
            $map[$rate->getTaxYear()] = $rate->jsonSerialize();
        }
        return $map;
    }

    public function create(string $userId, array $data): MileageLog {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $log = new MileageLog();
        $log->setUserId($userId);
        $log->setCreatedAt($now);
        $this->apply($log, $userId, $data, null, $data['rate_cents'] ?? null);
        $log->setUpdatedAt($now);
        return $this->mapper->insert($log);
    }

    public function update(int $id, string $userId, array $data): MileageLog {
        $log = $this->mapper->findById($id, $userId);
        $this->apply($log, $userId, Merge::forUpdate($log->jsonSerialize(), $data), $log->jsonSerialize(), $data['rate_cents'] ?? null);
        $log->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        return $this->mapper->update($log);
    }

    public function delete(int $id, string $userId): void {
        $log = $this->mapper->findById($id, $userId);
        $this->receiptMapper->deleteByEntity('mileage', $log->getId(), $userId);
        $this->mapper->delete($log);
    }

    /**
     * Rate precedence: an explicit positive rate_cents from the client wins
     * (manual entry for a year the table does not know yet); otherwise the IRS
     * table for year + purpose; on an update whose year and purpose are
     * unchanged and that sends no rate, the stored rate is kept. A rate of zero
     * is never persisted: that would silently write a $0 deduction.
     */
    private function apply(MileageLog $log, string $userId, array $data, ?array $stored, mixed $clientRate): void {
        $v           = new Validator();
        $date        = $v->date($data['date'] ?? null);
        $taxYear     = $v->taxYear($data['tax_year'] ?? null, $date);
        $purpose     = $v->enum($data['purpose_type'] ?? null, Validator::PURPOSE_TYPES, 'purpose_type');
        $miles       = $v->oneDecimal($data['miles'] ?? null, 'miles');
        $memberId    = $v->optionalInt($data['family_member_id'] ?? null, 'family_member_id');
        $description = $v->optionalString($data['description'] ?? null, 'description', 512);

        $rateGiven  = $clientRate !== null && $clientRate !== '' && (float) $clientRate > 0;
        $rate       = $rateGiven ? $v->oneDecimal($clientRate, 'rate_cents') : null;

        if ($memberId !== null) {
            try {
                $this->familyMapper->findById($memberId, $userId);
            } catch (DoesNotExistException) {
                $v->fail('family_member_id', 'Unknown family member');
            }
        }
        $v->throwIfInvalid();

        if ($rate === null) {
            $unchanged = $stored !== null
                && (int) $stored['tax_year'] === $taxYear
                && $stored['purpose_type'] === $purpose
                && (float) $stored['rate_cents'] > 0;
            $rate = $unchanged ? $stored['rate_cents'] : $this->lookupRate($taxYear, $purpose);
            if ($rate === null) {
                $v->fail('rate_cents', "No IRS mileage rate on file for {$taxYear}. Enter the rate manually or add {$taxYear} under Settings.");
                $v->throwIfInvalid();
            }
        }

        $log->setFamilyMemberId($memberId);
        $log->setTaxYear($taxYear);
        $log->setDate($date);
        $log->setPurposeType($purpose);
        $log->setDescription($description);
        $log->setMiles($miles);
        $log->setRateCents($rate);
        $log->setDeductionAmount(Money::mileageDeduction($miles, $rate));
    }

    private function lookupRate(int $taxYear, string $purposeType): ?string {
        try {
            $row = $this->taxRateMapper->findByYear($taxYear);
        } catch (DoesNotExistException) {
            return null;
        }
        $rate = match ($purposeType) {
            'charitable' => $row->getMileageCharitableCents(),
            'medical'    => $row->getMileageMedicalCents(),
            'business'   => $row->getMileageBusinessCents(),
        };
        return (float) $rate > 0 ? number_format((float) $rate, 1, '.', '') : null;
    }
}
