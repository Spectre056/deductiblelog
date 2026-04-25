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
| Reports | HTML (print-to-PDF) + CSV + TXF (charitable, TurboTax Desktop) |
| DB | Nextcloud IQueryBuilder | Supports SQLite/MySQL/PostgreSQL |

## Directory Structure

```
deductiblelog/
├── appinfo/
│   ├── info.xml          — NC app metadata, version, dependencies
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

## Key Gotchas

- **@nextcloud/vue must be v9.x** — v8.x is Vue 2 and its layout components (NcContent, NcAppNavigation, NcAppContent, NcAppNavigationItem) use Vue 2 `_c()` internals that silently fail with Vue 3.
- **NC cache busting** — NC appends `?v={nc-version-hash}` to JS URLs. The hash only changes when NC core updates, not when the app updates. Always hard-refresh (`Ctrl+Shift+R`) after deploys.
- **No appinfo/app.php** — NC 33 does not support it; it was removed. Use IBootstrap in Application.php only.
- **docker cp does not delete** — deploying does not remove files from the container; use `docker exec rm` for explicit deletions.

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

### Session 2 (2026-04-23) — Phase 2
- DB migrations: schema (Version000100Date20260423000000) creates all 12 tables
- DB migrations: seed (Version000100Date20260423000001) inserts ~295 Salvation Army FMV items + 3 tax-rate rows (2024/2025/2026)
- Db layer: Charity, CharityMapper, FamilyMember, FamilyMemberMapper
- Service layer: CharityService, FamilyMemberService
- Controllers: CharityController, FamilyMemberController
- Vue: router (hash-based), Pinia stores for charities + familyMembers, full CRUD views for Charities and FamilyMembers, stubs for all other views
- App.vue updated with RouterView + NcAppNavigationSpacer separating primary nav from admin items
- main.js wired with Pinia + Router
- deploy.sh created (npm build → rsync → occ upgrade on spectre-nas)

### Session 5 (2026-04-23) — Phase 4: Mileage, Medical, Business
- TaxRate + MileageLog + MedicalExpense + BusinessExpense Db layer (Entity + Mapper × 4)
- MileageService: resolves IRS rate from tax_rates by year+purposeType, calculates deduction_amount = miles × rate/100, stores rate with record for historical accuracy
- MileageController includes GET /api/mileage/rates endpoint (keyed by year); route placed before {id} routes to prevent collision
- MedicalExpenseService + BusinessExpenseService: thin CRUD with nullable family_member_id
- Three Pinia stores: mileage (tracks deduction + miles totals separately), medicalExpenses, businessExpenses
- Mileage.vue: purpose-type colored badges, IRS rate auto-fill on year/purpose change, live deduction preview, rate hint showing all three purpose rates for selected year
- Medical.vue: family member required, 10 categories (Insurance Premium through Other)
- Business.vue: family member optional (Allison's consulting), description required, 10 categories

### Session 4 (2026-04-23) — Phase 3b: Item Donations + Receipts
- ItemCategory + ItemDonation + ItemDonationLine + Receipt Db layer (Entity + Mapper × 4)
- ItemDonationService: creates/replaces line items atomically, calculates total_value
- ReceiptService: stores files in NC Files under DeductibleLog/Receipts/{year}/ via IRootFolder
- Controllers: ItemDonationController, ItemCategoryController (search endpoint), ReceiptController (index/upload/show/destroy)
- Added receipt#index route: GET /api/receipts?entity_type=&entity_id=
- itemDonations Pinia store
- ItemDonations.vue: per-line debounced FMV search (300ms), condition-based value auto-fill (poor=min, good=mid, excellent=max), receipt upload/delete in edit dialog, stays open post-create for receipt attachment

### Session 3 (2026-04-23) — Phase 3a: Cash Donations
- Phase 3 split: 3a = cash donations; 3b = item donations + FMV lookup + receipts
- CashDonation Entity + CashDonationMapper (findAllByYear, findById, sumByYear)
- CashDonationService + CashDonationController (index accepts tax_year query param, returns total)
- cashDonations Pinia store (fetchYear, create, update, remove, computed totalFormatted)
- CashDonations.vue: year filter selector, donation table with totals row, add/edit dialog (charity NcSelect, date input with auto tax_year sync, amount, payment method, notes), delete confirm

### Session 7 (2026-04-24) — Phase 6: Dashboard + Deploy
- Dashboard.vue: year selector, 5 module summary cards (colored icons, amounts, router-link), grand total bar, charitable subtotal note
- deploy.sh rewritten: tar|ssh pipe (UGOS Pro blocks rsync daemon), stages to /tmp/dl-deploy, docker cp into container (eckardmo is in docker group — no sudo), occ app:enable (idempotent, runs migrations)
- First production deploy to spectre-nas confirmed: all 12 DB tables created, app enabled at 0.1.0

### Session 6 (2026-04-24) — Phase 5: Reports + Settings
- `lib/Http/HtmlReportResponse.php` — extends Response, renders raw HTML (for print-to-PDF report)
- `lib/Db/Setting.php` + `SettingMapper.php` — key/value store per user (deductiblelog_settings)
- `lib/Service/SettingsService.php` — get/save user settings, checkUpdates (GitHub rates.json), applyUpdates (tax_rates table)
- `lib/Controller/SettingsController.php` — GET/PUT /api/settings, POST /api/settings/check-updates, POST /api/settings/apply-updates
- `lib/Service/ReportService.php` — summarize(), csv(), txf() (charitable total only, N334), html() (full print-ready page)
- `lib/Controller/ReportController.php` — /api/reports/summary|html|csv|txf (pdf returns 501)
- `appinfo/routes.php` — added report#html route at /api/reports/html
- `src/stores/settings.js` + `src/stores/reports.js` — Pinia stores
- `src/views/Reports.vue` — summary table with % breakdown, HTML/CSV/TXF export buttons
- `src/views/Settings.vue` — household name + default year form, IRS rates table with check/apply updates flow
- Build confirmed clean

### Remaining Phases
- **Phase 6 NEXT:** Dashboard.vue (summary widgets, year at a glance), UI polish, validation, production deployment
- **Phase 7:** Mandalorian theme

## Phase 7 — Mandalorian Theme

A Star Wars / Mandalorian visual theme for the app. Private self-hosted use only;
user has accepted responsibility for any Disney/Lucasfilm IP. Prefer fan-made or
AI-generated assets; use official Disney assets as fallback if quality warrants.

### Color Palette — Beskar
| Variable | Value | Use |
|---|---|---|
| `--dl-color-bg` | `#0d0f12` | App background (deep space black) |
| `--dl-color-surface` | `#1a1d23` | Card/panel surface |
| `--dl-color-border` | `#2e333d` | Borders, dividers |
| `--dl-color-beskar` | `#8a9bb0` | Primary beskar silver |
| `--dl-color-gold` | `#c9a84c` | Accent gold (Mandalorian signet, highlights) |
| `--dl-color-amber` | `#e07b2a` | Warnings, mileage category |
| `--dl-color-text` | `#d4d8de` | Primary text |
| `--dl-color-text-muted` | `#6b7280` | Secondary/muted text |

### Typography
- Headings: *Star Jedi* font (free, fan-made, widely used) — `h1`/`h2`/section titles only
- Body: system font stack — readability over theme on form fields and data

### App Icon
- `appinfo/img/app.svg` — Mandalorian helmet (Mando's beskar helmet, front-facing)
- Source: fan SVG or AI-generated; fallback to traced/simplified Disney asset

### Navigation Section Names (themed)
| Functional Name | Themed Name | Icon |
|---|---|---|
| Dashboard | The Covert | Home/Mandalorian signet |
| Cash Donations | The Coffer | Currency |
| Item Donations | The Offering | Gift/crate |
| Mileage | The Hunt | Speeder/ship |
| Medical | Medpac Ledger | Medpac/heart |
| Business Expenses | The Armory | Blaster/briefcase |
| Reports | Holorecords | Hologram/file |
| Settings | Beskar Forge | Gear/anvil |

Themed names are the default; a "Disable Star Wars theme" toggle in Settings
reverts to functional names and the standard NC color scheme.

### Background & Texture Assets
- Dashboard background: Mandalorian landscape or space scene (AI-generated or official still)
- Card texture: subtle beskar metal texture or carbon fiber pattern
- Loading spinner: Mandalorian signet (the mudhorn skull + star)
- Empty state illustrations: IG-11, Grogu, or ship silhouettes

### "This is the Way" Micro-copy
- Save confirmation: *"This is the Way."*
- Delete confirmation dialog: *"I can bring you in warm, or I can bring you in cold."*
- Empty dashboard (no entries yet): *"You have a long way to go."*
- Successful report export: *"I have spoken."*
- Settings saved: *"Foundlings are the future."*
- Update check — no updates: *"The asset is secure."*
- Update check — updates available: *"New intel from the Guild."*
