<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\CashDonation;
use OCA\DeductibleLog\Db\CashDonationMapper;

class CashDonationService {

    public function __construct(private CashDonationMapper $mapper) {}

    /** @return CashDonation[] */
    public function findAll(string $userId, int $taxYear): array {
        return $this->mapper->findAllByYear($userId, $taxYear);
    }

    public function yearTotal(string $userId, int $taxYear): string {
        return $this->mapper->sumByYear($userId, $taxYear);
    }

    public function create(string $userId, array $data): CashDonation {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $donation = new CashDonation();
        $donation->setUserId($userId);
        $donation->setCharityId((int) $data['charity_id']);
        $donation->setTaxYear((int) $data['tax_year']);
        $donation->setDate($data['date']);
        $donation->setAmount($data['amount']);
        $donation->setPaymentMethod($data['payment_method'] ?? null);
        $donation->setNotes($data['notes'] ?? null);
        $donation->setCreatedAt($now);
        $donation->setUpdatedAt($now);

        return $this->mapper->insert($donation);
    }

    public function update(int $id, string $userId, array $data): CashDonation {
        $donation = $this->mapper->findById($id, $userId);

        if (isset($data['charity_id']))    { $donation->setCharityId((int) $data['charity_id']); }
        if (isset($data['tax_year']))      { $donation->setTaxYear((int) $data['tax_year']); }
        if (isset($data['date']))          { $donation->setDate($data['date']); }
        if (isset($data['amount']))        { $donation->setAmount($data['amount']); }
        if (array_key_exists('payment_method', $data)) { $donation->setPaymentMethod($data['payment_method']); }
        if (array_key_exists('notes', $data))          { $donation->setNotes($data['notes']); }
        $donation->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        return $this->mapper->update($donation);
    }

    public function delete(int $id, string $userId): void {
        $donation = $this->mapper->findById($id, $userId);
        $this->mapper->delete($donation);
    }
}
