<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\CashDonation;
use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Service\BusinessExpenseService;
use OCA\DeductibleLog\Service\CashDonationService;
use OCA\DeductibleLog\Service\CharityService;
use OCA\DeductibleLog\Service\FamilyMemberService;
use OCA\DeductibleLog\Service\ItemDonationService;
use OCA\DeductibleLog\Service\MedicalExpenseService;
use OCA\DeductibleLog\Service\MileageService;
use OCA\DeductibleLog\Service\ReportService;
use OCA\DeductibleLog\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IDateTimeZone;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase {

    private ReportService $service;

    protected function setUp(): void {
        $cash = $this->createMock(CashDonationService::class);
        $cash->method('yearTotal')->willReturn('1000.00');
        $d = new CashDonation();
        $d->setId(1); $d->setCharityId(3); $d->setDate('2026-02-14'); $d->setAmount('1000.00'); $d->setNotes("=HYPERLINK(\"http://x\")\nline2");
        $cash->method('findAll')->willReturn([$d]);

        $items = $this->createMock(ItemDonationService::class);
        $items->method('yearTotal')->willReturn('1686.50');
        $items->method('findAll')->willReturn([[
            'id' => 2, 'charity_id' => 99, 'date' => '2026-04-05', 'total_value' => '1686.50', 'notes' => null,
            'lines' => [['id' => 1, 'description' => 'Jeans^Pants', 'quantity' => 3, 'unit_value' => '2.56', 'total_value' => '7.68', 'condition' => 'good', 'date_acquired' => '2021-06', 'how_acquired' => 'purchase', 'cost_basis' => '45.00', 'fmv_method' => 'thrift_shop_value']],
        ]]);

        $mileage = $this->createMock(MileageService::class);
        $byPurpose = [
            'charitable' => ['deduction' => '14.00', 'miles' => '100.0'],
            'medical'    => ['deduction' => '20.50', 'miles' => '100.0'],
            'business'   => ['deduction' => '72.50', 'miles' => '100.0'],
        ];
        $mileage->method('yearTotals')->willReturn(['deduction' => '107.00', 'miles' => '300.0', 'by_purpose' => $byPurpose]);
        $mileage->method('byPurpose')->willReturn($byPurpose);
        $mileage->method('findAll')->willReturn([]);

        $medical = $this->createMock(MedicalExpenseService::class);
        $medical->method('yearTotal')->willReturn('250.10');
        $medical->method('findAll')->willReturn([]);
        $business = $this->createMock(BusinessExpenseService::class);
        $business->method('yearTotal')->willReturn('0.20');
        $business->method('findAll')->willReturn([]);

        $charities = $this->createMock(CharityService::class);
        $c = new Charity(); $c->setId(3); $c->setName('Goodwill of SC');
        $charities->method('findAll')->willReturn([$c]);
        $family = $this->createMock(FamilyMemberService::class);
        $family->method('findAll')->willReturn([]);
        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturn(['household_name' => 'Eckard <Household>']);
        $apps = $this->createMock(IAppManager::class);
        $apps->method('getAppVersion')->willReturn('0.2.0');

        $tz = $this->createMock(IDateTimeZone::class);
        $tz->method('getTimeZone')->willReturn(new \DateTimeZone('America/New_York'));
        $this->service = new ReportService($cash, $items, $mileage, $medical, $business, $charities, $family, $settings, $apps, $tz);
    }

    public function testSummaryMapsMileageOntoReturnLines(): void {
        $s = $this->service->summarize('michael', 2026);
        $this->assertSame('2686.50', $s['charitable_total']);
        $this->assertSame('1014.00', $s['schedule_a_cash']);
        $this->assertSame('1686.50', $s['schedule_a_noncash']);
        $this->assertSame('270.60', $s['medical_with_mileage']);
        $this->assertSame('72.70', $s['schedule_c']);
        $this->assertSame('3043.80', $s['grand_total']);
        $this->assertSame(2, $s['acknowledgment_missing']);
        $this->assertTrue($s['form_8283_required']);
    }

    public function testTxfGolden(): void {
        $txf   = $this->service->txf('michael', 2026);
        $lines = explode("\n", trim($txf));
        $this->assertSame('V042', $lines[0]);
        $this->assertSame('ADeductibleLog 0.2.0', $lines[1]);
        $this->assertMatchesRegularExpression('#^D \d{2}/\d{2}/\d{4}$#', $lines[2]);
        $this->assertSame('^', $lines[3]);
        $this->assertSame(['TD', 'N280', 'C1', 'L1', '$1000.00', 'X02/14/2026 - Goodwill of SC', 'PGoodwill of SC', '^'], array_slice($lines, 4, 8));
        $this->assertSame(['TD', 'N485', 'C2', 'L1', '$1686.50', 'X04/05/2026 - Unknown charity (deleted)', 'PUnknown charity (deleted)', '^'], array_slice($lines, 12, 8));
    }

    public function testCsvDefusesFormulasAndEscapesQuotes(): void {
        $csv = $this->service->csv('michael', 2026);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://x"")' . "\n" . 'line2"', $csv);
        $this->assertStringContainsString('"Mileage Subtotal","2026","","","","Charitable","","14.00","100.0",""', $csv);
        $this->assertStringContainsString('"Item Donation Line","2026","2026-04-05","","Jeans^Pants","","","7.68","","","","","3","2.56","good","2021-06","purchase","45.00","thrift_shop_value"', $csv);
        $this->assertSame("'-5", ReportService::csvSafe('-5'));
        $this->assertSame('5', ReportService::csvSafe('5'));
        $this->assertSame('', ReportService::csvSafe(''));
    }

    public function testHtmlEscapesUserContent(): void {
        $html = $this->service->html('michael', 2026);
        $this->assertStringContainsString('Eckard &lt;Household&gt;', $html);
        $this->assertStringNotContainsString('<Household>', $html);
        $this->assertStringContainsString('Jeans^Pants', $html);
        $this->assertStringContainsString('Schedule A: cash gifts incl. 100.0 charitable mi', $html);
        $this->assertStringContainsString('$1,014.00', $html);
        $this->assertStringContainsString('Form 8283 Section A', $html);
        $this->assertStringContainsString('Purchase 2021-06', $html);
        $this->assertStringContainsString('2 contribution(s) of $250 or more', $html);
    }
}
