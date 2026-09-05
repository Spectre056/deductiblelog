<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\CashDonation;
use OCA\DeductibleLog\Db\CashDonationMapper;
use OCA\DeductibleLog\Db\CharityMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class CashDonationService {

    public function __construct(
        private CashDonationMapper $mapper,
        private CharityMapper $charityMapper,
        private ReceiptMapper $receiptMapper,
    ) {}

    /** @return CashDonation[] */
    public function findAll(string $userId, int $taxYear): array {
        return $this->mapper->findAllByYear($userId, $taxYear);
    }

    public function yearTotal(string $userId, int $taxYear): string {
        return $this->mapper->sumByYear($userId, $taxYear);
    }

    public function create(string $userId, array $data): CashDonation {
        $now      = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $donation = new CashDonation();
        $donation->setUserId($userId);
        $donation->setCreatedAt($now);
        $this->apply($donation, $userId, $data);
        $donation->setUpdatedAt($now);
        return $this->mapper->insert($donation);
    }

    public function update(int $id, string $userId, array $data): CashDonation {
        $donation = $this->mapper->findById($id, $userId);
        $merged   = Merge::forUpdate($donation->jsonSerialize(), $data);
        $this->apply($donation, $userId, $merged);
        $donation->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        return $this->mapper->update($donation);
    }

    public function delete(int $id, string $userId): void {
        $donation = $this->mapper->findById($id, $userId);
        $this->receiptMapper->deleteByEntity('cash_donation', $donation->getId(), $userId);
        $this->mapper->delete($donation);
    }

    private function apply(CashDonation $donation, string $userId, array $data): void {
        $v         = new Validator();
        $charityId = $v->positiveInt($data['charity_id'] ?? null, 'charity_id');
        $date      = $v->date($data['date'] ?? null);
        $taxYear   = $v->taxYear($data['tax_year'] ?? null, $date);
        $amount    = $v->amount($data['amount'] ?? null);
        $payment   = $v->optionalString($data['payment_method'] ?? null, 'payment_method', 32);
        $notes     = $v->optionalString($data['notes'] ?? null, 'notes', 10000);

        if ($charityId !== null) {
            try {
                $this->charityMapper->findById($charityId, $userId);
            } catch (DoesNotExistException) {
                $v->fail('charity_id', 'Unknown charity');
            }
        }
        $v->throwIfInvalid();

        $donation->setCharityId($charityId);
        $donation->setTaxYear($taxYear);
        $donation->setDate($date);
        $donation->setAmount($amount);
        $donation->setPaymentMethod($payment);
        $donation->setNotes($notes);
    }
}
