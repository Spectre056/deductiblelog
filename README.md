# DeductibleLog

DeductibleLog is a self-hosted Nextcloud app for tracking household tax deductions in one place.

It was inspired by Intuit's discontinued ItsDeductible and is designed for people who want a private, self-hosted way to log charitable giving, mileage, medical expenses, business expenses, and supporting records.

## Features

- Cash donations: track charity, date, amount, payment method, and notes
- Item donations: log donated goods with fair market value lookup and condition-based pricing
- Mileage: record charitable, medical, and business mileage with IRS rate support
- Medical expenses: organize deductible expenses by family member and category
- Business expenses: track side-gig and small business expenses
- Family members: assign records to household members where relevant
- Receipts: attach supporting files to records
- Reports: generate accountant-friendly summaries plus CSV and TXF exports
- Settings: manage household defaults and tax-rate updates

## Tech Stack

- Backend: PHP 8.2+, Nextcloud App Framework
- Frontend: Vue 3, Pinia, `@nextcloud/vue`
- Build: Vite with `@nextcloud/vite-config`
- Database: Nextcloud-supported databases including SQLite, MySQL, and PostgreSQL

## Current Status

`v0.1.1`

The app is feature-complete for its initial release and currently includes:

- Dashboard
- Charities
- Family Members
- Cash Donations
- Item Donations
- Mileage
- Medical Expenses
- Business Expenses
- Reports
- Settings

## Requirements

- Nextcloud 33 or 34
- PHP 8.2+
- Node.js and npm for frontend builds

## Installation

### Option 1: Copy into `custom_apps`

1. Place the app in your Nextcloud `custom_apps` directory as `deductiblelog`
2. Build the frontend assets:

```bash
npm install
npm run build
```

3. Enable the app:

```bash
php occ app:enable deductiblelog
```

### Option 2: Deploy from this repo

This repo includes a `deploy.sh` script intended for the original homelab environment. Treat it as an example and update the host, user, container, and path values for your own setup before using it.

## Development

Install dependencies:

```bash
npm install
```

Run a production build:

```bash
npm run build
```

Watch frontend assets during development:

```bash
npm run watch
```

## Data Model

DeductibleLog stores data in dedicated tables for:

- family members
- charities
- cash donations
- item donations
- item donation lines
- item valuation categories
- mileage logs
- medical expenses
- business expenses
- receipts
- tax rates
- user settings

The app also seeds:

- IRS mileage rates for supported tax years
- a fair market value catalog for common donated household items

## Notes

- Browser hard refresh is often needed after deploys because Nextcloud can cache built assets aggressively.
- The app is built as a single-page Vue application inside a standard Nextcloud app shell.
- PDF export is not implemented yet; HTML, CSV, and TXF exports are available.

## Roadmap Ideas

- packaged releases for easier installation
- screenshot documentation
- improved report exports
- broader FMV data sources
- multi-user household collaboration polish

## License

AGPL-3.0-or-later
