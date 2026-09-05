<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\SettingMapper;
use OCA\DeductibleLog\Db\TaxRate;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCA\DeductibleLog\Exception\ValidationException;
use OCA\DeductibleLog\Service\SettingsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {

    public function testNormalizeRate(): void {
        $this->assertSame('14.0', SettingsService::normalizeRate(14));
        $this->assertSame('14.0', SettingsService::normalizeRate('14'));
        $this->assertSame('14.0', SettingsService::normalizeRate(14.0));
        $this->assertSame('20.5', SettingsService::normalizeRate('20.5'));
        $this->assertNull(SettingsService::normalizeRate('abc'));
        $this->assertNull(SettingsService::normalizeRate(''));
        $this->assertNull(SettingsService::normalizeRate(null));
    }

    public function testApplyInsertsNewYearWithEveryColumnDirty(): void {
        $rates = $this->createMock(TaxRateMapper::class);
        $rates->method('findByYear')->willThrowException(new DoesNotExistException('x'));
        $rates->expects($this->once())->method('insert')->with($this->callback(function (TaxRate $r) {
            $fields = $r->getUpdatedFields();
            foreach (['taxYear', 'mileageCharitableCents', 'mileageMedicalCents', 'mileageBusinessCents', 'updatedAt', 'source'] as $f) {
                $this->assertArrayHasKey($f, $fields, "$f missing from INSERT");
            }
            $this->assertSame('14.0', $r->getMileageCharitableCents());
            $this->assertSame('21.0', $r->getMileageMedicalCents());
            return true;
        }))->willReturnArgument(0);

        $service = new SettingsService($this->createMock(SettingMapper::class), $rates, $this->createMock(IClientService::class));
        $service->applyUpdates('michael', [['year' => 2027, 'charitable' => '14.0', 'medical' => 21, 'business' => '73.5']]);
    }

    public function testApplyRejectsGarbageBeforeTouchingTheDb(): void {
        $rates = $this->createMock(TaxRateMapper::class);
        $rates->expects($this->never())->method('insert');
        $rates->expects($this->never())->method('update');
        $service = new SettingsService($this->createMock(SettingMapper::class), $rates, $this->createMock(IClientService::class));
        $this->expectException(ValidationException::class);
        $service->applyUpdates('michael', [['year' => 2027, 'charitable' => 'abc', 'medical' => -1, 'business' => '73.5']]);
    }
}
