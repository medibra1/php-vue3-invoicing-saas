<?php

declare(strict_types=1);

namespace App\Modules\Tenant;

use App\Core\Database\Connection;

/**
 * The `tenants` table isn't itself tenant-scoped (it *is* the tenant),
 * so this is one of the few repositories that never calls
 * QueryBuilder::forTenant().
 */
final class TenantRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>|null Null if the tenant doesn't exist
     *         or has been soft-deleted — both treated identically by
     *         TenantResolverMiddleware (403, not a data leak via 404).
     */
    public function findActiveById(int $id): ?array
    {
        return $this->connection->table('tenants')
            ->where('id', '=', $id)
            ->whereNull('deleted_at')
            ->first();
    }
}
