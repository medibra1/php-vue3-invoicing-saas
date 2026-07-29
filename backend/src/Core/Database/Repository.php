<?php

declare(strict_types=1);

namespace App\Core\Database;

use DateTimeImmutable;

/**
 * Base class for every tenant-scoped repository (ClientRepository,
 * InvoiceRepository, ...). query() is the only way a subclass gets a
 * QueryBuilder, and it's always already scoped via forTenant() — a
 * subclass has no way to accidentally query across tenants, because it
 * never gets a hold of an unscoped builder to begin with. This is the
 * payoff of the CurrentTenant/forTenant() mechanism described in
 * QueryBuilder's class doc.
 *
 * Not every repository extends this — TenantRepository, UserRepository
 * and RefreshTokenRepository all have a legitimate reason not to (see
 * their own class docs): querying a table that isn't tenant-scoped, or
 * needing to look a row up *before* a tenant context exists.
 */
abstract class Repository
{
    public function __construct(
        protected readonly Connection $connection,
        protected readonly CurrentTenant $tenant
    ) {
    }

    /** The table this repository manages, e.g. 'clients'. */
    abstract protected function table(): string;

    protected function query(): QueryBuilder
    {
        return $this->connection->table($this->table())->forTenant($this->tenant->id);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->query()->where('id', '=', $id)->whereNull('deleted_at')->first();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->query()->whereNull('deleted_at')->orderBy('id', 'DESC')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return (int) $this->query()->insert($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return int Rows affected (0 if $id doesn't belong to this tenant or doesn't exist).
     */
    public function update(int $id, array $data): int
    {
        return $this->query()->where('id', '=', $id)->update($data);
    }

    /**
     * Soft delete — sets deleted_at, never a hard DELETE for business
     * entities (CLAUDE.md decision: accounting data is never destroyed).
     *
     * @return int Rows affected (0 if $id doesn't belong to this tenant or doesn't exist).
     */
    public function delete(int $id): int
    {
        return $this->query()->where('id', '=', $id)->update([
            'deleted_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
