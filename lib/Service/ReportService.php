<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

class ReportService {

    public function __construct(
        private CashDonationService    $cashService,
        private ItemDonationService    $itemService,
        private MileageService         $mileageService,
        private MedicalExpenseService  $medicalService,
        private BusinessExpenseService $businessService,
        private CharityService         $charityService,
        private FamilyMemberService    $familyService,
        private SettingsService        $settingsService,
    ) {}

    // ── Summary ──────────────────────────────────────────────────────────────

    public function summarize(string $userId, int $taxYear): array {
        $cashTotal     = $this->cashService->yearTotal($userId, $taxYear);
        $itemTotal     = $this->itemService->yearTotal($userId, $taxYear);
        $mileageTotals = $this->mileageService->yearTotals($userId, $taxYear);
        $medicalTotal  = $this->medicalService->yearTotal($userId, $taxYear);
        $businessTotal = $this->businessService->yearTotal($userId, $taxYear);

        $charitableTotal = number_format((float)$cashTotal + (float)$itemTotal, 2, '.', '');
        $grandTotal      = number_format(
            (float)$charitableTotal + (float)$mileageTotals['deduction'] + (float)$medicalTotal + (float)$businessTotal,
            2, '.', '',
        );

        return [
            'tax_year'          => $taxYear,
            'cash_donations'    => $cashTotal,
            'item_donations'    => $itemTotal,
            'charitable_total'  => $charitableTotal,
            'mileage_deduction' => $mileageTotals['deduction'],
            'mileage_miles'     => $mileageTotals['miles'],
            'medical_expenses'  => $medicalTotal,
            'business_expenses' => $businessTotal,
            'grand_total'       => $grandTotal,
        ];
    }

    // ── CSV ───────────────────────────────────────────────────────────────────

    public function csv(string $userId, int $taxYear): string {
        $charityMap = $this->buildCharityMap($userId);
        $memberMap  = $this->buildMemberMap($userId);

        $rows = [['Type', 'Tax Year', 'Date', 'Charity / Provider', 'Description', 'Category / Purpose', 'Family Member', 'Amount', 'Miles', 'Notes']];

        foreach ($this->cashService->findAll($userId, $taxYear) as $d) {
            $rows[] = [
                'Cash Donation',
                $taxYear,
                $d->getDate(),
                $charityMap[$d->getCharityId()] ?? '',
                '',
                '',
                '',
                $d->getAmount(),
                '',
                $d->getNotes() ?? '',
            ];
        }

        foreach ($this->itemService->findAll($userId, $taxYear) as $d) {
            $rows[] = [
                'Item Donation',
                $taxYear,
                $d['date'],
                $charityMap[$d['charity_id']] ?? '',
                '',
                '',
                '',
                $d['total_value'],
                '',
                $d['notes'] ?? '',
            ];
        }

        foreach ($this->mileageService->findAll($userId, $taxYear) as $log) {
            $rows[] = [
                'Mileage',
                $taxYear,
                $log->getDate(),
                '',
                $log->getDescription() ?? '',
                ucfirst($log->getPurposeType()),
                $memberMap[$log->getFamilyMemberId()] ?? '',
                $log->getDeductionAmount(),
                $log->getMiles(),
                '',
            ];
        }

        foreach ($this->medicalService->findAll($userId, $taxYear) as $exp) {
            $rows[] = [
                'Medical Expense',
                $taxYear,
                $exp->getDate(),
                $exp->getProvider() ?? '',
                '',
                $exp->getCategory() ?? '',
                $memberMap[$exp->getFamilyMemberId()] ?? '',
                $exp->getAmount(),
                '',
                $exp->getNotes() ?? '',
            ];
        }

        foreach ($this->businessService->findAll($userId, $taxYear) as $exp) {
            $rows[] = [
                'Business Expense',
                $taxYear,
                $exp->getDate(),
                '',
                $exp->getDescription(),
                $exp->getCategory() ?? '',
                $memberMap[$exp->getFamilyMemberId()] ?? '',
                $exp->getAmount(),
                '',
                $exp->getNotes() ?? '',
            ];
        }

        return $this->buildCsv($rows);
    }

    // ── TXF ───────────────────────────────────────────────────────────────────

    /**
     * Tax Exchange Format for TurboTax Desktop.
     * Scope: Schedule A charitable deductions only (cash + item donations).
     * Mileage / medical / business are entered manually in TurboTax.
     */
    public function txf(string $userId, int $taxYear): string {
        $cashTotal = (float) $this->cashService->yearTotal($userId, $taxYear);
        $itemTotal = (float) $this->itemService->yearTotal($userId, $taxYear);

        $lines   = [];
        $lines[] = 'V042';
        $lines[] = "A{$taxYear}";
        $lines[] = '^';

        $copy = 1;

        if ($cashTotal > 0.0) {
            $amt = number_format($cashTotal, 2, '.', '');
            $lines[] = 'TD';
            $lines[] = 'N334';
            $lines[] = "C{$copy}";
            $lines[] = 'L334';
            $lines[] = "\${$amt}";
            $lines[] = 'P Cash Donations';
            $lines[] = '^';
            $copy++;
        }

        if ($itemTotal > 0.0) {
            $amt = number_format($itemTotal, 2, '.', '');
            $lines[] = 'TD';
            $lines[] = 'N334';
            $lines[] = "C{$copy}";
            $lines[] = 'L334';
            $lines[] = "\${$amt}";
            $lines[] = 'P Non-Cash Donations';
            $lines[] = '^';
        }

        return implode("\n", $lines) . "\n";
    }

    // ── HTML ─────────────────────────────────────────────────────────────────

    public function html(string $userId, int $taxYear): string {
        $summary    = $this->summarize($userId, $taxYear);
        $settings   = $this->settingsService->get($userId);
        $charityMap = $this->buildCharityMap($userId);
        $memberMap  = $this->buildMemberMap($userId);
        $generated  = (new \DateTimeImmutable())->format('F j, Y g:i A');
        $household  = htmlspecialchars($settings['household_name'] ?? 'My Household');

        $cash     = $this->cashService->findAll($userId, $taxYear);
        $items    = $this->itemService->findAll($userId, $taxYear);
        $mileage  = $this->mileageService->findAll($userId, $taxYear);
        $medical  = $this->medicalService->findAll($userId, $taxYear);
        $business = $this->businessService->findAll($userId, $taxYear);

        $fmt = fn(string $v) => '$' . number_format((float) $v, 2);

        $html  = $this->htmlHead($household, $taxYear);
        $html .= "<body>\n";
        $html .= '<button class="print-btn" onclick="window.print()">&#128438; Print / Save as PDF</button>' . "\n";
        $html .= "<h1>{$household} &mdash; {$taxYear} Tax Deduction Report</h1>\n";
        $html .= "<p class=\"meta\">Generated {$generated}</p>\n";

        // Summary cards
        $html .= "<h2>Summary</h2>\n<div class=\"summary-grid\">\n";
        $html .= $this->summaryCard('Cash Donations',    $fmt($summary['cash_donations']));
        $html .= $this->summaryCard('Item Donations',    $fmt($summary['item_donations']));
        $html .= $this->summaryCard('Mileage Deduction', $fmt($summary['mileage_deduction']) . ' (' . number_format((float)$summary['mileage_miles'], 1) . ' mi)');
        $html .= $this->summaryCard('Medical Expenses',  $fmt($summary['medical_expenses']));
        $html .= $this->summaryCard('Business Expenses', $fmt($summary['business_expenses']));
        $html .= $this->summaryCard('Charitable Total',  $fmt($summary['charitable_total']), 'accent');
        $html .= "</div>\n";
        $html .= '<div class="grand-total">Grand Total: ' . $fmt($summary['grand_total']) . "</div>\n";

        // Cash donations
        if (!empty($cash)) {
            $html .= "<h2>Cash Donations</h2>\n";
            $html .= "<table>\n<thead><tr><th>Date</th><th>Charity</th><th>Payment</th><th class=\"amt\">Amount</th></tr></thead>\n<tbody>\n";
            foreach ($cash as $d) {
                $html .= '<tr><td>' . h($d->getDate()) . '</td><td>' . h($charityMap[$d->getCharityId()] ?? '') . '</td><td>' . h($d->getPaymentMethod() ?? '—') . '</td><td class="amt">' . $fmt($d->getAmount()) . "</td></tr>\n";
            }
            $html .= '</tbody><tfoot><tr><td colspan="3"><strong>Total</strong></td><td class="amt"><strong>' . $fmt($summary['cash_donations']) . "</strong></td></tr></tfoot>\n</table>\n";
        }

        // Item donations
        if (!empty($items)) {
            $html .= "<h2>Item Donations</h2>\n";
            $html .= "<table>\n<thead><tr><th>Date</th><th>Charity</th><th>Items</th><th class=\"amt\">Value</th></tr></thead>\n<tbody>\n";
            foreach ($items as $d) {
                $itemDesc = implode(', ', array_map(fn($l) => $l['description'] ?: ('Item #' . $l['id']), $d['lines'] ?? []));
                $html .= '<tr><td>' . h($d['date']) . '</td><td>' . h($charityMap[$d['charity_id']] ?? '') . '</td><td>' . h($itemDesc ?: '—') . '</td><td class="amt">' . $fmt($d['total_value']) . "</td></tr>\n";
            }
            $html .= '</tbody><tfoot><tr><td colspan="3"><strong>Total</strong></td><td class="amt"><strong>' . $fmt($summary['item_donations']) . "</strong></td></tr></tfoot>\n</table>\n";
        }

        // Mileage
        if (!empty($mileage)) {
            $html .= "<h2>Mileage Logs</h2>\n";
            $html .= "<table>\n<thead><tr><th>Date</th><th>Purpose</th><th>Description</th><th>Who</th><th class=\"amt\">Miles</th><th class=\"amt\">Deduction</th></tr></thead>\n<tbody>\n";
            foreach ($mileage as $log) {
                $html .= '<tr><td>' . h($log->getDate()) . '</td><td>' . h(ucfirst($log->getPurposeType())) . '</td><td>' . h($log->getDescription() ?? '—') . '</td><td>' . h($memberMap[$log->getFamilyMemberId()] ?? '—') . '</td><td class="amt">' . number_format((float)$log->getMiles(), 1) . '</td><td class="amt">' . $fmt($log->getDeductionAmount()) . "</td></tr>\n";
            }
            $html .= '</tbody><tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="amt"><strong>' . number_format((float)$summary['mileage_miles'], 1) . '</strong></td><td class="amt"><strong>' . $fmt($summary['mileage_deduction']) . "</strong></td></tr></tfoot>\n</table>\n";
        }

        // Medical
        if (!empty($medical)) {
            $html .= "<h2>Medical Expenses</h2>\n";
            $html .= "<table>\n<thead><tr><th>Date</th><th>Provider</th><th>Category</th><th>Who</th><th class=\"amt\">Amount</th></tr></thead>\n<tbody>\n";
            foreach ($medical as $exp) {
                $html .= '<tr><td>' . h($exp->getDate()) . '</td><td>' . h($exp->getProvider() ?? '—') . '</td><td>' . h($exp->getCategory() ?? '—') . '</td><td>' . h($memberMap[$exp->getFamilyMemberId()] ?? '—') . '</td><td class="amt">' . $fmt($exp->getAmount()) . "</td></tr>\n";
            }
            $html .= '</tbody><tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="amt"><strong>' . $fmt($summary['medical_expenses']) . "</strong></td></tr></tfoot>\n</table>\n";
        }

        // Business
        if (!empty($business)) {
            $html .= "<h2>Business Expenses</h2>\n";
            $html .= "<table>\n<thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Who</th><th class=\"amt\">Amount</th></tr></thead>\n<tbody>\n";
            foreach ($business as $exp) {
                $html .= '<tr><td>' . h($exp->getDate()) . '</td><td>' . h($exp->getDescription()) . '</td><td>' . h($exp->getCategory() ?? '—') . '</td><td>' . h($memberMap[$exp->getFamilyMemberId()] ?? '—') . '</td><td class="amt">' . $fmt($exp->getAmount()) . "</td></tr>\n";
            }
            $html .= '</tbody><tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="amt"><strong>' . $fmt($summary['business_expenses']) . "</strong></td></tr></tfoot>\n</table>\n";
        }

        $html .= "</body>\n</html>\n";
        return $html;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildCharityMap(string $userId): array {
        $map = [];
        foreach ($this->charityService->findAll($userId) as $c) {
            $map[$c->getId()] = $c->getName();
        }
        return $map;
    }

    private function buildMemberMap(string $userId): array {
        $map = [];
        foreach ($this->familyService->findAll($userId) as $m) {
            $map[$m->getId()] = $m->getName();
        }
        return $map;
    }

    private function buildCsv(array $rows): string {
        $out = '';
        foreach ($rows as $row) {
            $out .= implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', (string) $cell) . '"', $row)) . "\r\n";
        }
        return $out;
    }

    private function summaryCard(string $label, string $value, string $extra = ''): string {
        $cls = $extra ? " class=\"summary-card {$extra}\"" : ' class="summary-card"';
        return "<div{$cls}><div class=\"summary-label\">" . htmlspecialchars($label) . '</div><div class="summary-amount">' . htmlspecialchars($value) . "</div></div>\n";
    }

    private function htmlHead(string $household, int $taxYear): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$household} — {$taxYear} Tax Deduction Report</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: system-ui, -apple-system, sans-serif; font-size: 14px; color: #1a1a1a; max-width: 900px; margin: 0 auto; padding: 2rem; }
  h1 { font-size: 1.6rem; border-bottom: 2px solid #333; padding-bottom: 0.5rem; margin-bottom: 0.25rem; }
  h2 { font-size: 1.1rem; color: #444; margin-top: 2rem; margin-bottom: 0.5rem; border-left: 3px solid #2563eb; padding-left: 0.5rem; }
  .meta { color: #888; font-size: 0.85rem; margin: 0 0 1.5rem; }
  .print-btn { float: right; padding: 0.4rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; }
  .print-btn:hover { background: #1d4ed8; }
  .summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
  .summary-card { border: 1px solid #ddd; padding: 0.75rem 1rem; border-radius: 6px; background: #fafafa; }
  .summary-card.accent { border-color: #2563eb; background: #eff6ff; }
  .summary-label { font-size: 0.78rem; color: #666; margin-bottom: 0.2rem; }
  .summary-amount { font-size: 1.15rem; font-weight: 700; color: #1d4ed8; }
  .grand-total { font-size: 1.4rem; font-weight: 800; color: #059669; margin: 0.5rem 0 2rem; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
  th, td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid #e5e7eb; }
  th { background: #f3f4f6; font-weight: 600; font-size: 0.82rem; color: #555; }
  .amt { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  tfoot td { font-weight: 700; border-top: 2px solid #ccc; border-bottom: none; background: #f9fafb; }
  @media print {
    .print-btn { display: none; }
    body { padding: 0.5rem; max-width: none; }
    h2 { break-after: avoid; }
    tr { break-inside: avoid; }
    table { break-inside: auto; }
  }
</style>
</head>

HTML;
    }
}

// Module-level HTML escape helper (avoids polluting global namespace issues in NC)
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
