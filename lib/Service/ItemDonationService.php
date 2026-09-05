<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\CharityMapper;
use OCA\DeductibleLog\Db\ItemCategoryMapper;
use OCA\DeductibleLog\Db\ItemDonation;
use OCA\DeductibleLog\Db\ItemDonationLine;
use OCA\DeductibleLog\Db\ItemDonationLineMapper;
use OCA\DeductibleLog\Db\ItemDonationMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Migration\SeedData;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

class ItemDonationService {

    public function __construct(
        private ItemDonationMapper $mapper,
        private ItemDonationLineMapper $lineMapper,
        private CharityMapper $charityMapper,
        private ItemCategoryMapper $categoryMapper,
        private ReceiptMapper $receiptMapper,
        private IDBConnection $db,
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
        $now      = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $donation = new ItemDonation();
        $donation->setUserId($userId);
        $donation->setCreatedAt($now);

        $lines = $this->validate($donation, $userId, $data, true);
        $donation->setUpdatedAt($now);

        return $this->transactional(function () use ($donation, $lines) {
            $donation->setTotalValue('0.00');
            $donation = $this->mapper->insert($donation);
            [$saved, $total] = $this->writeLines($donation->getId(), $lines);
            $donation->setTotalValue($total);
            $donation = $this->mapper->update($donation);
            return array_merge($donation->jsonSerialize(), ['lines' => $saved]);
        });
    }

    public function update(int $id, string $userId, array $data): array {
        $donation = $this->mapper->findById($id, $userId);
        $merged   = Merge::forUpdate($donation->jsonSerialize(), $data);
        $replace  = array_key_exists('lines', $data);
        $lines    = $this->validate($donation, $userId, $merged, $replace);
        $donation->setUpdatedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        return $this->transactional(function () use ($donation, $lines, $replace) {
            if ($replace) {
                [$saved, $total] = $this->writeLines($donation->getId(), $lines);
                $donation->setTotalValue($total);
            } else {
                $existing = $this->lineMapper->findAllByDonation($donation->getId());
                $saved    = array_map(fn($l) => $l->jsonSerialize(), $existing);
                $donation->setTotalValue(Money::sum(...array_map(fn($l) => $l->getTotalValue(), $existing)));
            }
            $donation = $this->mapper->update($donation);
            return array_merge($donation->jsonSerialize(), ['lines' => $saved]);
        });
    }

    public function delete(int $id, string $userId): void {
        $donation = $this->mapper->findById($id, $userId);
        $this->transactional(function () use ($donation, $userId) {
            $this->lineMapper->deleteByDonation($donation->getId());
            $this->receiptMapper->deleteByEntity('item_donation', $donation->getId(), $userId);
            $this->mapper->delete($donation);
            return null;
        });
    }

    private function withLines(ItemDonation $donation): array {
        $lines = $this->lineMapper->findAllByDonation($donation->getId());
        return array_merge(
            $donation->jsonSerialize(),
            ['lines' => array_map(fn($l) => $l->jsonSerialize(), $lines)],
        );
    }

    /**
     * Validates header fields onto $donation and returns normalized line data
     * (when $withLines) ready for writeLines(). Throws 422 on any problem.
     *
     * @return array<int, array{category_id:int, description:string, quantity:int, condition:string, unit_value:string, fmv_source:?string}>
     */
    private function validate(ItemDonation $donation, string $userId, array $data, bool $withLines): array {
        $v         = new Validator();
        $charityId = $v->positiveInt($data['charity_id'] ?? null, 'charity_id');
        $date      = $v->date($data['date'] ?? null);
        $taxYear   = $v->taxYear($data['tax_year'] ?? null, $date);
        $notes     = $v->optionalString($data['notes'] ?? null, 'notes', 10000);

        if ($charityId !== null) {
            try {
                $this->charityMapper->findById($charityId, $userId);
            } catch (DoesNotExistException) {
                $v->fail('charity_id', 'Unknown charity');
            }
        }

        $lines = [];
        if ($withLines) {
            $raw = $data['lines'] ?? null;
            if (!is_array($raw) || $raw === []) {
                $v->fail('lines', 'At least one line item is required');
            } else {
                foreach (array_values($raw) as $i => $line) {
                    $lines[] = $this->validateLine($v, is_array($line) ? $line : [], $i);
                }
            }
        }
        $v->throwIfInvalid();

        $donation->setCharityId($charityId);
        $donation->setTaxYear($taxYear);
        $donation->setDate($date);
        $donation->setNotes($notes);
        return $lines;
    }

    private function validateLine(Validator $v, array $line, int $i): array {
        $f          = "lines[{$i}]";
        $categoryId = (int) ($line['item_category_id'] ?? 0);
        $quantity   = $v->positiveInt($line['quantity'] ?? 1, "{$f}.quantity");
        $condition  = $v->enum($line['condition'] ?? 'good', Validator::CONDITIONS, "{$f}.condition");
        $unitValue  = $v->amount($line['unit_value'] ?? null, "{$f}.unit_value");
        $desc       = $v->optionalString($line['description'] ?? null, "{$f}.description", 256);
        $fmvSource  = null;

        if ($categoryId < 0) {
            $v->fail("{$f}.item_category_id", 'item_category_id must be 0 or a catalog id');
        } elseif ($categoryId > 0) {
            try {
                $cat       = $this->categoryMapper->findById($categoryId);
                $desc      = $desc ?? $cat->getName();
                $fmvSource = SeedData::CATALOG_VERSION;
            } catch (DoesNotExistException) {
                $v->fail("{$f}.item_category_id", 'Unknown catalog item');
            }
        }
        if ($desc === null) {
            $v->fail("{$f}.description", 'description is required for an item not in the catalog');
        }

        return [
            'category_id' => $categoryId,
            'description' => $desc ?? '',
            'quantity'    => $quantity ?? 1,
            'condition'   => $condition ?? 'good',
            'unit_value'  => $unitValue ?? '0.00',
            'fmv_source'  => $fmvSource,
        ];
    }

    /**
     * Replace all lines of a donation. The header total is the sum of the line
     * totals actually written, computed in integer cents, so the two can never
     * disagree.
     *
     * @return array{0: array[], 1: string} [saved lines, total]
     */
    private function writeLines(int $donationId, array $lines): array {
        $this->lineMapper->deleteByDonation($donationId);
        $saved      = [];
        $totalCents = 0;
        foreach ($lines as $l) {
            $lineTotal = Money::lineTotal($l['unit_value'], $l['quantity']);
            $entity    = new ItemDonationLine();
            $entity->setDonationId($donationId);
            $entity->setItemCategoryId($l['category_id']);
            $entity->setDescription($l['description']);
            $entity->setQuantity($l['quantity']);
            $entity->setCondition($l['condition']);
            $entity->setUnitValue($l['unit_value']);
            $entity->setTotalValue($lineTotal);
            $entity->setFmvSource($l['fmv_source']);
            $saved[]     = $this->lineMapper->insert($entity)->jsonSerialize();
            $totalCents += Money::toCents($lineTotal);
        }
        return [$saved, Money::fromCents($totalCents)];
    }

    private function transactional(callable $fn): mixed {
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
