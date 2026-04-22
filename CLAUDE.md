# DeductibleLog — Claude Development Context

## Project Overview

A self-hosted Nextcloud app (NC 33+, PHP 8.2+, Vue 3) that tracks household tax
deductions. Inspired by Intuit's discontinued ItsDeductible. Runs on the user's
Nextcloud AIO instance on a Ugreen NASync DXP2800 NAS (spectre-nas, 192.168.7.80).

**GitHub:** https://github.com/Spectre056/deductiblelog
**Dev machine:** spectre-main (Ubuntu 25.10, 192.168.7.10) — build here, deploy to NAS
**NC version:** 33.0.0 (UGOS Pro / Debian 12 on NAS)

## Deduction Categories (5 modules)

1. **Cash donations** — charity, date, amount, payment method, notes
2. **Item donations** — charity, date, items with Salvation Army FMV lookup, condition
3. **Mileage** — type (charitable/medical/business), date, miles, auto-calculated deduction
4. **Medical expenses** — provider, date, amount, category, tagged to family member
5. **Business expenses** — wife's medical consulting side-gig: supplies, travel, meals, software, mileage

## Household Members

- Michael (primary user / self)
- Allison (wife — also enters data; has her own consulting business expenses)
- Oliver (son / dependent)
- Lizzy (daughter / dependent)

Family members are configured at setup and stored in `*_family_members` table.
All expense/medical records reference a `family_member_id`.

## Tech Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | PHP 8.2, OCP/OCA framework | Nextcloud's built-in DI + ORM |
| Frontend | Vue 3 (Composition API) | `<script setup>` syntax throughout |
| State | Pinia | Replaces Vuex |
| Components | @nextcloud/vue v8 | NcContent, NcAppNavigation, etc. |
| Build | Vite + @nextcloud/vite-config | Output to js/ and css/ |
| Routing | vue-router v4 | Hash-based, single SPA |
| HTTP | @nextcloud/axios | Wraps axios with NC auth headers |
| Icons | vue-material-design-icons | MDI icon set |
| PDF export | (Phase 5 — library TBD, likely TCPDF via NC or Dompdf) |
| DB | Nextcloud IQueryBuilder | Supports SQLite/MySQL/PostgreSQL |

## Directory Structure

```
deductiblelog/
├── appinfo/
│   ├── info.xml          — NC app metadata, version, dependencies
│   ├── app.php           — app bootstrap (loads Application class)
│   └── routes.php        — all REST API + page routes
├── lib/
│   ├── AppInfo/
│   │   └── Application.php   — DI registration, IBootstrap
│   ├── Controller/
│   │   ├── PageController.php        — renders SPA shell (index route)
│   │   ├── CharityController.php     — Phase 2
│   │   ├── FamilyMemberController.php— Phase 2
│   │   ├── CashDonationController.php— Phase 2
│   │   ├── ItemDonationController.php— Phase 3
│   │   ├── ItemCategoryController.php— Phase 3
│   │   ├── MileageController.php     — Phase 4
│   │   ├── MedicalExpenseController.php — Phase 4
│   │   ├── BusinessExpenseController.php— Phase 4
│   │   ├── ReceiptController.php     — Phase 4
│   │   ├── ReportController.php      — Phase 5
│   │   └── SettingsController.php    — Phase 5
│   ├── Db/               — Entity + Mapper pairs (Phase 2+)
│   └── Service/          — Business logic layer (Phase 2+)
├── src/
│   ├── main.js           — Vue app entry point
│   ├── App.vue           — root component, NC navigation shell
│   ├── router/           — vue-router config (Phase 2)
│   ├── stores/           — Pinia stores (Phase 2+)
│   ├── views/            — one .vue file per nav section (Phase 2+)
│   └── components/       — shared components (Phase 2+)
├── templates/
│   └── index.php         — SPA shell: loads JS/CSS, renders <div id="app">
├── js/                   — (gitignored) Vite build output
├── css/                  — (gitignored) Vite build output
├── CLAUDE.md             — this file
├── composer.json
├── package.json
├── vite.config.js
└── .gitignore
```

## Database Schema (to be implemented in Phase 2 migrations)

All tables prefixed with `oc_deductiblelog_`. All include `id`, `user_id`,
`created_at`, `updated_at` unless noted. `user_id` = Nextcloud uid string.

### `oc_deductiblelog_family_members`
`id, user_id, name, relationship (self/spouse/dependent), created_at`

### `oc_deductiblelog_charities`
`id, user_id, name, ein, address, city, state, zip, notes, created_at, updated_at`

### `oc_deductiblelog_cash_donations`
`id, user_id, charity_id, tax_year, date, amount, payment_method, notes, created_at, updated_at`

### `oc_deductiblelog_item_donations`
`id, user_id, charity_id, tax_year, date, notes, total_value, created_at, updated_at`

### `oc_deductiblelog_item_donation_lines`
`id, donation_id, item_category_id, description, quantity, condition (poor/good/excellent), unit_value, total_value`

### `oc_deductiblelog_item_categories`
`id, category (clothing/furniture/electronics/appliances/other), name, min_value, max_value, unit (each/pair/set), source (salvation_army)`
Seeded with ~300 rows from Salvation Army Donation Value Guide.

### `oc_deductiblelog_mileage_logs`
`id, user_id, family_member_id, tax_year, date, purpose_type (charitable/medical/business), description, miles, rate_cents, deduction_amount, created_at, updated_at`

### `oc_deductiblelog_medical_expenses`
`id, user_id, family_member_id, tax_year, date, provider, category, amount, notes, created_at, updated_at`

### `oc_deductiblelog_business_expenses`
`id, user_id, family_member_id, tax_year, date, description, category, amount, notes, created_at, updated_at`

### `oc_deductiblelog_receipts`
`id, user_id, entity_type (cash_donation/item_donation/mileage/medical/business), entity_id, nc_file_path, original_filename, created_at`
Stored in Nextcloud Files under `DeductibleLog/Receipts/{tax_year}/`.

### `oc_deductiblelog_tax_rates`
`id, tax_year, mileage_charitable_cents, mileage_medical_cents, mileage_business_cents, updated_at, source`
Current known rates: 2025 → 14/21/70; 2026 → 14/20.5/72.5

### `oc_deductiblelog_settings`
`id, user_id, key, value`
Keys: `default_tax_year`, `household_name`, `last_update_check`

## IRS Rates (hardcoded seed, update mechanism in Phase 5)

| Year | Charitable | Medical | Business |
|------|-----------|---------|----------|
| 2024 | 14¢       | 21¢     | 67¢      |
| 2025 | 14¢       | 21¢     | 70¢      |
| 2026 | 14¢       | 20.5¢   | 72.5¢    |

Update mechanism: fetches `rates.json` from GitHub (repo: Spectre056/deductiblelog-data).
Maintained annually each December when IRS publishes new rates.

## Coding Conventions

- PHP: strict types declared in every file (`declare(strict_types=1)`)
- PHP attributes for route annotations (NC 33 style — no @-style docblock annotations)
- Vue: `<script setup>` Composition API throughout, no Options API
- No comments except for non-obvious WHY explanations
- API responses: always JSON, always include `status` key
- Controllers are thin — business logic lives in Service classes
- Db layer: Entity class + Mapper class per table (OCA\DeductibleLog\Db\*)

## Build & Deploy

```bash
# Build (run on spectre-main)
cd ~/Projects/nextcloud-apps/deductiblelog
npm install
npm run build

# Deploy to NAS (deploy.sh — to be created in Phase 2)
./deploy.sh
# Rsyncs to spectre-nas custom_apps, runs occ upgrade
```

NC custom_apps host path: `/volume2/@docker/volumes/nextcloud_aio_nextcloud/_data/custom_apps/`
(requires sudo on NAS)

## Session History

### Session 1 (2026-04-21) — Scaffold
- Created GitHub repo: https://github.com/Spectre056/deductiblelog
- Full directory scaffold committed
- All routes defined in routes.php
- PageController renders SPA shell
- App.vue has navigation sidebar with all 8 sections stubbed
- CLAUDE.md created

### Remaining Phases
- **Phase 2:** DB migrations + Salvation Army seed + Charity CRUD + Family member CRUD + deploy.sh
- **Phase 3:** Cash donations + Item donations with FMV lookup + receipt attachments
- **Phase 4:** Mileage + Medical expenses + Business expenses
- **Phase 5:** Reports (PDF/CSV/TXF) + Settings + update mechanism
- **Phase 6:** UI polish, validation, production deployment
