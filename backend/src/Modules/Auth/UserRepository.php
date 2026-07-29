<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database\Connection;

/**
 * Deliberately never scoped via QueryBuilder::forTenant(): login looks
 * a user up by email *before* any tenant context exists (email is
 * globally unique — see the users migration), so this repository can't
 * be tenant-scoped the way ClientRepository/InvoiceRepository etc. will
 * be once the base Repository class exists.
 */
final class UserRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->connection->table('users')
            ->where('email', '=', $email)
            ->whereNull('deleted_at')
            ->first();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->connection->table('users')
            ->where('id', '=', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    public function emailExists(string $email): bool
    {
        return $this->connection->table('users')->where('email', '=', $email)->count() > 0;
    }

    /** @param array{tenant_id: int, name: string, email: string, password_hash: string} $data */
    public function create(array $data): int
    {
        return (int) $this->connection->table('users')->insert($data);
    }
}
