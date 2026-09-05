<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\BusinessExpenseMapper;
use OCA\DeductibleLog\Db\FamilyMember;
use OCA\DeductibleLog\Db\FamilyMemberMapper;
use OCA\DeductibleLog\Db\MedicalExpenseMapper;
use OCA\DeductibleLog\Db\MileageLogMapper;
use OCA\DeductibleLog\Exception\ConflictException;

class FamilyMemberService {

    public function __construct(
        private FamilyMemberMapper $mapper,
        private MileageLogMapper $mileageMapper,
        private MedicalExpenseMapper $medicalMapper,
        private BusinessExpenseMapper $businessMapper,
    ) {}

    /** @return FamilyMember[] */
    public function findAll(string $userId): array {
        return $this->mapper->findAll($userId);
    }

    public function create(string $userId, array $data): FamilyMember {
        $member = new FamilyMember();
        $member->setUserId($userId);
        $member->setCreatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->apply($member, $data);
        return $this->mapper->insert($member);
    }

    public function update(int $id, string $userId, array $data): FamilyMember {
        $member = $this->mapper->findById($id, $userId);
        $this->apply($member, array_merge($member->jsonSerialize(), $data));
        return $this->mapper->update($member);
    }

    public function delete(int $id, string $userId): void {
        $member   = $this->mapper->findById($id, $userId);
        $mileage  = $this->mileageMapper->countWhere('family_member_id', $member->getId(), $userId);
        $medical  = $this->medicalMapper->countWhere('family_member_id', $member->getId(), $userId);
        $business = $this->businessMapper->countWhere('family_member_id', $member->getId(), $userId);
        if ($mileage + $medical + $business > 0) {
            throw new ConflictException(
                sprintf('%s is referenced by %d mileage, %d medical and %d business record(s). Reassign those first.', $member->getName(), $mileage, $medical, $business),
                ['references' => ['mileage' => $mileage, 'medical' => $medical, 'business' => $business]],
            );
        }
        $this->mapper->delete($member);
    }

    private function apply(FamilyMember $member, array $data): void {
        $v            = new Validator();
        $name         = $v->requiredString($data['name'] ?? null, 'name', 128);
        $relationship = $v->enum($data['relationship'] ?? null, Validator::RELATIONSHIPS, 'relationship');
        $v->throwIfInvalid();
        $member->setName($name);
        $member->setRelationship($relationship);
    }
}
