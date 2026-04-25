<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Db\CharityMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class CharityService {

    public function __construct(private CharityMapper $mapper) {}

    /** @return Charity[] */
    public function findAll(string $userId, ?string $search = null): array {
        if ($search !== null && $search !== '') {
            return $this->mapper->search($userId, $search);
        }
        return $this->mapper->findAll($userId);
    }

    public function create(string $userId, array $data): Charity {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $charity = new Charity();
        $charity->setUserId($userId);
        $charity->setName($data['name']);
        $charity->setEin($data['ein'] ?? null);
        $charity->setAddress($data['address'] ?? null);
        $charity->setCity($data['city'] ?? null);
        $charity->setState($data['state'] ?? null);
        $charity->setZip($data['zip'] ?? null);
        $charity->setNotes($data['notes'] ?? null);
        $charity->setCreatedAt($now);
        $charity->setUpdatedAt($now);

        return $this->mapper->insert($charity);
    }

    public function update(int $id, string $userId, array $data): Charity {
        $charity = $this->mapper->findById($id, $userId);

        if (isset($data['name']))    { $charity->setName($data['name']); }
        if (array_key_exists('ein', $data))     { $charity->setEin($data['ein']); }
        if (array_key_exists('address', $data)) { $charity->setAddress($data['address']); }
        if (array_key_exists('city', $data))    { $charity->setCity($data['city']); }
        if (array_key_exists('state', $data))   { $charity->setState($data['state']); }
        if (array_key_exists('zip', $data))     { $charity->setZip($data['zip']); }
        if (array_key_exists('notes', $data))   { $charity->setNotes($data['notes']); }
        $charity->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        return $this->mapper->update($charity);
    }

    public function delete(int $id, string $userId): void {
        $charity = $this->mapper->findById($id, $userId);
        $this->mapper->delete($charity);
    }
}
