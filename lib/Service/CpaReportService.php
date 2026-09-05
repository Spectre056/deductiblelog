<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Service;

use OCA\DeductibleLog\Db\Charity;
use OCA\DeductibleLog\Db\ReceiptMapper;
use OCA\DeductibleLog\Db\TaxRateMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDateTimeZone;

/**
 * Consolidated preparer-facing report: one document, ordered the way the
 * return is built (Schedule A cash, Schedule A non-cash / Form 8283, medical,
 * Schedule C), with the compliance checks a preparer would otherwise have to
 * do by hand and every figure traceable to a dated record.
 */
class CpaReportService {

    private const CWA_THRESHOLD      = '250.00';   // written acknowledgment, IRC §170(f)(8)
    private const FORM_8283_TOTAL    = '500.00';   // Section A required above this (annual non-cash)
    private const GROUP_DETAIL       = '500.00';   // columns (e)(f)(g) required for a similar-items group above this
    private const APPRAISAL          = '5000.00';  // Section B + qualified appraisal above this per item/group

    public function __construct(
        private CashDonationService    $cashService,
        private ItemDonationService    $itemService,
        private MileageService         $mileageService,
        private MedicalExpenseService  $medicalService,
        private BusinessExpenseService $businessService,
        private CharityService         $charityService,
        private FamilyMemberService    $familyService,
        private SettingsService        $settingsService,
        private ReportService          $reportService,
        private ReceiptMapper          $receiptMapper,
        private TaxRateMapper          $taxRateMapper,
        private IDateTimeZone          $timeZone,
    ) {}

    public function html(string $userId, int $taxYear): string {
        $d = $this->collect($userId, $taxYear);
        $h = fn(?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $m = fn(string|int|float|null $v) => '$' . number_format((float) ($v ?? 0), 2);

        $out  = $this->head($d);
        $out .= $this->sectionCover($d, $h, $m);
        $out .= $this->sectionChecklist($d, $h, $m);
        $out .= $this->sectionCash($d, $h, $m);
        $out .= $this->sectionNonCash($d, $h, $m);
        $out .= $this->sectionMedical($d, $h, $m);
        $out .= $this->sectionMileage($d, $h, $m);
        $out .= $this->sectionBusiness($d, $h, $m);
        $out .= $this->sectionAppendix($d, $h, $m);
        $out .= "</body>\n</html>\n";
        return $out;
    }

    // ── Data ────────────────────────────────────────────────────────────────

    /** Everything the sections need, computed once. */
    public function collect(string $userId, int $taxYear): array {
        $summary  = $this->reportService->summarize($userId, $taxYear);
        $settings = $this->settingsService->get($userId);

        $charities = [];
        foreach ($this->charityService->findAll($userId) as $c) {
            $charities[$c->getId()] = $c;
        }
        $members = [];
        foreach ($this->familyService->findAll($userId) as $fm) {
            $members[$fm->getId()] = $fm;
        }

        $cash     = $this->cashService->findAll($userId, $taxYear);
        $items    = $this->itemService->findAll($userId, $taxYear);
        $mileage  = $this->mileageService->findAll($userId, $taxYear);
        $medical  = $this->medicalService->findAll($userId, $taxYear);
        $business = $this->businessService->findAll($userId, $taxYear);

        $receipts = [];
        foreach ($this->receiptMapper->findAllByUser($userId) as $r) {
            $receipts[$r->getEntityType()][$r->getEntityId()][] = $r->getOriginalFilename();
        }

        try {
            $rates = $this->taxRateMapper->findByYear($taxYear)->jsonSerialize();
        } catch (DoesNotExistException) {
            $rates = null;
        }

        // Cash by charity
        $cashByCharity = [];
        foreach ($cash as $c) {
            $id = $c->getCharityId();
            $cashByCharity[$id]['count'] = ($cashByCharity[$id]['count'] ?? 0) + 1;
            $cashByCharity[$id]['total'] = Money::sum($cashByCharity[$id]['total'] ?? '0', $c->getAmount());
        }
        uasort($cashByCharity, fn($a, $b) => Money::toCents($b['total']) <=> Money::toCents($a['total']));

        // Non-cash: similar-items groups (Form 8283 treats similar items as one)
        $groups = [];
        foreach ($items as $don) {
            foreach ($don['lines'] ?? [] as $l) {
                $key = mb_strtolower(trim((string) $l['description']));
                $g   = &$groups[$key];
                $g['description'] = $g['description'] ?? $l['description'];
                $g['quantity']    = ($g['quantity'] ?? 0) + (int) $l['quantity'];
                $g['fmv']         = Money::sum($g['fmv'] ?? '0', $l['total_value']);
                $g['cost_basis']  = $l['cost_basis'] !== null && $l['cost_basis'] !== '' ? Money::sum($g['cost_basis'] ?? '0', $l['cost_basis']) : ($g['cost_basis'] ?? null);
                $g['acquired'][$l['date_acquired'] ?? '']  = true;
                $g['how'][$l['how_acquired'] ?? '']        = true;
                $g['method'][$l['fmv_method'] ?? '']       = true;
                $g['conditions'][$l['condition'] ?? '']    = true;
                $g['lines']       = ($g['lines'] ?? 0) + 1;
                $g['charities'][$don['charity_id']] = true;
                unset($g);
            }
        }
        foreach ($groups as &$g) {
            $g['needs_detail']  = Money::toCents($g['fmv']) > Money::toCents(self::GROUP_DETAIL);
            $g['needs_appraisal'] = Money::toCents($g['fmv']) > Money::toCents(self::APPRAISAL);
            $g['missing_detail'] = $g['needs_detail'] && (isset($g['acquired']['']) || isset($g['how']['']) || $g['cost_basis'] === null);
            $g['poor'] = isset($g['conditions']['poor']);
        }
        unset($g);
        uasort($groups, fn($a, $b) => Money::toCents($b['fmv']) <=> Money::toCents($a['fmv']));

        // Medical by category and by person
        $medByCat = [];
        $medByWho = [];
        foreach ($medical as $e) {
            $cat = $e->getCategory() ?: 'Uncategorized';
            $who = $e->getFamilyMemberId() !== null ? ($members[$e->getFamilyMemberId()]?->getName() ?? 'Unknown') : 'Unassigned';
            $medByCat[$cat] = Money::sum($medByCat[$cat] ?? '0', $e->getDeductibleAmount());
            $medByWho[$who] = Money::sum($medByWho[$who] ?? '0', $e->getDeductibleAmount());
        }
        arsort($medByCat);
        arsort($medByWho);

        $bizByCat = [];
        foreach ($business as $e) {
            $cat = $e->getCategory() ?: 'Uncategorized';
            $bizByCat[$cat] = Money::sum($bizByCat[$cat] ?? '0', $e->getAmount());
        }
        arsort($bizByCat);

        // Compliance checks
        $cwa = Money::toCents(self::CWA_THRESHOLD);
        $cwaMissing = [];
        foreach ($cash as $c) {
            if ($c->getAcknowledged() !== 1 && Money::toCents($c->getAmount()) >= $cwa) {
                $cwaMissing[] = ['date' => $c->getDate(), 'charity' => $charities[$c->getCharityId()]?->getName() ?? 'Unknown', 'amount' => $c->getAmount()];
            }
        }
        foreach ($items as $don) {
            if (empty($don['acknowledged']) && Money::toCents($don['total_value']) >= $cwa) {
                $cwaMissing[] = ['date' => $don['date'], 'charity' => $charities[$don['charity_id']]?->getName() ?? 'Unknown', 'amount' => $don['total_value']];
            }
        }
        $cwaRequired = 0;
        foreach ($cash as $c) { if (Money::toCents($c->getAmount()) >= $cwa) $cwaRequired++; }
        foreach ($items as $don) { if (Money::toCents($don['total_value']) >= $cwa) $cwaRequired++; }

        $poorLines = 0;
        foreach ($items as $don) {
            foreach ($don['lines'] ?? [] as $l) { if (($l['condition'] ?? '') === 'poor') $poorLines++; }
        }

        $tz = $this->timeZone->getTimeZone();
        $generated = (new \DateTimeImmutable('now', $tz))->format('F j, Y g:i A T');

        return [
            'tax_year'      => $taxYear,
            'household'     => $settings['household_name'] ?? 'My Household',
            'generated'     => $generated,
            'summary'       => $summary,
            'charities'     => $charities,
            'members'       => $members,
            'cash'          => $cash,
            'cash_by_charity' => $cashByCharity,
            'items'         => $items,
            'groups'        => $groups,
            'mileage'       => $mileage,
            'rates'         => $rates,
            'medical'       => $medical,
            'med_by_cat'    => $medByCat,
            'med_by_who'    => $medByWho,
            'business'      => $business,
            'biz_by_cat'    => $bizByCat,
            'receipts'      => $receipts,
            'cwa_required'  => $cwaRequired,
            'cwa_missing'   => $cwaMissing,
            'form_8283'     => Money::toCents($summary['item_donations']) > Money::toCents(self::FORM_8283_TOTAL),
            'poor_lines'    => $poorLines,
            'any_reimbursed' => array_reduce($medical, fn($carry, $e) => $carry || Money::toCents($e->getReimbursedAmount()) > 0, false),
        ];
    }

    // ── Sections ────────────────────────────────────────────────────────────

    private function sectionCover(array $d, callable $h, callable $m): string {
        $s  = "<body>\n<button class=\"print-btn\" onclick=\"window.print()\">&#128438; Print / Save as PDF</button>\n";
        $s .= '<h1>' . $h($d['household']) . ' &mdash; Tax Year ' . $d['tax_year'] . ' Deduction Workpapers</h1>' . "\n";
        $s .= '<p class="meta">Prepared for the return preparer. Generated ' . $h($d['generated']) . ' from DeductibleLog. Amounts are as recorded by the taxpayer; source documents are listed in the appendix.</p>' . "\n";

        $s .= "<h2>Taxpayer household</h2>\n<table class=\"compact\"><thead><tr><th>Name</th><th>Relationship</th></tr></thead><tbody>\n";
        foreach ($d['members'] as $fm) {
            $s .= '<tr><td>' . $h($fm->getName()) . '</td><td>' . $h(ucfirst($fm->getRelationship())) . "</td></tr>\n";
        }
        if ($d['members'] === []) {
            $s .= "<tr><td colspan=\"2\">No family members recorded</td></tr>\n";
        }
        $s .= "</tbody></table>\n";

        $sum = $d['summary'];
        $bp  = $sum['mileage_by_purpose'];
        $s .= "<h2>Return-line summary</h2>\n";
        $s .= "<table><thead><tr><th>Where it goes</th><th>What it is</th><th class=\"amt\">Amount</th></tr></thead><tbody>\n";
        $s .= '<tr><td>Schedule A, gifts by cash or check</td><td>' . count($d['cash']) . ' cash gift(s)' . ((float) $bp['charitable']['miles'] > 0 ? ' + ' . number_format((float) $bp['charitable']['miles'], 1) . ' charitable miles at ' . $h((string) ($d['rates']['charitable'] ?? '?')) . '¢' : '') . '</td><td class="amt"><strong>' . $m($sum['schedule_a_cash']) . "</strong></td></tr>\n";
        $s .= '<tr><td>Schedule A, gifts other than cash (Form 8283)</td><td>' . count($d['items']) . ' donation(s), ' . array_sum(array_map(fn($x) => count($x['lines'] ?? []), $d['items'])) . ' line item(s), ' . count($d['groups']) . ' similar-item group(s)</td><td class="amt"><strong>' . $m($sum['schedule_a_noncash']) . "</strong></td></tr>\n";
        $s .= '<tr><td>Schedule A, medical and dental (before the 7.5% AGI floor)</td><td>' . count($d['medical']) . ' expense(s)' . ((float) $bp['medical']['miles'] > 0 ? ' + ' . number_format((float) $bp['medical']['miles'], 1) . ' medical miles' : '') . ($d['any_reimbursed'] ? ', net of reimbursements' : '') . '</td><td class="amt"><strong>' . $m($sum['medical_with_mileage']) . "</strong></td></tr>\n";
        $s .= '<tr><td>Schedule C, business expenses</td><td>' . count($d['business']) . ' expense(s)' . ((float) $bp['business']['miles'] > 0 ? ' + ' . number_format((float) $bp['business']['miles'], 1) . ' business miles' : '') . '</td><td class="amt"><strong>' . $m($sum['schedule_c']) . "</strong></td></tr>\n";
        $s .= "</tbody></table>\n";
        $s .= '<p class="note">Cash, non-cash, medical and business figures are independent totals and are not summed here: they land on different lines and are subject to different limits.</p>' . "\n";
        return $s;
    }

    private function sectionChecklist(array $d, callable $h, callable $m): string {
        $rows = [];
        $ok   = fn(bool $b) => $b ? '<span class="ok">&#10003;</span>' : '<span class="flag">&#9888;</span>';

        $rows[] = [$ok($d['cwa_missing'] === []), 'Contemporaneous written acknowledgment for every single gift of $250 or more',
            $d['cwa_required'] . ' gift(s) at or above $250; ' . ($d['cwa_missing'] === [] ? 'all marked as acknowledged by the taxpayer.' : count($d['cwa_missing']) . ' NOT yet acknowledged, listed below.')];
        $rows[] = [$d['form_8283'] ? '<span class="info">&#8505;</span>' : $ok(true), 'Form 8283 Section A (non-cash gifts over $500 for the year)',
            $d['form_8283'] ? 'Required: non-cash total is ' . $m($d['summary']['item_donations']) . '. Similar-item groups and per-line detail are in the non-cash section.' : 'Not required: non-cash total is ' . $m($d['summary']['item_donations']) . '.'];
        $detailGroups = array_filter($d['groups'], fn($g) => $g['needs_detail']);
        $missing      = array_filter($detailGroups, fn($g) => $g['missing_detail']);
        $rows[] = [$ok($missing === []), 'Form 8283 columns (e) date acquired, (f) how acquired, (g) cost basis for any similar-item group over $500',
            count($detailGroups) . ' group(s) over $500' . ($detailGroups !== [] ? ': ' . implode(', ', array_map(fn($g) => $h($g['description']) . ' (' . $m($g['fmv']) . ')', $detailGroups)) : '') . '. ' . ($missing === [] ? 'Detail is complete.' : count($missing) . ' group(s) still missing acquisition detail.')];
        $appraisal = array_filter($d['groups'], fn($g) => $g['needs_appraisal']);
        $rows[] = [$ok($appraisal === []), 'Qualified appraisal and Form 8283 Section B for any item or similar-item group over $5,000',
            $appraisal === [] ? 'None: largest group is ' . ($d['groups'] !== [] ? $m(reset($d['groups'])['fmv']) : '$0.00') . '.' : 'REQUIRED for: ' . implode(', ', array_map(fn($g) => $h($g['description']), $appraisal))];
        $rows[] = [$ok($d['poor_lines'] === 0), 'Clothing and household items must be in good used condition or better',
            $d['poor_lines'] === 0 ? 'No lines recorded as "poor".' : $d['poor_lines'] . ' line(s) recorded as "poor" condition; not deductible unless over $500 with a qualified appraisal.'];
        $receiptCount = array_sum(array_map(fn($byId) => array_sum(array_map('count', $byId)), $d['receipts']));
        $rows[] = ['<span class="info">&#8505;</span>', 'Source documents attached in the app', $receiptCount . ' file(s) attached across all records (see appendix). Paper or PDF receipts, acknowledgment letters and EOBs held outside the app are not reflected here.'];

        $s = "<h2>Preparer checklist</h2>\n<table><thead><tr><th></th><th>Requirement</th><th>Status</th></tr></thead><tbody>\n";
        foreach ($rows as [$mark, $req, $status]) {
            $s .= '<tr><td class="mark">' . $mark . '</td><td>' . $req . '</td><td>' . $status . "</td></tr>\n";
        }
        $s .= "</tbody></table>\n";
        if ($d['cwa_missing'] !== []) {
            $s .= "<table class=\"compact\"><thead><tr><th>Date</th><th>Charity</th><th class=\"amt\">Amount</th><th>Acknowledgment</th></tr></thead><tbody>\n";
            foreach ($d['cwa_missing'] as $r) {
                $s .= '<tr><td>' . $h($r['date']) . '</td><td>' . $h($r['charity']) . '</td><td class="amt">' . $m($r['amount']) . '</td><td class="flag">not on file</td></tr>' . "\n";
            }
            $s .= "</tbody></table>\n";
        }
        $s .= '<p class="note">Questions the preparer may still need answered by the taxpayer: whether any gift went to a donor-advised fund; whether any gift returned goods or services (quid pro quo) and the acknowledgment states their value; whether any medical amount was paid with pre-tax dollars (HSA, FSA, payroll premiums), which would make it non-deductible.</p>' . "\n";
        return $s;
    }

    private function sectionCash(array $d, callable $h, callable $m): string {
        $s = "<h2 class=\"page\">Schedule A &mdash; Gifts by cash or check</h2>\n";
        if ($d['cash'] === []) {
            return $s . "<p class=\"note\">No cash gifts recorded.</p>\n";
        }
        $s .= "<h3>By donee</h3>\n<table><thead><tr><th>Donee</th><th>EIN</th><th>Address</th><th class=\"amt\">Gifts</th><th class=\"amt\">Total</th></tr></thead><tbody>\n";
        foreach ($d['cash_by_charity'] as $cid => $agg) {
            $c = $d['charities'][$cid] ?? null;
            $s .= '<tr><td>' . $h($c?->getName() ?? 'Unknown charity (deleted)') . '</td><td>' . $h($c?->getEin() ?? '—') . '</td><td>' . $h($this->address($c)) . '</td><td class="amt">' . $agg['count'] . '</td><td class="amt">' . $m($agg['total']) . "</td></tr>\n";
        }
        $s .= '<tr class="total"><td colspan="4">Total cash gifts</td><td class="amt">' . $m($d['summary']['cash_donations']) . "</td></tr>\n</tbody></table>\n";

        $s .= "<h3>Detail, chronological</h3>\n<table><thead><tr><th>Date</th><th>Donee</th><th>Method</th><th class=\"amt\">Amount</th><th>Ack.</th><th>Docs</th><th>Notes</th></tr></thead><tbody>\n";
        $rows = $d['cash'];
        usort($rows, fn($a, $b) => strcmp($a->getDate(), $b->getDate()));
        foreach ($rows as $c) {
            $big = Money::toCents($c->getAmount()) >= Money::toCents(self::CWA_THRESHOLD);
            $ack = $big ? ($c->getAcknowledged() === 1 ? '<span class="ok">yes</span>' : '<span class="flag">missing</span>') : 'n/a';
            $docs = count($d['receipts']['cash_donation'][$c->getId()] ?? []);
            $s .= '<tr><td>' . $h($c->getDate()) . '</td><td>' . $h($d['charities'][$c->getCharityId()]?->getName() ?? 'Unknown charity (deleted)') . '</td><td>' . $h($c->getPaymentMethod() ?? '—') . '</td><td class="amt">' . $m($c->getAmount()) . '</td><td>' . $ack . '</td><td class="amt">' . ($docs ?: '—') . '</td><td class="notes">' . $h($c->getNotes() ?? '') . "</td></tr>\n";
        }
        $s .= "</tbody></table>\n";
        $s .= '<p class="note">"Ack." is required only for single gifts of $250 or more. Every cash gift needs a bank record or a written communication from the donee regardless of amount.</p>' . "\n";
        return $s;
    }

    private function sectionNonCash(array $d, callable $h, callable $m): string {
        $s = "<h2 class=\"page\">Schedule A &mdash; Gifts other than cash (Form 8283 Section A)</h2>\n";
        if ($d['items'] === []) {
            return $s . "<p class=\"note\">No non-cash gifts recorded.</p>\n";
        }
        $s .= "<h3>Donations</h3>\n<table><thead><tr><th>Date</th><th>Donee</th><th>EIN</th><th>Address</th><th class=\"amt\">Lines</th><th class=\"amt\">FMV</th><th>Ack.</th><th>Docs</th></tr></thead><tbody>\n";
        foreach ($d['items'] as $don) {
            $c    = $d['charities'][$don['charity_id']] ?? null;
            $ack  = !empty($don['acknowledged']) ? '<span class="ok">yes</span>' : '<span class="flag">missing</span>';
            $docs = count($d['receipts']['item_donation'][$don['id']] ?? []);
            $s .= '<tr><td>' . $h($don['date']) . '</td><td>' . $h($c?->getName() ?? 'Unknown charity (deleted)') . '</td><td>' . $h($c?->getEin() ?? '—') . '</td><td>' . $h($this->address($c)) . '</td><td class="amt">' . count($don['lines'] ?? []) . '</td><td class="amt">' . $m($don['total_value']) . '</td><td>' . $ack . '</td><td class="amt">' . ($docs ?: '—') . "</td></tr>\n";
        }
        $s .= '<tr class="total"><td colspan="5">Total non-cash gifts</td><td class="amt">' . $m($d['summary']['item_donations']) . "</td><td colspan=\"2\"></td></tr>\n</tbody></table>\n";

        $s .= "<h3>Similar-item groups (Form 8283 treats similar items as one entry)</h3>\n";
        $s .= "<table><thead><tr><th>Group</th><th class=\"amt\">Qty</th><th>Condition</th><th>(e) Acquired</th><th>(f) How</th><th class=\"amt\">(g) Cost basis</th><th class=\"amt\">(h) FMV</th><th>(i) Method</th><th>Status</th></tr></thead><tbody>\n";
        foreach ($d['groups'] as $g) {
            $status = '';
            if ($g['needs_appraisal'])      { $status = '<span class="flag">over $5,000: appraisal + Section B</span>'; }
            elseif ($g['missing_detail'])   { $status = '<span class="flag">over $500: columns (e)(f)(g) required</span>'; }
            elseif ($g['needs_detail'])     { $status = '<span class="ok">over $500, detail complete</span>'; }
            if ($g['poor'])                 { $status .= ($status ? '<br>' : '') . '<span class="flag">includes "poor" condition</span>'; }
            $s .= '<tr><td>' . $h($g['description']) . '</td><td class="amt">' . $g['quantity'] . '</td><td>' . $h($this->joinKeys($g['conditions'])) . '</td><td>' . $h($this->joinKeys($g['acquired'])) . '</td><td>' . $h($this->joinKeys($g['how'])) . '</td><td class="amt">' . ($g['cost_basis'] !== null ? $m($g['cost_basis']) : '—') . '</td><td class="amt">' . $m($g['fmv']) . '</td><td>' . $h($this->joinKeys($g['method'], fn($k) => ReportService::fmvMethodLabel($k ?: null))) . '</td><td>' . ($status ?: '—') . "</td></tr>\n";
        }
        $s .= "</tbody></table>\n";
        $s .= '<p class="note">Columns (e), (f) and (g) are only required where the deduction for the item or group of similar items exceeds $500. Thrift-shop value refers to the Salvation Army valuation guide; catalog-priced lines carry the guide edition on the record.</p>' . "\n";

        $s .= "<h3>Line detail by donation</h3>\n";
        foreach ($d['items'] as $don) {
            $c = $d['charities'][$don['charity_id']] ?? null;
            $s .= '<h4>' . $h($don['date']) . ' &mdash; ' . $h($c?->getName() ?? 'Unknown charity (deleted)') . ' &mdash; ' . $m($don['total_value']) . ($don['notes'] ? ' <span class="notes">(' . $h($don['notes']) . ')</span>' : '') . "</h4>\n";
            $s .= "<table class=\"compact\"><thead><tr><th>Item</th><th>Cond.</th><th class=\"amt\">Qty</th><th class=\"amt\">Unit FMV</th><th class=\"amt\">Line FMV</th><th>Acquired</th><th>How</th><th class=\"amt\">Cost basis</th><th>Method</th></tr></thead><tbody>\n";
            foreach ($don['lines'] ?? [] as $l) {
                $s .= '<tr><td>' . $h($l['description'] ?: '—') . '</td><td>' . $h(ucfirst((string) $l['condition'])) . '</td><td class="amt">' . (int) $l['quantity'] . '</td><td class="amt">' . $m($l['unit_value']) . '</td><td class="amt">' . $m($l['total_value']) . '</td><td>' . $h($l['date_acquired'] ?? '—') . '</td><td>' . $h($l['how_acquired'] ? ucfirst($l['how_acquired']) : '—') . '</td><td class="amt">' . ($l['cost_basis'] !== null && $l['cost_basis'] !== '' ? $m($l['cost_basis']) : '—') . '</td><td>' . $h(ReportService::fmvMethodLabel($l['fmv_method'] ?? null)) . "</td></tr>\n";
            }
            $s .= "</tbody></table>\n";
        }
        return $s;
    }

    private function sectionMedical(array $d, callable $h, callable $m): string {
        $s = "<h2 class=\"page\">Schedule A &mdash; Medical and dental expenses</h2>\n";
        if ($d['medical'] === []) {
            return $s . "<p class=\"note\">No medical expenses recorded.</p>\n";
        }
        $s .= '<p class="note">Amounts are what the household paid out of pocket in ' . $d['tax_year'] . ' (cash basis)' . ($d['any_reimbursed'] ? ', net of reimbursements recorded per expense' : '') . '. The 7.5% of AGI floor has not been applied. Insurance premiums are shown separately because premiums paid with pre-tax dollars are not deductible.</p>' . "\n";

        $s .= "<div class=\"two-col\">\n<div><h3>By category</h3>\n<table class=\"compact\"><tbody>\n";
        foreach ($d['med_by_cat'] as $cat => $total) {
            $s .= '<tr><td>' . $h((string) $cat) . '</td><td class="amt">' . $m($total) . "</td></tr>\n";
        }
        $s .= '<tr class="total"><td>Total</td><td class="amt">' . $m($d['summary']['medical_expenses']) . "</td></tr>\n</tbody></table></div>\n";
        $s .= "<div><h3>By person</h3>\n<table class=\"compact\"><tbody>\n";
        foreach ($d['med_by_who'] as $who => $total) {
            $s .= '<tr><td>' . $h((string) $who) . '</td><td class="amt">' . $m($total) . "</td></tr>\n";
        }
        $s .= "</tbody></table></div>\n</div>\n";

        $bp = $d['summary']['mileage_by_purpose']['medical'];
        if ((float) $bp['miles'] > 0) {
            $s .= '<p class="note">Plus medical mileage: ' . number_format((float) $bp['miles'], 1) . ' miles = ' . $m($bp['deduction']) . ' (detail in the mileage section). Medical total including mileage: <strong>' . $m($d['summary']['medical_with_mileage']) . '</strong>.</p>' . "\n";
        }

        $s .= "<h3>Detail, chronological</h3>\n<table><thead><tr><th>Date</th><th>Provider</th><th>Category</th><th>Person</th><th class=\"amt\">Paid</th>" . ($d['any_reimbursed'] ? '<th class="amt">Reimbursed</th><th class="amt">Deductible</th>' : '') . "<th>Docs</th><th>Notes</th></tr></thead><tbody>\n";
        $rows = $d['medical'];
        usort($rows, fn($a, $b) => strcmp($a->getDate(), $b->getDate()));
        foreach ($rows as $e) {
            $docs = count($d['receipts']['medical'][$e->getId()] ?? []);
            $who  = $e->getFamilyMemberId() !== null ? ($d['members'][$e->getFamilyMemberId()]?->getName() ?? '—') : '—';
            $s .= '<tr><td>' . $h($e->getDate()) . '</td><td>' . $h($e->getProvider() ?? '—') . '</td><td>' . $h($e->getCategory() ?? '—') . '</td><td>' . $h($who) . '</td><td class="amt">' . $m($e->getAmount()) . '</td>' . ($d['any_reimbursed'] ? '<td class="amt">' . $m($e->getReimbursedAmount()) . '</td><td class="amt">' . $m($e->getDeductibleAmount()) . '</td>' : '') . '<td class="amt">' . ($docs ?: '—') . '</td><td class="notes">' . $h($e->getNotes() ?? '') . "</td></tr>\n";
        }
        $s .= '<tr class="total"><td colspan="4">Total deductible</td><td class="amt">' . $m($d['summary']['medical_expenses']) . '</td><td colspan="' . ($d['any_reimbursed'] ? 4 : 2) . '"></td></tr>' . "\n</tbody></table>\n";
        return $s;
    }

    private function sectionMileage(array $d, callable $h, callable $m): string {
        $s = "<h2>Standard mileage</h2>\n";
        $bp = $d['summary']['mileage_by_purpose'];
        $r  = $d['rates'];
        $s .= "<table class=\"compact\"><thead><tr><th>Purpose</th><th>Goes to</th><th class=\"amt\">Miles</th><th class=\"amt\">IRS rate</th><th class=\"amt\">Deduction</th></tr></thead><tbody>\n";
        foreach ([['charitable', 'Schedule A cash gifts'], ['medical', 'Schedule A medical'], ['business', 'Schedule C']] as [$p, $where]) {
            $s .= '<tr><td>' . ucfirst($p) . '</td><td>' . $where . '</td><td class="amt">' . number_format((float) $bp[$p]['miles'], 1) . '</td><td class="amt">' . $h($r !== null ? $r[$p] . '¢' : 'n/a') . '</td><td class="amt">' . $m($bp[$p]['deduction']) . "</td></tr>\n";
        }
        $s .= "</tbody></table>\n";
        if ($d['mileage'] === []) {
            return $s . "<p class=\"note\">No trips logged for " . $d['tax_year'] . ".</p>\n";
        }
        $s .= "<table><thead><tr><th>Date</th><th>Purpose</th><th>Description</th><th>Person</th><th class=\"amt\">Miles</th><th class=\"amt\">Rate</th><th class=\"amt\">Deduction</th></tr></thead><tbody>\n";
        $rows = $d['mileage'];
        usort($rows, fn($a, $b) => strcmp($a->getDate(), $b->getDate()));
        foreach ($rows as $l) {
            $who = $l->getFamilyMemberId() !== null ? ($d['members'][$l->getFamilyMemberId()]?->getName() ?? '—') : '—';
            $s .= '<tr><td>' . $h($l->getDate()) . '</td><td>' . $h(ucfirst($l->getPurposeType())) . '</td><td>' . $h($l->getDescription() ?? '—') . '</td><td>' . $h($who) . '</td><td class="amt">' . number_format((float) $l->getMiles(), 1) . '</td><td class="amt">' . $h($l->getRateCents()) . '¢</td><td class="amt">' . $m($l->getDeductionAmount()) . "</td></tr>\n";
        }
        $s .= "</tbody></table>\n";
        $s .= '<p class="note">The per-trip deduction is miles × the IRS standard rate in effect for the year, rounded to the cent per trip. Preparers who compute from total miles may see a difference of a few cents.</p>' . "\n";
        return $s;
    }

    private function sectionBusiness(array $d, callable $h, callable $m): string {
        $s = "<h2>Schedule C &mdash; Business expenses</h2>\n";
        if ($d['business'] === [] && (float) $d['summary']['mileage_by_purpose']['business']['miles'] <= 0) {
            return $s . "<p class=\"note\">No business expenses or business mileage recorded.</p>\n";
        }
        if ($d['biz_by_cat'] !== []) {
            $s .= "<h3>By category</h3>\n<table class=\"compact\"><tbody>\n";
            foreach ($d['biz_by_cat'] as $cat => $total) {
                $s .= '<tr><td>' . $h((string) $cat) . '</td><td class="amt">' . $m($total) . "</td></tr>\n";
            }
            $s .= '<tr class="total"><td>Total expenses</td><td class="amt">' . $m($d['summary']['business_expenses']) . "</td></tr>\n</tbody></table>\n";
        }
        $bp = $d['summary']['mileage_by_purpose']['business'];
        if ((float) $bp['miles'] > 0) {
            $s .= '<p class="note">Plus business mileage: ' . number_format((float) $bp['miles'], 1) . ' miles = ' . $m($bp['deduction']) . '. Schedule C total including mileage: <strong>' . $m($d['summary']['schedule_c']) . '</strong>.</p>' . "\n";
        }
        if ($d['business'] !== []) {
            $s .= "<h3>Detail, chronological</h3>\n<table><thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Owner</th><th class=\"amt\">Amount</th><th>Docs</th><th>Notes</th></tr></thead><tbody>\n";
            $rows = $d['business'];
            usort($rows, fn($a, $b) => strcmp($a->getDate(), $b->getDate()));
            foreach ($rows as $e) {
                $docs = count($d['receipts']['business'][$e->getId()] ?? []);
                $who  = $e->getFamilyMemberId() !== null ? ($d['members'][$e->getFamilyMemberId()]?->getName() ?? '—') : '—';
                $s .= '<tr><td>' . $h($e->getDate()) . '</td><td>' . $h($e->getDescription()) . '</td><td>' . $h($e->getCategory() ?? '—') . '</td><td>' . $h($who) . '</td><td class="amt">' . $m($e->getAmount()) . '</td><td class="amt">' . ($docs ?: '—') . '</td><td class="notes">' . $h($e->getNotes() ?? '') . "</td></tr>\n";
            }
            $s .= "</tbody></table>\n";
        }
        return $s;
    }

    private function sectionAppendix(array $d, callable $h, callable $m): string {
        $s = "<h2 class=\"page\">Appendix</h2>\n<h3>Donee directory</h3>\n";
        $s .= "<table class=\"compact\"><thead><tr><th>Charity</th><th>EIN</th><th>Address</th><th>Notes</th></tr></thead><tbody>\n";
        $used = [];
        foreach ($d['cash'] as $c) { $used[$c->getCharityId()] = true; }
        foreach ($d['items'] as $don) { $used[$don['charity_id']] = true; }
        foreach ($d['charities'] as $id => $c) {
            if (!isset($used[$id])) { continue; }
            $s .= '<tr><td>' . $h($c->getName()) . '</td><td>' . $h($c->getEin() ?? '—') . '</td><td>' . $h($this->address($c)) . '</td><td class="notes">' . $h($c->getNotes() ?? '') . "</td></tr>\n";
        }
        $s .= "</tbody></table>\n";

        $s .= "<h3>Attached source documents</h3>\n";
        $labels = ['cash_donation' => 'Cash gift', 'item_donation' => 'Non-cash gift', 'mileage' => 'Mileage', 'medical' => 'Medical', 'business' => 'Business'];
        $any = false;
        $s .= "<table class=\"compact\"><thead><tr><th>Record</th><th>ID</th><th>File(s)</th></tr></thead><tbody>\n";
        foreach ($labels as $type => $label) {
            foreach ($d['receipts'][$type] ?? [] as $id => $files) {
                $any = true;
                $s .= '<tr><td>' . $label . '</td><td>#' . (int) $id . '</td><td>' . $h(implode(', ', $files)) . "</td></tr>\n";
            }
        }
        if (!$any) {
            $s .= "<tr><td colspan=\"3\">No files attached in the app. Source documents are held outside DeductibleLog.</td></tr>\n";
        }
        $s .= "</tbody></table>\n";
        $s .= '<p class="note">Thresholds applied in this report: $250 written acknowledgment (IRC §170(f)(8)); $500 annual non-cash total for Form 8283; $500 per similar-item group for Form 8283 columns (e)–(g); $5,000 per item or group for a qualified appraisal and Section B. Verify against the current-year form instructions.</p>' . "\n";
        return $s;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function address(?Charity $c): string {
        if ($c === null) {
            return '—';
        }
        $parts = array_filter([
            $c->getAddress(),
            trim(implode(' ', array_filter([$c->getCity() ? $c->getCity() . ',' : null, $c->getState(), $c->getZip()]))),
        ]);
        return $parts !== [] ? implode(', ', $parts) : '— (add in Charities)';
    }

    /** @param array<string,bool> $keys */
    private function joinKeys(array $keys, ?callable $label = null): string {
        $vals = array_map(fn($k) => $label ? $label((string) $k) : ((string) $k === '' ? '—' : ucfirst((string) $k)), array_keys($keys));
        return implode(' / ', array_unique($vals));
    }

    private function head(array $d): string {
        $title = htmlspecialchars($d['household'] . ' — ' . $d['tax_year'] . ' Deduction Workpapers', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: Georgia, "Times New Roman", serif; font-size: 12.5px; color: #111; max-width: 1000px; margin: 0 auto; padding: 2rem; line-height: 1.35; }
  h1 { font-size: 1.5rem; margin: 0 0 0.25rem; border-bottom: 2px solid #111; padding-bottom: 0.4rem; }
  h2 { font-size: 1.15rem; margin: 1.6rem 0 0.5rem; padding: 0.25rem 0; border-bottom: 1px solid #999; }
  h3 { font-size: 0.95rem; margin: 1rem 0 0.35rem; color: #333; }
  h4 { font-size: 0.85rem; margin: 0.8rem 0 0.25rem; color: #333; font-weight: 600; }
  .meta, .note { color: #444; font-size: 0.8rem; margin: 0.3rem 0 0.8rem; }
  .print-btn { float: right; padding: 0.4rem 1rem; background: #1f2937; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-family: system-ui, sans-serif; font-size: 0.85rem; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 0.8rem; font-size: 0.8rem; }
  th, td { text-align: left; padding: 0.3rem 0.45rem; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { background: #f1f1f1; font-weight: 600; font-size: 0.75rem; color: #333; }
  .compact th, .compact td { padding: 0.2rem 0.4rem; }
  .amt { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .mark { width: 1.6rem; text-align: center; font-size: 1rem; }
  .notes { color: #444; font-size: 0.75rem; }
  tr.total td { font-weight: 700; border-top: 2px solid #999; border-bottom: none; background: #fafafa; }
  .ok { color: #15803d; font-weight: 600; }
  .flag { color: #b45309; font-weight: 700; }
  .info { color: #1d4ed8; font-weight: 700; }
  .two-col { display: flex; gap: 2rem; }
  .two-col > div { flex: 1; }
  @media print {
    .print-btn { display: none; }
    body { padding: 0.4in; max-width: none; font-size: 11px; }
    h2.page { break-before: page; }
    h2, h3, h4 { break-after: avoid; }
    tr { break-inside: avoid; }
    thead { display: table-header-group; }
  }
</style>
</head>

HTML;
    }
}
