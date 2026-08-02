# Architecture

## Why a homemade backend framework

The backend is PHP 8.2+ with no Laravel/Symfony — a small framework built from PSR standards
(PSR-7 HTTP messages, PSR-15 middleware, PSR-11 container) rather than an existing one. The goal
is demonstrating what happens *inside* a modern HTTP framework — routing, dependency injection,
middleware pipelines — not proficiency with a specific one. The same philosophy runs through the
whole stack: native PDO + a small hand-rolled query builder instead of an ORM, and a handful of
homemade Vue components instead of a full UI component library.

Reused rather than reinvented, deliberately: PSR-7 message objects themselves (`Request`/
`Response`/`Stream`/`Uri`) come from `nyholm/psr7` + `nyholm/psr7-server` — reimplementing
stream/URI parsing from scratch was judged low-value next to the architectural pieces that
actually demonstrate seniority (the router, the container, the middleware pipeline, the query
builder's tenant-scoping mechanism).

## Request lifecycle

```
public/index.php
  → builds Container + Router, registers routes, wires Kernel
  → Kernel::run()
      → ServerRequestCreator::fromGlobals() (nyholm/psr7-server)
      → Kernel::handle($request)
          → global MiddlewarePipeline (Cors → JsonBodyParser)
              → Router::match($request)  →  404 / 405 handled here
              → per-route MiddlewarePipeline (Auth → TenantResolver → Permission, as declared)
                  → Controller action (resolved + called via Container)
          → any uncaught Throwable → 500 JSON
      → ResponseEmitter::emit($response)
```

Global middleware runs **before** routing, wrapping the whole routing+dispatch step as its own
final handler — not just around an already-matched route. Otherwise a CORS preflight `OPTIONS` to
an unregistered path would 404 before `CorsMiddleware` ever got a chance to answer it.

Per-route middleware is declared as a plain array on each route: `AuthMiddleware::class` (decodes
the JWT), `TenantResolverMiddleware::class` (resolves + verifies the tenant), and a
`[PermissionMiddleware::class, 'invoices.view']` tuple (the permission slug is injected into
whichever middleware constructor parameter is literally named `$parameter`). Order matters and is
enforced by convention, not the type system: `TenantResolverMiddleware` needs `authClaims`
(set by `AuthMiddleware`), `PermissionMiddleware` needs both.

## Core pieces (`backend/src/Core/`)

- **`Container`** — reflection-based auto-wiring DI container. Self-binds so any class can
  type-hint `Container` itself; `makeWith()` resolves a class while overriding specific
  constructor parameters (used by `MiddlewarePipeline` to inject a route's middleware tuple
  parameter, and by `Kernel` to inject the request's route params into a controller action).
- **`Router`** — route groups (nested prefixes + shared middleware), dynamic `{param}` segments,
  distinguishes 404 (no route) from 405 (route exists, wrong method — returns the allowed methods
  in an `Allow` header).
- **`MiddlewarePipeline`** — classic PSR-15 onion: each middleware wraps the next handler, the
  innermost handler is the controller call.
- **`Database\Connection`** — thin PDO wrapper (`statement()`, `transaction()`), knows nothing
  about SQL syntax.
- **`Database\QueryBuilder`** — fluent builder with two non-optional safety mechanisms once
  triggered: `forTenant()` (see [database.md](database.md) for the full multi-tenancy mechanism)
  and a refusal to run `update()`/`delete()` without a `WHERE` clause
  (`UnsafeQueryException`) — a missing `->where(...)` fails loudly instead of touching every row.
- **`Database\Repository`** — base class every business-table repository extends;
  `find()`/`all()`/`create()`/`update()`/`delete()` (soft) are generic, a subclass only implements
  `table(): string`.
- **`Database\HasLineItems`** — trait for a repository whose rows own a child collection
  (`quote_items`/`invoice_items`): `findWithItems()`, `replaceItems()` (delete-then-reinsert the
  whole set on edit, rather than diffing).
- **`Database\Migration`/`Migrator`** — `up()`/`down()` contract; the migrator runs whatever
  hasn't run yet from `database/migrations/*.php`, in filename order, tracked in a `migrations`
  table.

## Modules (`backend/src/Modules/`)

Feature-based, one directory per bounded concern — mirrored by the frontend's `modules/` for a
consistent mental map across both codebases:

| Module | Owns |
|---|---|
| `Auth` | JWT encode/decode, register/login/refresh/logout, RBAC permission checks |
| `Tenant` | Tenant resolution/verification middleware |
| `Client` | Client CRUD (the template every later business module follows) |
| `Quote` | Quote + line items, status transitions, conversion into an invoice |
| `Invoice` | Invoice CRUD, PDF generation, status transitions |
| `Payment` | Payment recording, invoice balance computation, auto-transitions |
| `Stats` | Dashboard aggregate reads across quotes/invoices/payments |
| `Profile` | Current-user self-service (name, avatar, password) |
| `ActivityLog` | Append-only audit trail |
| `Shared` | `DocumentNumberGenerator` — the one class genuinely used by two modules (Quote and Invoice), so it belongs to neither |

Each business module generally follows the same shape: `{Module}Repository` (data access,
tenant-scoped), `{Module}Exception` (an expected failure, carries the HTTP status it maps to —
422 by default for most, explicit 404/401/403/409 at the throw site), `{Module}Service` (business
logic — status graphs, validation, transactions; no `Request`/`Response` object reaches this far
in, so it's testable and reusable without HTTP scaffolding), `{Module}Controller` (thin HTTP
layer: pulls the request body, calls the service, catches `{Module}Exception` and converts it to
`JsonErrorResponse`). Not every module needs every piece — `Stats` and `ActivityLog` have no
`Exception` class since neither has a validation-failure path.

Cross-module dependencies go through a **repository**, not a service, when one module needs to
read/write another's data as a side effect: `PaymentService` depends on `InvoiceRepository`
directly (not `InvoiceService`) to update an invoice's status inside its own transaction, and
`QuoteToInvoiceConverter` does the same across `QuoteRepository`/`InvoiceRepository`. This keeps
each module's own business-logic layer (the transition graph, the validation rules) from becoming
implicitly coupled to another module's.

### RBAC

Four fixed roles (`owner`, `admin`, `accountant`, `viewer`), granular permissions
(`invoices.create`, `clients.view`, ...) checked per-route via the `PermissionMiddleware` tuple.
`PermissionRepository::userHasPermission()` does three small tenant-scoped queries (`user_roles`
→ `role_permissions` → `permissions`) rather than one join, since the query builder doesn't
support joins — acceptable at this scale, flagged as worth collapsing if that ever changes.
Self-service endpoints (`/me/*`) and read-only aggregate endpoints available to every viewing
role (`/stats/dashboard`, `/activity-logs`) are the two categories that don't follow the
"exactly one permission per route" pattern: profile management has no RBAC gate at all (implicit
for any authenticated user), while stats/activity-log get their own `*.view` permission granted
broadly rather than reusing an unrelated one.

### PDF generation

`dompdf/dompdf` — pure PHP, no external binary or service, chosen for the same
"clone and run, no exotic local setup" reasoning as every other dependency. `InvoicePdfGenerator`
is deliberately DB-free (invoice + client data in, PDF bytes out), so it's testable and reusable
without a `Connection`.

## Frontend (`frontend/src/`)

Vue 3, Composition API only (no Options API), TypeScript, `<script setup>` everywhere.

- **`modules/`** — one directory per feature, mirroring the backend's `Modules/` naming. Each
  module owns `types.ts` (API shapes), `store.ts` (a Pinia store — the only thing that talks to
  `httpClient` directly), `composables/use{Module}.ts` (UI-facing: loading/error state, calls the
  store, does any post-action navigation — kept separate from the store so the store stays usable
  from router guards/interceptors without pulling in Vue Router), and `components/` (the actual
  `.vue` files).
- **`components/ui/`** — a small homemade set (`Button`, `Input`, `Badge`, `Card`, `Table`,
  `MetricCard`) plus a few copied-in shadcn-vue components (`Dialog`, `DropdownMenu`) for the
  handful of places a homemade component would've meant reinventing real interaction complexity
  (focus trapping, portal rendering). shadcn-vue components are copied into the repo, not an npm
  dependency, so they sit *alongside* the homemade set rather than replacing it.
- **`layouts/`** — `AdminLayout.vue` (Sidebar + Topbar + `<RouterView>`) wraps every authenticated
  route via a parent route in `router/index.ts`; `requiresAuth: true` is set once on that parent,
  vue-router merges matched-record metas so children inherit it.
- **API client** — a single `axios` instance (`api/httpClient.ts`) every store goes through, with
  one interceptor handling JWT attachment and the 401→refresh flow (promise-guarded so concurrent
  401s can't trigger a double refresh).
- **Route guards are UX-only** — they hide/redirect based on auth state, never the actual security
  boundary. Every protected endpoint enforces auth/tenant/permission again server-side regardless
  of what the frontend decided.

## Testing strategy

Every integration test (`tests/Integration/`) runs against a **real SQLite in-memory database**
with a hand-written minimal schema (not the actual MySQL migrations) and **real wiring** — actual
`Repository`/`Service` instances, never mocked. This exercises real behavior (the tenant-scoping
mechanism, transaction boundaries, transition graphs) rather than asserting that mocked calls
happened. Unit tests (`tests/Unit/`) are for pieces with no DB dependency at all — `AvatarService`
(real GD, real generated PNG/JPEG bytes, no binary fixture committed) and `InvoicePdfGenerator`
(real dompdf output, decompressed and checked for actual content, not just "is a valid PDF").

This is also why CI needs no MySQL service container (see `.github/workflows/backend.yml`) —
`pdo_sqlite` is enough. Every module was additionally verified against the real MySQL DB (and,
for frontend work, a real browser) before being committed — see the commit history.

## Infra

Docker Compose (`docker-compose.yml`) runs six services for local dev: `nginx` (reverse proxy +
static file serving for uploaded avatars via `try_files`), `backend` (PHP-FPM 8.2), `mysql` 8,
`frontend` (Vite dev server), `adminer`, and `swagger-ui` (serves a generated
`backend/public/openapi.json`, regenerated via `composer run docs`). All required backend env
vars are set directly in `docker-compose.yml` — `loadEnvFile()` never overrides a real env var, so
the whole stack works standalone on a fresh clone with zero manual configuration. See the root
[README](../README.md) for the actual commands.
