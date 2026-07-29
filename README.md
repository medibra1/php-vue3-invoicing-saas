# InvoicePro

Multi-tenant SaaS for invoicing and quote management, built for freelancers and SMEs.

- **Backend**: PHP 8.2+, homemade framework (router, DI container, PSR-15 middleware pipeline),
  hand-rolled PDO query builder, JWT authentication + homemade RBAC.
- **Frontend**: Vue 3 (Composition API), TypeScript, Pinia.
- **Infra**: Docker, GitHub Actions CI/CD, PHPUnit / Vitest tests.

## Why a homemade framework?

This project intentionally avoids Laravel/Symfony for the backend: the goal is to demonstrate
understanding of what happens inside a modern HTTP framework (routing, dependency injection,
middleware pipelines, PSR standards) rather than proficiency with an existing one.

## Documentation

- [Backend architecture](docs/architecture.md)
- [API endpoints](docs/api.md)
- [Database schema](docs/database.md)

## Status

🚧 Work in progress — see commit history for progress.
