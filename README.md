# InvoicePro

[![Backend CI](https://github.com/medibra1/php-vue3-invoicing-saas/actions/workflows/backend.yml/badge.svg)](https://github.com/medibra1/php-vue3-invoicing-saas/actions/workflows/backend.yml)
[![Frontend CI](https://github.com/medibra1/php-vue3-invoicing-saas/actions/workflows/frontend.yml/badge.svg)](https://github.com/medibra1/php-vue3-invoicing-saas/actions/workflows/frontend.yml)

Multi-tenant SaaS for invoicing and quote management, built for freelancers and SMEs.

- **Backend**: PHP 8.2+, homemade framework (router, DI container, PSR-15 middleware pipeline),
  hand-rolled PDO query builder, JWT authentication + homemade RBAC. No Laravel/Symfony, no ORM.
- **Frontend**: Vue 3 (Composition API), TypeScript, Pinia, Tailwind CSS, shadcn-vue for a few
  richer components (Dialog, DropdownMenu), Chart.js for the dashboard.
- **Infra**: Docker Compose (Nginx + PHP-FPM, MySQL, Adminer, Swagger UI), GitHub Actions CI,
  47 PHPUnit tests / 23 Vitest tests.

## Why a homemade framework?

This project intentionally avoids Laravel/Symfony for the backend: the goal is to demonstrate
understanding of what happens inside a modern HTTP framework (routing, dependency injection,
middleware pipelines, PSR standards) rather than proficiency with an existing one. The same
philosophy applies to the data layer (native PDO + a small query builder, no ORM) and the
frontend (a handful of homemade UI components rather than a full component library).

## Features

- **Multi-tenancy** — shared database/schema, every business table scoped by `tenant_id`
  resolved from the JWT, enforced structurally by the base `Repository` class (a subclass can't
  build an unscoped query even by mistake).
- **RBAC** — four roles (owner/admin/accountant/viewer), granular permissions checked per route.
- **Workflow** — Client → Quote (draft/sent/accepted/rejected/expired) → converted into an
  Invoice (draft/sent/partially\_paid/paid/overdue/cancelled) → Payments (partial payments
  supported, auto-transitions the invoice).
- **PDF generation** for invoices (dompdf, pure PHP).
- **Dashboard** — revenue (this month/all-time), overdue amount, draft quotes, quote acceptance
  rate, a 6-month revenue chart.
- **Profile management** — name, avatar upload (resized/re-encoded server-side via GD),
  password change.
- **OpenAPI docs** generated from PHP 8 attributes, served via Swagger UI.

## Quick start (Docker)

The fastest way to get a working instance — no local PHP/MySQL/Node install required beyond
[Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
git clone https://github.com/medibra1/php-vue3-invoicing-saas.git
cd php-vue3-invoicing-saas

docker compose up -d --build
docker compose exec backend php bin/migrate.php
docker compose exec backend php bin/seed.php
```

| Service      | URL                                                |
|--------------|-----------------------------------------------------|
| Frontend     | http://localhost:5173                                |
| Backend API  | http://localhost:8000/api/v1                         |
| Swagger UI   | http://localhost:8081                                 |
| Adminer      | http://localhost:8080 (server `mysql`, user/pass `root`/`root`, db `invoicepro`) |

All required backend env vars (DB credentials, `JWT_SECRET`, CORS origin) are already set in
`docker-compose.yml` for local dev — nothing to configure by hand.

## Manual setup (without Docker)

### Requirements

- PHP 8.2+ with `pdo_mysql`, `gd`, `zip`, `mbstring`, and [Composer](https://getcomposer.org)
- A MySQL/MariaDB server (e.g. MAMP, or a system install)
- Node.js 20+ and npm

### 1. Backend

```bash
cd backend
composer install

cp .env.example .env
# edit .env: DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD to match your MySQL,
# and set JWT_SECRET to a long random string (>= 32 bytes for HS256).
```

Create the database (adjust host/port/user to your setup, e.g. MAMP MySQL defaults to port 8889):

```bash
mysql -h127.0.0.1 -P8889 -uroot -p -e "CREATE DATABASE invoicepro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Run migrations, then seed the fixed RBAC roles/permissions (owner/admin/accountant/viewer):

```bash
php bin/migrate.php
php bin/seed.php
```

Start the API. PHP's built-in server works for local dev — `public/index.php` includes a
`cli-server` guard so it also serves static files (uploaded avatars) correctly, which the
built-in server otherwise wouldn't when a router script is given:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

### 2. Frontend

```bash
cd frontend
npm install

cp .env.example .env
# edit .env if your backend isn't at http://127.0.0.1:8000/api/v1
```

```bash
npm run dev
```

The app is now at **http://localhost:5173** (Vite prints the exact port on start).

## Trying it out

With the stack running (Docker or manual):

1. Open http://localhost:5173 — no session yet, so it redirects to `/login`.
2. Go to **Sign up**, fill in a company name, your name, an email, and a password
   (8+ characters). This creates a new tenant with you as its `owner`.
3. You land on the dashboard, inside the admin shell (collapsible sidebar, top-right user menu).
4. Create a client, then a quote, send it, accept it, and convert it into an invoice — or create
   an invoice directly. Record a payment (try a partial one, then the rest) and watch the
   invoice status and the dashboard numbers update.
5. Open the user menu → **Edit profile** to rename yourself or upload an avatar; **Change
   password** is a separate page on purpose (never bundled with a name/avatar update).
6. **Log out** returns you to `/login`; logging back in with the same credentials works.

### Testing the API directly

```bash
# Register a new tenant + user
curl -s http://127.0.0.1:8000/api/v1/auth/register \
  -X POST -H "Content-Type: application/json" \
  -d '{"tenantName":"Acme Freelance","name":"Jane Doe","email":"jane@acme.test","password":"supersecret123"}'

# Log in (returns accessToken + refreshToken + user)
curl -s http://127.0.0.1:8000/api/v1/auth/login \
  -X POST -H "Content-Type: application/json" \
  -d '{"email":"jane@acme.test","password":"supersecret123"}'
```

The full API surface (auth, clients, quotes, invoices, payments, stats, profile — 19 endpoints)
is documented and explorable via Swagger UI (see Quick start above), generated straight from
PHP 8 attributes on each controller — never hand-written, so it can't drift from the actual
routes.

## Documentation

Planned, not yet written:

- Backend architecture (`docs/architecture.md`)
- API endpoints (`docs/api.md`) — covered for now by the generated Swagger UI instead
- Database schema (`docs/database.md`)

## Testing

```bash
# Backend — 47 tests (Unit + Integration), SQLite in-memory, no MySQL needed
cd backend && composer test

# Frontend — 23 tests (Vitest) + type-check/build
cd frontend && npm run build && npm run test
```

Both suites run in CI on every push (see the badges above) — backend and frontend workflows
are path-filtered, so a frontend-only change doesn't trigger the PHP suite and vice versa.

## Status

All 6 planned phases are done: Auth, Clients, Quotes, Invoices, Payments + Dashboard, and an
admin shell + Profile module. Every module was verified against a real MySQL database and a
real browser run (not just unit tests) before being committed — see the commit history for the
full trail. Docker, Swagger UI, and CI are also done.

Not done, and not blocking: `docs/architecture.md` / `docs/api.md` / `docs/database.md` (the
architecture is documented in depth in code comments and OpenAPI attributes instead), and an
`activity_logs` table (flagged early as a nice-to-have, never scheduled into a phase).
