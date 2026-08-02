<?php

declare(strict_types=1);

namespace App\Modules\Team;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;

/**
 * Reads the `users` table tenant-scoped (unlike Auth\UserRepository,
 * which is deliberately never tenant-scoped since login needs to find
 * a user by email before any tenant context exists) — this module only
 * ever operates on members of the *current* tenant.
 */
final class TeamRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentTenant $tenant
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listMembers(): array
    {
        return $this->connection->table('users')
            ->forTenant($this->tenant->id)
            ->whereNull('deleted_at')
            ->orderBy('id', 'ASC')
            ->get();
    }
}
