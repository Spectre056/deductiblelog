<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\FamilyMember;
use OCA\DeductibleLog\Db\FamilyMemberMapper;

class FamilyMemberService {

    public function __construct(private FamilyMemberMapper $mapper) {}

    /** @return FamilyMember[] */
    public function findAll(string $userId): array {
        return $this->mapper->findAll($userId);
    }

    public function create(string $userId, array $data): FamilyMember {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $member = new FamilyMember();
        $member->setUserId($userId);
        $member->setName($data['name']);
        $member->setRelationship($data['relationship']);
        $member->setCreatedAt($now);

        return $this->mapper->insert($member);
    }

    public function update(int $id, string $userId, array $data): FamilyMember {
        $member = $this->mapper->findById($id, $userId);

        if (isset($data['name']))         { $member->setName($data['name']); }
        if (isset($data['relationship'])) { $member->setRelationship($data['relationship']); }

        return $this->mapper->update($member);
    }

    public function delete(int $id, string $userId): void {
        $member = $this->mapper->findById($id, $userId);
        $this->mapper->delete($member);
    }
}
