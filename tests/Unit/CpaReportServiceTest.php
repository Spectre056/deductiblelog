<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Tests\Unit;

use OCA\DeductibleLog\Db\CashDonation;
use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Db\FamilyMember;
use OCA\DeductibleLog\Db\MedicalExpense;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCA\DeductibleLog\Service\BusinessExpenseService;
use OCA\DeductibleLog\Service\CashDonationService;
use OCA\DeductibleLog\Service\CharityService;
use OCA\DeductibleLog\Service\CpaReportService;
use OCA\DeductibleLog\Service\FamilyMemberService;
use OCA\DeductibleLog\Service\ItemDonationService;
use OCA\DeductibleLog\Service\MedicalExpenseService;
use OCA\DeductibleLog\Service\MileageService;
use OCA\DeductibleLog\Service\ReportService;
use OCA\DeductibleLog\Service\SettingsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDateTimeZone;
use PHPUnit\Framework\TestCase;

class CpaReportServiceTest extends TestCase {

    private CpaReportService $service;

    protected function setUp(): void {
        $cash = $this->createMock(CashDonationService::class);
        $c1 = new CashDonation(); $c1->setId(1); $c1->setCharityId(3); $c1->setDate('2026-02-14'); $c1->setAmount('1000.00'); $c1->setAcknowledged(1);
        $c2 = new CashDonation(); $c2->setId(2); $c2->setCharityId(3); $c2->setDate('2026-03-01'); $c2->setAmount('300.00'); $c2->setAcknowledged(0);
        $c3 = new CashDonation(); $c3->setId(3); $c3->setCharityId(4); $c3->setDate('2026-01-01'); $c3->setAmount('25.00');
        $cash->method('findAll')->willReturn([$c1, $c2, $c3]);
        $cash->method('yearTotal')->willReturn('1325.00');

        $items = $this->createMock(ItemDonationService::class);
        $line = fn($desc, $qty, $unit, $extra = []) => array_merge([
            'description' => $desc, 'quantity' => $qty, 'unit_value' => $unit, 'total_value' => number_format($qty * (float) $unit, 2, '.', ''),
            'condition' => 'good', 'date_acquired' => null, 'how_acquired' => null, 'cost_basis' => null, 'fmv_method' => 'thrift_shop_value',
        ], $extra);
        $items->method('findAll')->willReturn([[
            'id' => 4, 'charity_id' => 5, 'date' => '2026-09-05', 'total_value' => '840.00', 'notes' => null, 'acknowledged' => true,
            'lines' => [
                $line('Action Figure / Doll', 100, '5.00'),
                $line('action figure / doll', 65, '5.00', ['condition' => 'excellent']),
                $line('Bath Towel', 8, '2.00', ['condition' => 'poor']),
            ],
        ]]);
        $items->method('yearTotal')->willReturn('841.00');

        $mileage = $this->createMock(MileageService::class);
        $bp = ['charitable' => ['deduction' => '0.00', 'miles' => '0.0'], 'medical' => ['deduction' => '0.00', 'miles' => '0.0'], 'business' => ['deduction' => '0.00', 'miles' => '0.0']];
        $mileage->method('yearTotals')->willReturn(['deduction' => '0.00', 'miles' => '0.0', 'by_purpose' => $bp]);
        $mileage->method('byPurpose')->willReturn($bp);
        $mileage->method('findAll')->willReturn([]);

        $medical = $this->createMock(MedicalExpenseService::class);
        $m1 = new MedicalExpense(); $m1->setId(9); $m1->setFamilyMemberId(2); $m1->setDate('2026-05-01'); $m1->setAmount('100.00'); $m1->setReimbursedAmount('40.00'); $m1->setCategory('Doctor'); $m1->setProvider('MUSC');
        $m2 = new MedicalExpense(); $m2->setId(10); $m2->setFamilyMemberId(2); $m2->setDate('2026-06-01'); $m2->setAmount('40.00'); $m2->setCategory('Insurance Premium');
        $medical->method('findAll')->willReturn([$m1, $m2]);
        $medical->method('yearTotal')->willReturn('100.00');
        $business = $this->createMock(BusinessExpenseService::class);
        $business->method('findAll')->willReturn([]);
        $business->method('yearTotal')->willReturn('0.00');

        $charities = $this->createMock(CharityService::class);
        $ch3 = new Charity(); $ch3->setId(3); $ch3->setName("St Paul's"); $ch3->setEin('12-3456789'); $ch3->setAddress('1 Main St'); $ch3->setCity('Charleston'); $ch3->setState('SC'); $ch3->setZip('29401');
        $ch4 = new Charity(); $ch4->setId(4); $ch4->setName('Tiny <Fund>');
        $ch5 = new Charity(); $ch5->setId(5); $ch5->setName('Palmetto Goodwill');
        $charities->method('findAll')->willReturn([$ch3, $ch4, $ch5]);
        $family = $this->createMock(FamilyMemberService::class);
        $fm = new FamilyMember(); $fm->setId(2); $fm->setName('Allison'); $fm->setRelationship('spouse');
        $family->method('findAll')->willReturn([$fm]);
        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturn(['household_name' => 'Eckard Family']);
        $receipts = $this->createMock(ReceiptMapper::class);
        $receipts->method('findAllByUser')->willReturn([]);
        $rates = $this->createMock(TaxRateMapper::class);
        $rates->method('findByYear')->willThrowException(new DoesNotExistException('x'));
        $tz = $this->createMock(IDateTimeZone::class);
        $tz->method('getTimeZone')->willReturn(new \DateTimeZone('America/New_York'));

        $apps = $this->createMock(\OCP\App\IAppManager::class);
        $report = new ReportService($cash, $items, $mileage, $medical, $business, $charities, $family, $settings, $apps, $tz);
        $this->service = new CpaReportService($cash, $items, $mileage, $medical, $business, $charities, $family, $settings, $report, $receipts, $rates, $tz);
    }

    public function testSimilarItemsAreGroupedCaseInsensitivelyAndThresholdsApplied(): void {
        $d = $this->service->collect('michael', 2026);
        $this->assertCount(2, $d['groups']);
        $dolls = reset($d['groups']);
        $this->assertSame('Action Figure / Doll', $dolls['description']);
        $this->assertSame(165, $dolls['quantity']);
        $this->assertSame('825.00', $dolls['fmv']);
        $this->assertTrue($dolls['needs_detail']);
        $this->assertTrue($dolls['missing_detail']);
        $this->assertFalse($dolls['needs_appraisal']);
        $towels = end($d['groups']);
        $this->assertFalse($towels['needs_detail']);
        $this->assertTrue($towels['poor']);
        $this->assertSame(1, $d['poor_lines']);
        $this->assertTrue($d['form_8283']);
    }

    public function testAcknowledgmentChecksAndMedicalBreakdown(): void {
        $d = $this->service->collect('michael', 2026);
        $this->assertSame(3, $d['cwa_required']);
        $this->assertCount(1, $d['cwa_missing']);
        $this->assertSame('300.00', $d['cwa_missing'][0]['amount']);
        $this->assertSame(['Doctor' => '60.00', 'Insurance Premium' => '40.00'], $d['med_by_cat']);
        $this->assertSame(['Allison' => '100.00'], $d['med_by_who']);
        $this->assertTrue($d['any_reimbursed']);
        $this->assertSame(['count' => 2, 'total' => '1300.00'], $d['cash_by_charity'][3]);
    }

    public function testHtmlRendersSectionsAndEscapes(): void {
        $html = $this->service->html('michael', 2026);
        $this->assertStringContainsString('Deduction Workpapers', $html);
        $this->assertStringContainsString('Preparer checklist', $html);
        $this->assertStringContainsString('over $500: columns (e)(f)(g) required', $html);
        $this->assertStringContainsString('Tiny &lt;Fund&gt;', $html);
        $this->assertStringNotContainsString('<Fund>', $html);
        $this->assertStringContainsString('1 Main St, Charleston, SC 29401', $html);
        $this->assertStringContainsString('12-3456789', $html);
        $this->assertStringContainsString('1 NOT yet acknowledged', $html);
        $this->assertStringContainsString('Insurance Premium', $html);
        $this->assertStringContainsString('No files attached in the app', $html);
        $this->assertStringContainsString('includes "poor" condition', $html);
    }
}
