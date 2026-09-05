<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Db\CharityMapper;
use OCA\DeductibleLog\Db\ItemCategory;
use OCA\DeductibleLog\Db\ItemCategoryMapper;
use OCA\DeductibleLog\Db\ItemDonation;
use OCA\DeductibleLog\Db\ItemDonationLine;
use OCA\DeductibleLog\Db\ItemDonationLineMapper;
use OCA\DeductibleLog\Db\ItemDonationMapper;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Migration\SeedData;
use OCA\DeductibleLog\Service\ItemDonationService;
use OCA\DeductibleLog\Service\Money;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class ItemDonationServiceTest extends TestCase {

    private ItemDonationMapper $mapper;
    private ItemDonationLineMapper $lines;
    private IDBConnection $db;
    private ItemDonationService $service;
    /** @var ItemDonationLine[] */
    private array $inserted = [];

    protected function setUp(): void {
        $this->inserted = [];
        $this->mapper = $this->createMock(ItemDonationMapper::class);
        $this->mapper->method('insert')->willReturnCallback(function (ItemDonation $d) {
            $d->setId(42);
            return $d;
        });
        $this->mapper->method('update')->willReturnArgument(0);

        $this->lines = $this->createMock(ItemDonationLineMapper::class);
        $this->lines->method('insert')->willReturnCallback(function (ItemDonationLine $l) {
            $l->setId(count($this->inserted) + 1);
            $this->inserted[] = $l;
            return $l;
        });

        $charities = $this->createMock(CharityMapper::class);
        $charities->method('findById')->willReturnCallback(function (int $id, string $uid) {
            if ($id !== 7) {
                throw new DoesNotExistException('nope');
            }
            return new Charity();
        });
        $categories = $this->createMock(ItemCategoryMapper::class);
        $categories->method('findById')->willReturnCallback(function (int $id) {
            if ($id !== 100) {
                throw new DoesNotExistException('nope');
            }
            $c = new ItemCategory();
            $c->setName("Men's Jeans");
            return $c;
        });

        $this->db = $this->createMock(IDBConnection::class);
        $this->service = new ItemDonationService(
            $this->mapper, $this->lines, $charities, $categories, $this->createMock(ReceiptMapper::class), $this->db,
        );
    }

    public function testHeaderTotalAlwaysEqualsSumOfWrittenLines(): void {
        $this->db->expects($this->once())->method('beginTransaction');
        $this->db->expects($this->once())->method('commit');
        $this->db->expects($this->never())->method('rollBack');

        $result = $this->service->create('michael', [
            'charity_id' => 7, 'date' => '2026-04-05',
            'lines' => [
                ['item_category_id' => 100, 'quantity' => 3, 'condition' => 'good', 'unit_value' => '2.56'],
                ['item_category_id' => 0, 'description' => 'Lamp', 'quantity' => 1, 'condition' => 'excellent', 'unit_value' => '10'],
                ['item_category_id' => 0, 'description' => 'Odd', 'quantity' => 7, 'condition' => 'poor', 'unit_value' => '0.10'],
            ],
        ]);

        $sum = Money::sum(...array_map(fn($l) => $l->getTotalValue(), $this->inserted));
        $this->assertSame('18.38', $result['total_value']);
        $this->assertSame($sum, $result['total_value']);
        $this->assertCount(3, $result['lines']);
        $this->assertSame("Men's Jeans", $this->inserted[0]->getDescription());
        $this->assertSame(SeedData::CATALOG_VERSION, $this->inserted[0]->getFmvSource());
        $this->assertNull($this->inserted[1]->getFmvSource());
        $this->assertSame(2026, $result['tax_year']);
    }

    public function testInvalidQuantityOrThreeDecimalValueIsRejectedNotSilentlyNormalized(): void {
        foreach ([
            ['item_category_id' => 0, 'description' => 'x', 'quantity' => 0, 'unit_value' => '5'],
            ['item_category_id' => 0, 'description' => 'x', 'quantity' => -2, 'unit_value' => '5'],
            ['item_category_id' => 0, 'description' => 'x', 'quantity' => 1, 'unit_value' => '2.555'],
            ['item_category_id' => 0, 'description' => 'x', 'quantity' => 1, 'unit_value' => '0'],
            ['item_category_id' => 0, 'quantity' => 1, 'unit_value' => '5'],
            ['item_category_id' => 999, 'quantity' => 1, 'unit_value' => '5'],
            ['item_category_id' => 0, 'description' => 'x', 'quantity' => 1, 'condition' => 'mint', 'unit_value' => '5'],
        ] as $line) {
            try {
                $this->service->create('michael', ['charity_id' => 7, 'date' => '2026-04-05', 'lines' => [$line]]);
                $this->fail('expected 422 for ' . json_encode($line));
            } catch (ValidationException $e) {
                $this->assertStringStartsWith('lines[0]', array_key_first($e->getDetails()['errors']));
            }
        }
        $this->assertSame([], $this->inserted);
    }

    public function testFailureInsideTheTransactionRollsBack(): void {
        $this->lines->method('deleteByDonation')->willThrowException(new \RuntimeException('db down'));
        $this->db->expects($this->once())->method('rollBack');
        $this->db->expects($this->never())->method('commit');
        $this->expectException(\RuntimeException::class);
        $this->service->create('michael', [
            'charity_id' => 7, 'date' => '2026-04-05',
            'lines' => [['item_category_id' => 0, 'description' => 'x', 'quantity' => 1, 'unit_value' => '5']],
        ]);
    }

    public function testUnknownCharityAndMissingLinesAreRejected(): void {
        try {
            $this->service->create('michael', ['charity_id' => 8, 'date' => '2026-04-05', 'lines' => []]);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertSame(['charity_id', 'lines'], array_keys($e->getDetails()['errors']));
        }
    }

    public function testUpdateWithoutLinesRecomputesHeaderFromStoredLines(): void {
        $stored = new ItemDonation();
        $stored->setId(42);
        $stored->setUserId('michael');
        $stored->setCharityId(7);
        $stored->setTaxYear(2026);
        $stored->setDate('2026-04-05');
        $stored->setTotalValue('999.99');
        $stored->setCreatedAt('x');
        $stored->setUpdatedAt('x');
        $stored->resetUpdatedFields();
        $this->mapper->method('findById')->willReturn($stored);
        $a = new ItemDonationLine(); $a->setTotalValue('7.68');
        $b = new ItemDonationLine(); $b->setTotalValue('10.00');
        $this->lines->method('findAllByDonation')->willReturn([$a, $b]);

        $result = $this->service->update(42, 'michael', ['notes' => 'edited']);
        $this->assertSame('17.68', $result['total_value']);
        $this->assertSame('edited', $result['notes']);
    }
}
