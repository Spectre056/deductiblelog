<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\ItemDonation;
use OCA\DeductibleLog\Db\ItemDonationLine;
use OCA\DeductibleLog\Db\ItemDonationLineMapper;
use OCA\DeductibleLog\Db\ItemDonationMapper;

class ItemDonationService {

    public function __construct(
        private ItemDonationMapper $mapper,
        private ItemDonationLineMapper $lineMapper,
    ) {}

    /** @return array[] Donations with embedded lines */
    public function findAll(string $userId, int $taxYear): array {
        $donations = $this->mapper->findAllByYear($userId, $taxYear);
        return array_map(fn($d) => $this->withLines($d), $donations);
    }

    public function yearTotal(string $userId, int $taxYear): string {
        return $this->mapper->sumByYear($userId, $taxYear);
    }

    public function create(string $userId, array $data): array {
        $now   = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $lines = $data['lines'] ?? [];
        $total = $this->calcTotal($lines);

        $donation = new ItemDonation();
        $donation->setUserId($userId);
        $donation->setCharityId((int) $data['charity_id']);
        $donation->setTaxYear((int) $data['tax_year']);
        $donation->setDate($data['date']);
        $donation->setNotes($data['notes'] ?? null);
        $donation->setTotalValue($total);
        $donation->setCreatedAt($now);
        $donation->setUpdatedAt($now);

        $donation   = $this->mapper->insert($donation);
        $savedLines = $this->replaceLines($donation->getId(), $lines);

        return array_merge($donation->jsonSerialize(), ['lines' => $savedLines]);
    }

    public function update(int $id, string $userId, array $data): array {
        $donation = $this->mapper->findById($id, $userId);
        $lines    = $data['lines'] ?? null;

        if (isset($data['charity_id']))       { $donation->setCharityId((int) $data['charity_id']); }
        if (isset($data['tax_year']))         { $donation->setTaxYear((int) $data['tax_year']); }
        if (isset($data['date']))             { $donation->setDate($data['date']); }
        if (array_key_exists('notes', $data)) { $donation->setNotes($data['notes']); }

        if ($lines !== null) {
            $donation->setTotalValue($this->calcTotal($lines));
            $savedLines = $this->replaceLines($donation->getId(), $lines);
        } else {
            $savedLines = array_map(
                fn($l) => $l->jsonSerialize(),
                $this->lineMapper->findAllByDonation($donation->getId()),
            );
        }

        $donation->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $donation = $this->mapper->update($donation);

        return array_merge($donation->jsonSerialize(), ['lines' => $savedLines]);
    }

    public function delete(int $id, string $userId): void {
        $donation = $this->mapper->findById($id, $userId);
        $this->lineMapper->deleteByDonation($donation->getId());
        $this->mapper->delete($donation);
    }

    private function withLines(ItemDonation $donation): array {
        $lines = $this->lineMapper->findAllByDonation($donation->getId());
        return array_merge(
            $donation->jsonSerialize(),
            ['lines' => array_map(fn($l) => $l->jsonSerialize(), $lines)],
        );
    }

    private function calcTotal(array $lines): string {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (int) ($line['quantity'] ?? 1) * (float) ($line['unit_value'] ?? 0);
        }
        return number_format($total, 2, '.', '');
    }

    private function replaceLines(int $donationId, array $linesData): array {
        $this->lineMapper->deleteByDonation($donationId);
        $saved = [];
        foreach ($linesData as $lineData) {
            $qty     = max(1, (int) ($lineData['quantity'] ?? 1));
            $unitVal = number_format((float) ($lineData['unit_value'] ?? 0), 2, '.', '');

            $line = new ItemDonationLine();
            $line->setDonationId($donationId);
            $line->setItemCategoryId((int) ($lineData['item_category_id'] ?? 0));
            $line->setDescription(trim($lineData['description'] ?? ''));
            $line->setQuantity($qty);
            $line->setCondition($lineData['condition'] ?? 'good');
            $line->setUnitValue($unitVal);
            $line->setTotalValue(number_format((float) $unitVal * $qty, 2, '.', ''));
            $saved[] = $this->lineMapper->insert($line)->jsonSerialize();
        }
        return $saved;
    }
}
