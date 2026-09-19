# Stockpoint Pharma ERP

A multi-branch ERP for pharmaceutical wholesale, retail and dispensing operations. It covers the
full trading cycle from requisition to payment, with batch and expiry tracking, a seven-step
pricing engine, double-entry accounting and Kenyan eTIMS fiscal invoicing.

The backend is a Laravel JSON API. The frontend is a separate React single-page application that
consumes it.

---

## Contents

- [What it does](#what-it-does)
- [Architecture](#architecture)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Running the app](#running-the-app)
- [Testing and quality gates](#testing-and-quality-gates)
- [API conventions](#api-conventions)
- [Scheduled jobs](#scheduled-jobs)
- [Console commands](#console-commands)
- [Roles and permissions](#roles-and-permissions)
- [Project layout](#project-layout)
- [eTIMS configuration](#etims-configuration)

---

## What it does

| Module | Capability |
| --- | --- |
| **Products and master data** | Catalogue with generic names, dosage forms, manufacturers, storage conditions, multi-UoM packs with barcodes, customers, tiers, suppliers, stores and locations |
| **Pricing** | Seven-step quote engine: contract price, promotion, tier list, mode list, branch override, product default, then quantity breaks, discounts, bonus, margin floor, tax and rounding |
| **Point of sale** | One checkout path for retail, wholesale and dispensing. Sale mode is a data field on the branch, never a separate system |
| **Wholesale orders** | Quotation, sales order, picking list, dispatch, delivery note and proof of delivery |
| **Inventory** | Ledger-first stock with batch and expiry tracking, FEFO allocation, reservations, transfers between stores, cycle counts and approval-gated adjustments |
| **Procurement** | Reorder advice, requisitions, purchase orders, goods receipts, landed costs, supplier invoices with three-way matching and supplier payments |
| **Quality and compliance** | Batch quarantine and release, waste disposal, and product recalls with customer traceback |
| **Returns** | Customer returns with per-line disposition, and supplier returns |
| **Finance** | Chart of accounts, journal entries, receivables and payables, payments with allocation, financial periods and period close |
| **Tax** | eTIMS fiscal invoicing with a retry queue, so a tax authority outage never blocks a sale |
| **Payroll** | Employees, banded PAYE, NSSF and SHIF calculation, payroll runs from compute to bank payment, and payslips |
| **Reporting** | A catalogue of 52 reports across sales, margin, inventory, procurement, finance, quality, management and exceptions |

---

## Architecture

### Ledger-first inventory

`stock_ledgers` is the single source of truth for stock. `stock_balances` is a derived,
reconcilable cache. Every write goes through `StockLedgerService::post()`, which writes the ledger
row first and updates the cache from it inside one transaction. Mutating `stock_balances` directly
is architecturally prohibited. A nightly job reconciles the cache and the general ledger against
the stock ledger, and must return zero rows.

### FEFO batch allocation

Stock is allocated first-expired-first-out by `FefoAllocator`, inside the same transaction that
posts the ledger. Overriding FEFO to name a specific batch requires the `stock.fefo.override`
permission and a reason, and is recorded as an exception for reporting.

### Branch scoping

`ResolveActiveBranch` middleware resolves the acting branch on every request and sets it as the
Spatie Permission team context, so role checks are branch-scoped. The client may send an
`X-Branch-Id` header, but it is validated against the caller's own role assignments, so it cannot
be used to reach a branch the user is not assigned to. The organisation is always derived
server-side and never accepted from the client.

### Money and quantities

Every monetary and quantity field is `DECIMAL(18,4)` in the database, is carried on the wire as a
decimal **string** such as `"125.5000"`, and is computed with bcmath. Never parse these into a
native float or compute on them with `+`, `-` or `*`. The frontend uses a decimal-safe helper in
`frontend/src/lib/decimal.ts`.

### Idempotency

Endpoints that move money or stock require an `Idempotency-Key` header, falling back to an
`idempotency_key` body field. A retried request with the same key returns the original result
rather than a duplicate, and sets `X-Idempotent-Replay: true` on the response. Generate a fresh
key per distinct user action.

---

## Tech stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ |
| Database | MySQL 8 or MariaDB 10.11+ |
| Auth | Laravel Sanctum, with TOTP multi-factor via `pragmarx/google2fa` |
| Authorization | `spatie/laravel-permission`, team-scoped per branch |
| Frontend | React 19, TypeScript, Vite, Tailwind CSS 4 |
| Frontend data | TanStack Query for server state, Zustand for local state |
| Tests | PHPUnit 12 |
| Static analysis | PHPStan level 5 with Larastan |
| Formatting | Laravel Pint, oxlint |

### Required PHP extensions

`bcmath`, `pdo_mysql`, `mbstring`, `openssl`. The `bcmath` extension is not optional: all money
and quantity arithmetic depends on it, and without it the pricing, finance and payroll code fails
at runtime.

---

## Getting started

### Prerequisites

- PHP 8.3 or newer, with the extensions listed above
- Composer 2
- MySQL 8 or MariaDB 10.11+
- Node.js 20 or newer

### Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Point the database settings in `.env` at your server, then create the schema and seed the
reference data:

```bash
mysql -u root -e "CREATE DATABASE pharmacy_erp CHARACTER SET utf8mb4"
php artisan migrate
php artisan db:seed
```

Seeding installs permissions, the ten roles, the organisation and branch structure, the chart of
accounts, discount authorities, payroll bands, tax codes, units of measure and the price list.

Create an administrator:

```bash
php artisan user:create-admin admin@example.com
```

### Frontend

```bash
cd frontend
npm install
```

---

## Running the app

Start the API and the SPA in two terminals:

```bash
php artisan serve --port=8000
```

```bash
cd frontend && npm run dev
```

The SPA runs on port 5173 and proxies `/api`, `/auth` and `/sanctum` to the API on port 8000.
The proxy matters: Sanctum's cookie session authentication requires the browser to see the API as
same-origin, and a cross-origin request cannot carry the session cookie.

Open http://localhost:5173.

The default queue connection is the database, and eTIMS submission is dispatched to it. Run a
worker whenever fiscal invoicing is enabled:

```bash
php artisan queue:work
```

---

## Testing and quality gates

The test suite runs against a **real MySQL database**, not SQLite, so that check constraints, row
locks and enum columns behave exactly as they do in production. The migrations use MySQL-specific
DDL and will not run on SQLite. Connection settings live in `phpunit.xml`, and the database name
must contain `test` or the suite refuses to run.

```bash
mysql -u root -e "CREATE DATABASE pharmacy_erp_test CHARACTER SET utf8mb4"
php artisan test
```

Migrations are applied once per process and every test is rolled back in a transaction, so the
suite does not pay for a `migrate:fresh` per test.

Narrow a run to one file or one test:

```bash
php artisan test --compact tests/Feature/Blueprint/LedgerFirstTest.php
php artisan test --filter=test_a_rolled_back_document_does_not_burn_a_number
```

Other gates:

```bash
vendor/bin/pint            # format PHP
vendor/bin/phpstan analyse # static analysis at level 5
cd frontend && npm run build   # typecheck and production build
cd frontend && npm run lint    # oxlint
```

Tests are organised in three groups. `tests/Feature/Blueprint` asserts the domain invariants such
as ledger-first posting, journal balance, FEFO allocation, costing, credit control and idempotency.
`tests/Feature/Api` drives the HTTP surface end to end. `tests/Unit` covers the pure calculators.

---

## API conventions

Full endpoint documentation, including request bodies, response shapes, permissions and error
codes, is in `docs/Stockpoint_ERP_API_Reference_v2.docx`.

| Concern | Convention |
| --- | --- |
| Base URL | `/api`, except the session auth routes under `/auth` |
| Auth | `auth:sanctum` on every route, accepting either an SPA session cookie or a `Bearer` token |
| CSRF | Cookie clients call `GET /sanctum/csrf-cookie` first, then send the `XSRF-TOKEN` value back as an `X-XSRF-TOKEN` header, or `/auth/login` answers 419 |
| IDs | UUID v7 strings, except `users.id`, which is an integer |
| Money and quantity | Decimal strings at four decimal places |
| Dates | `YYYY-MM-DD`; datetimes are ISO 8601 UTC |
| Branch | Optional `X-Branch-Id` header, validated against the caller's assignments |
| Permissions | Checked server-side per action; `GET /api/user` returns the effective set for driving navigation |

Domain errors use a single envelope:

```json
{
  "error": {
    "code": "CREDIT_LIMIT_EXCEEDED",
    "message": "Order would exceed credit limit: limit 5000.0000, exposure 4800.0000, shortfall 300.0000.",
    "details": { "limit": "5000.0000", "exposure": "4800.0000", "shortfall": "300.0000" }
  }
}
```

Validation failures are the exception and render as Laravel's default 422 body with a `message`
and an `errors` map. The mapping from domain exception to status and code lives in
`app/Exceptions/ApiErrorMap.php`.

---

## Scheduled jobs

Run the scheduler in production with a single cron entry calling `php artisan schedule:run` each
minute. Three jobs are registered in `routes/console.php`:

| Job | Frequency | Purpose |
| --- | --- | --- |
| `inventory:expire-batches` | daily at 00:05 | Move batches past their expiry date out of free-to-sell stock |
| `inventory:release-expired-reservations` | hourly | Release stock held by abandoned orders |
| `inventory:reconcile-ledger` | daily at 00:30 | Verify balances against the ledger, and the ledger value against the general ledger |

---

## Console commands

| Command | Purpose |
| --- | --- |
| `user:create-admin` | Create an administrator account and assign its roles per branch |
| `inventory:expire-batches` | Mark batches past expiry as expired. Supports `--dry-run` |
| `inventory:release-expired-reservations` | Release active reservations that have expired |
| `inventory:reconcile-ledger` | Reconcile stock balances and the general ledger against the stock ledger |

---

## Roles and permissions

Ten roles ship in the seeder: Director, Operations Manager, Pharmacist, Senior Cashier, Cashier,
Storekeeper, Procurement Officer, Finance Officer, Auditor and System Administrator.

Roles are assigned per branch, so the same person can hold different authority in different
branches. Permissions are always checked on the server. A permission that has not been defined
denies rather than erroring, on the principle that a hidden button is not security and neither is
a missing seed row. Use the permission list from `GET /api/user` to drive navigation and button
visibility, never a client-side guess.

---

## Project layout

```
app/
  Console/Commands/      Nightly jobs and the admin bootstrap command
  Exceptions/            ApiErrorMap: domain exception to HTTP status and code
  Http/Controllers/Api/  The API surface, one controller per module
  Http/Middleware/       Branch resolution and request correlation IDs
  Models/                85 Eloquent models
  Services/              The domain layer, grouped by bounded context
    Finance/             Journal posting, receipts, sales journal mapping
    Inventory/           Stock ledger, FEFO, transfers, counts, adjustments
    Payroll/             PAYE, NSSF and SHIF calculation and run orchestration
    Pricing/             The seven-step engine, tax, bonus, discount authority
    Procurement/         Goods receipt, landed cost, three-way match, reorder
    Quality/             Recalls and waste disposal
    Reports/             The report catalogue and its query classes
    Sales/               Checkout, picking, dispatch, quotations, returns, voids
    Tax/Etims/           Fiscal invoicing drivers: http, log and null
database/
  migrations/            87 migrations
  seeders/               Permissions, roles, chart of accounts, price lists
docs/                    API reference documents
frontend/src/
  components/            Shared UI and layout
  features/              One folder per module, mirroring the navigation
  lib/                   API client, decimal helpers, permissions, formatting
routes/
  api.php                The API catalogue
  console.php            The schedule
  web.php                Session auth, MFA and logout
tests/
  Feature/Api/           HTTP flows end to end
  Feature/Blueprint/     Domain invariants
  Unit/                  Pure calculators
```

---

## eTIMS configuration

Fiscal invoicing is off by default. Enable it in `.env`:

```dotenv
ETIMS_ENABLED=true
ETIMS_DRIVER=http     # log = local placeholder codes, http = live gateway, null = off
ETIMS_BASE_URL=
ETIMS_TOKEN=
ETIMS_DEVICE_SERIAL=
ETIMS_SELLER_PIN=
ETIMS_BRANCH_CODE=00
```

Submission is queued, so a gateway outage never blocks a sale. Failed submissions stay in the
queue at `GET /api/etims/queue` and can be retried per sale or per credit note.
