# API reference

This is a quick index. For full request/response schemas, try each endpoint interactively via
**Swagger UI** (http://localhost:8081 with the Docker setup — see the root [README](../README.md))
— it's generated straight from the same PHP 8 attributes on each controller as this page is
written from, so it can't drift from the actual routes.

Base URL: `/api/v1`. All responses are JSON. Errors share one shape:

```json
{ "error": { "status": 422, "message": "Human-readable message", "detail": "..." } }
```

(`detail` only appears when `APP_DEBUG=true`.)

## Authentication

Every endpoint below `/auth/*` requires an `Authorization: Bearer <accessToken>` header. Tokens
come from `/auth/login` or `/auth/register`; `accessToken` expires after `JWT_TTL` seconds
(3600 by default) and is renewed via `/auth/refresh` using the paired `refreshToken` (rotated on
every use — the old one stops working).

| Method | Path | Auth | Permission | Notes |
|---|---|---|---|---|
| POST | `/auth/register` | — | — | Creates a new tenant + its first user, who becomes `owner` |
| POST | `/auth/login` | — | — | |
| POST | `/auth/refresh` | — | — | Body: `{ "refreshToken": "..." }` |
| POST | `/auth/logout` | — | — | Idempotent — always succeeds, even for an already-revoked token |

## Clients

| Method | Path | Permission |
|---|---|---|
| GET | `/clients` `?search=` | `clients.view` |
| GET | `/clients/{id}` | `clients.view` |
| POST | `/clients` | `clients.create` |
| PUT | `/clients/{id}` | `clients.update` |
| DELETE | `/clients/{id}` | `clients.delete` |

## Quotes

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/quotes` | `quotes.view` | Without line items |
| GET | `/quotes/{id}` | `quotes.view` | With line items |
| POST | `/quotes` | `quotes.create` | Always created as `draft` |
| PUT | `/quotes/{id}` | `quotes.update` | Draft only |
| DELETE | `/quotes/{id}` | `quotes.delete` | Draft only |
| POST | `/quotes/{id}/status` | `quotes.update` | `draft→sent→accepted/rejected/expired` |
| POST | `/quotes/{id}/convert` | `quotes.convert` | Accepted only; returns the new invoice |

## Invoices

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/invoices` | `invoices.view` | Without line items |
| GET | `/invoices/{id}` | `invoices.view` | With line items |
| POST | `/invoices` | `invoices.create` | Standalone (not via a quote) |
| PUT | `/invoices/{id}` | `invoices.update` | Draft only |
| DELETE | `/invoices/{id}` | `invoices.delete` | Draft only |
| POST | `/invoices/{id}/status` | `invoices.update` | `draft→sent→paid/overdue/cancelled` (+ `partially_paid` as a source, reachable only via a payment) |
| GET | `/invoices/{id}/pdf` | `invoices.view` | `Content-Type: application/pdf` |

## Payments

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/invoices/{id}/payments` | `payments.view` | |
| POST | `/invoices/{id}/payments` | `payments.create` | Rejects an amount exceeding the remaining balance; auto-transitions the invoice |

## Stats

| Method | Path | Permission |
|---|---|---|
| GET | `/stats/dashboard` | `stats.view` |

Single payload: `revenue` (this month/all-time, from actual payments collected), `overdue`
(count + remaining balance), `draftQuotes` (count), `quoteAcceptanceRate` (excludes undecided
quotes, `null` if none decided yet), `revenueByMonth` (last 6 months, gaps filled with 0).

## Activity log

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/activity-logs` `?page=&perPage=` | `activity_logs.view` | Newest first; `perPage` capped at 100 |

Logged automatically on quote/invoice status changes, quote→invoice conversion, and payment
recording — not full CRUD on every entity.

## Profile (`/me`)

No RBAC permission on any of these — managing your own profile is implicit for any authenticated
user, not gated by the permission matrix above.

| Method | Path | Notes |
|---|---|---|
| GET | `/me` | Includes a display-only `role` slug |
| PUT | `/me` | Body: `{ "name": "..." }` — email is not editable |
| POST | `/me/avatar` | `multipart/form-data`, field `avatar`; JPEG/PNG/WebP, ≤2MB, resized to 256×256 |
| DELETE | `/me/avatar` | Falls back to initials on the frontend |
| PUT | `/me/password` | Body: `{ "current_password": "...", "new_password": "..." }` |

## Team

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/team` | `users.manage` | Members of the current tenant, with their role |
| POST | `/team` | `users.manage` | Body: `{ "name", "email", "password", "role" }` — creates the account directly and usably, no invitation email |

## RBAC quick reference

| Role | Permissions |
|---|---|
| `owner`, `admin` | Everything, incl. Team |
| `accountant` | Clients (view/create/update), Quotes (view/create/update/convert), Invoices (view/create/update), Payments (view/create), Stats, Activity log |
| `viewer` | Everything above, read-only (`*.view` only) |

Every tenant's first user (from `/auth/register`) is its `owner`. Additional users are added via
`POST /team` by an `owner`/`admin`, with any of the four roles.
