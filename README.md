# InvoicePro

Multi-tenant SaaS for invoicing and quote management, built for freelancers and SMEs.

- **Backend**: PHP 8.2+, homemade framework (router, DI container, PSR-15 middleware pipeline),
  hand-rolled PDO query builder, JWT authentication + homemade RBAC.
- **Frontend**: Vue 3 (Composition API), TypeScript, Pinia, Tailwind CSS.
- **Infra**: Docker, GitHub Actions CI/CD, PHPUnit / Vitest tests (planned — see Status).

## Why a homemade framework?

This project intentionally avoids Laravel/Symfony for the backend: the goal is to demonstrate
understanding of what happens inside a modern HTTP framework (routing, dependency injection,
middleware pipelines, PSR standards) rather than proficiency with an existing one.

## Requirements

- PHP 8.2+ with `pdo_mysql`, and [Composer](https://getcomposer.org)
- A MySQL/MariaDB server (e.g. MAMP, Docker, or a system install)
- Node.js 20+ and npm

## Setup

### 1. Backend

```bash
cd backend
composer install

cp .env.example .env
# edit .env: DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD to match your MySQL,
# and set JWT_SECRET to a long random string.
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

Start the API (PHP's built-in server is enough for local dev):

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

With both servers running:

1. Open http://localhost:5173 — no session yet, so it redirects to `/login`.
2. Go to **Sign up**, fill in a company name, your name, an email, and a password
   (8+ characters). This creates a new tenant with you as its `owner`.
3. You land on the authenticated home page, showing your name/email/tenant id.
4. **Log out** returns you to `/login`; logging back in with the same credentials works.

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

# Refresh (rotates the token — the old refreshToken stops working after this)
curl -s http://127.0.0.1:8000/api/v1/auth/refresh \
  -X POST -H "Content-Type: application/json" \
  -d '{"refreshToken":"<refreshToken from login>"}'

# Log out (revokes the refresh token; always 204, even for an invalid token)
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/api/v1/auth/logout \
  -X POST -H "Content-Type: application/json" \
  -d '{"refreshToken":"<refreshToken>"}'
```

## Documentation

Planned, not yet written:

- Backend architecture (`docs/architecture.md`)
- API endpoints (`docs/api.md`)
- Database schema (`docs/database.md`)

## Status

🚧 Work in progress — see commit history for progress. Phase 0 (backend foundations) and
Phase 1 (Auth module, back + front) are done; Phase 2 (Client module) is next.

Automated tests (PHPUnit/Vitest) aren't set up yet — everything above has been verified
manually (curl against a real MySQL DB, and a full browser run through the register →
logout → login flow). `composer test` and a frontend test runner are planned but not
wired up yet.
