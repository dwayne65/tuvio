# TicketFlow

> Every Event. Every Ticket. One Platform.

A premium event ticketing & management SaaS platform for African and international
markets — built as a modular monolith on **Laravel 13 + Inertia + React + TypeScript**
with **PostgreSQL**, an auditable **double-entry ledger**, QR ticketing, and a PWA
check-in scanner.

---

## Stack

| Layer      | Technology |
|------------|------------|
| Backend    | Laravel 13 (PHP 8.4) |
| Frontend   | Inertia.js + React 18 + TypeScript + Tailwind v4 |
| Database   | PostgreSQL 18 |
| Cache/Queue| Redis + Laravel Queues |
| Payments   | Provider adapters (Flutterwave sandbox first; Pesapal-ready) |
| Money      | Integer **minor units** everywhere (`App\Support\Money`) — never floats |

## Architecture

```
app/
  Models/            Eloquent models (one per table, 30+)
  Support/Money.php  Immutable money value object (minor units)
  Domain/            Business logic, grouped by module
    Ledger/          LedgerService, ChartOfAccounts, LedgerLine  (double-entry)
    Payments/        (provider adapters — in progress)
    Orders/          (checkout + reservations — in progress)
    Tickets/         (QR issuance + check-in — in progress)
config/ticketflow.php  Platform settings: currency, commission bps, reservation window, payout settlement
database/migrations/   40 tables, fully migrated
database/seeders/      Chart of accounts, subscription plans, categories, admin user
```

### Domain modules (planned/partial)
`Auth · Organizations · Subscriptions · Events · Tickets · Orders · Payments · Ledger · Payouts · Contributions · CheckIn · Notifications · Analytics`

### Financial system — double-entry ledger
- Every money movement posts a **balanced** `ledger_transaction` with ≥2 `ledger_entries`
  (sum of debits === sum of credits, enforced in `LedgerService::post()`).
- `ledger_entries` are **append-only**: a PostgreSQL trigger rejects UPDATE/DELETE, so
  posted financial history cannot be silently rewritten.
- Chart of accounts: platform cash, commission income, service-fee income, subscription
  income, processor-fee expense, tax payable, refunds clearing, plus a per-organizer
  **payable** subsidiary account.
- Money is stored as integer minor units in `*_minor` columns with a `char(3)` currency.

### Ticketing & payments (design)
- Tickets carry a public `code` + a secret, non-guessable `qr_token` (HMAC-verified on scan).
- Inventory tracked via `quantity_total/sold/reserved`; orders hold a reservation (`reserved_until`).
- Payments use an `idempotency_key` and are only trusted after **server-side verification**
  (`payments.verified_at`). Tickets are issued only on verified payment.
- Webhooks are logged + de-duplicated in `payment_webhooks`.

---

## Local setup

### Prerequisites
PHP 8.4, Composer, PostgreSQL 18, Redis, Node 20+.

### Database
```sh
sudo systemctl enable --now postgresql
sudo -u postgres psql -c "CREATE ROLE ticketflow LOGIN PASSWORD 'ticketflow';"
sudo -u postgres psql -c "CREATE DATABASE ticketflow OWNER ticketflow;"
```

### App
```sh
composer install
cp .env.example .env            # (if starting fresh)
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev      # frontend (once Inertia/React is scaffolded)
php artisan serve
```

Default admin after seeding: **admin@ticketflow.test** / `password`.

## Environment variables

| Key | Purpose |
|-----|---------|
| `DB_*` | PostgreSQL connection (database `ticketflow`) |
| `TICKETFLOW_CURRENCY` | Default currency (UGX) |
| `TICKETFLOW_COMMISSION_BPS` | Default platform commission in basis points (450 = 4.5%); plans set their own |
| `TICKETFLOW_TAX_BPS` | Tax on the platform fee in basis points (default 0 = no tax; 1800 = 18% VAT) |
| `TICKETFLOW_CONTRIBUTION_COMMISSION_BPS` | Commission on contributions |
| `TICKETFLOW_RESERVATION_MINUTES` | Unpaid-order inventory hold |
| `FLW_PUBLIC_KEY` / `FLW_SECRET_KEY` / `FLW_ENCRYPTION_KEY` / `FLW_WEBHOOK_SECRET` | Flutterwave sandbox |

> **Never** put payment secrets in frontend code. All payment verification is server-side.

---

## Build phases

- [x] **Foundation** — toolchain, Laravel scaffold, PostgreSQL
- [x] **Schema** — 40 tables, migrations, append-only ledger trigger
- [x] **Architecture** — models, `Money` VO, `LedgerService` (double-entry, verified)
- [x] **Reference data** — chart of accounts, subscription plans, categories, admin
- [x] **Phase 1** — design system + public website (Inertia/React): home, events listing + detail, categories, pricing, about, contact; brand design system, navbar/footer, UI kit (Button/Card/Badge/Input/EventCard)
- [x] **Phase 2** — auth (register/login/logout, password reset, email verification), profile settings, organizations (become-organizer onboarding + switcher), RBAC (policies + role helpers + admin super-gate). 6 feature tests passing.
- [x] **Phase 3** — subscriptions (trials, free/paid activation, plan switch, cancel/resume, invoices) + publish-gating by plan limits + billing UI + organizer dashboard shell. 5 feature tests passing.
- [x] **Phase 4** — event creation wizard, ticket types, organizer events CRUD (publish/unpublish/duplicate/cancel), publish-gated. 5 tests.
- [x] **Phase 5** — checkout, atomic inventory reservation (no oversell), payment provider adapters (Flutterwave sandbox + dev fake), server-side verify-before-issue, idempotent fulfillment, balanced double-entry ledger postings, signed+deduped webhooks. 6 tests.
- [x] **Phase 6** — secure QR tickets, ticket wallet (customer), PWA check-in scanner (camera + manual, live stats), atomic single-use check-in with offline dedup. 6 tests.
- [ ] **Phase 7** — fundraising, payouts, notifications, admin
- [ ] **Phase 8** — security + automated testing + deployment

## Testing

Automated tests (to be added) will cover: ledger balance integrity, ticket inventory /
oversell prevention, idempotent payments, QR validation, atomic check-in, and authorization.

---

## Deployment preparation

1. **Server**: PHP 8.4, PostgreSQL 18, Redis, Node 20+ (build step only).
2. **Env**: copy `.env.example` → `.env`, set `APP_ENV=production`, `APP_DEBUG=false`,
   real `DB_*`, `PAYMENT_PROVIDER=flutterwave` + `FLW_*` keys, and a mail transport.
   > The `fake` payment provider refuses to run when `APP_ENV=production`.
3. **Build & migrate**:
   ```sh
   composer install --no-dev --optimize-autoloader
   php artisan key:generate        # if needed
   php artisan migrate --force --seed
   npm ci && npm run build
   php artisan config:cache route:cache
   ```
4. **Queues** (emails, async work): run `php artisan queue:work` under a supervisor.
5. **Flutterwave webhook**: point it at `POST /webhooks/flutterwave` and set `FLW_WEBHOOK_SECRET`.
6. **Storage**: `php artisan storage:link` (done) for public avatars/images.

## Test suite

```sh
php artisan test        # 34 feature tests covering auth, subscriptions, events,
                        # orders/payments/ledger, check-in, contributions & payouts
```
