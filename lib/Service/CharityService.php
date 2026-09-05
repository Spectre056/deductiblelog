<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\CashDonationMapper;
use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Db\CharityMapper;
use OCA\DeductibleLog\Db\ItemDonationMapper;
use OCA\DeductibleLog\Exception\ConflictException;

class CharityService {

    public function __construct(
        private CharityMapper $mapper,
        private CashDonationMapper $cashMapper,
        private ItemDonationMapper $itemMapper,
    ) {}

    /** @return Charity[] */
    public function findAll(string $userId, ?string $search = null): array {
        if ($search !== null && $search !== '') {
            return $this->mapper->search($userId, $search);
        }
        return $this->mapper->findAll($userId);
    }

    public function create(string $userId, array $data): Charity {
        $now     = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $charity = new Charity();
        $charity->setUserId($userId);
        $charity->setCreatedAt($now);
        $this->apply($charity, $data);
        $charity->setUpdatedAt($now);
        return $this->mapper->insert($charity);
    }

    public function update(int $id, string $userId, array $data): Charity {
        $charity = $this->mapper->findById($id, $userId);
        $this->apply($charity, array_merge($charity->jsonSerialize(), $data));
        $charity->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        return $this->mapper->update($charity);
    }

    /** Refuses while donations still reference the charity; a blank donee on a return is worse than an extra click. */
    public function delete(int $id, string $userId): void {
        $charity = $this->mapper->findById($id, $userId);
        $cash    = $this->cashMapper->countWhere('charity_id', $charity->getId(), $userId);
        $items   = $this->itemMapper->countWhere('charity_id', $charity->getId(), $userId);
        if ($cash + $items > 0) {
            throw new ConflictException(
                sprintf('%s is referenced by %d cash and %d item donation(s). Reassign or delete those first.', $charity->getName(), $cash, $items),
                ['references' => ['cash_donations' => $cash, 'item_donations' => $items]],
            );
        }
        $this->mapper->delete($charity);
    }

    private function apply(Charity $charity, array $data): void {
        $v = new Validator();
        $name    = $v->requiredString($data['name'] ?? null, 'name', 256);
        $ein     = $v->optionalString($data['ein'] ?? null, 'ein', 12);
        $address = $v->optionalString($data['address'] ?? null, 'address', 256);
        $city    = $v->optionalString($data['city'] ?? null, 'city', 128);
        $state   = $v->optionalString($data['state'] ?? null, 'state', 2);
        $zip     = $v->optionalString($data['zip'] ?? null, 'zip', 10);
        $notes   = $v->optionalString($data['notes'] ?? null, 'notes', 10000);
        if ($ein !== null) {
            $digits = preg_replace('/\D/', '', $ein) ?? '';
            if (strlen($digits) !== 9) {
                $v->fail('ein', 'EIN must be nine digits, e.g. 12-3456789');
            } else {
                $ein = substr($digits, 0, 2) . '-' . substr($digits, 2);
            }
        }
        $v->throwIfInvalid();

        $charity->setName($name);
        $charity->setEin($ein);
        $charity->setAddress($address);
        $charity->setCity($city);
        $charity->setState($state !== null ? strtoupper($state) : null);
        $charity->setZip($zip);
        $charity->setNotes($notes);
    }
}
