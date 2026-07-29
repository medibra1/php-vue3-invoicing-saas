<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database\Connection;

/**
 * Like UserRepository, not tenant-scoped: refresh/logout only take the
 * raw token (never a JWT, so no tenant context to scope by). tenant_id
 * is still stored on each row for defense-in-depth and future admin
 * tooling ("revoke all sessions for this tenant"), just not used as a
 * filter here.
 */
final class RefreshTokenRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array{tenant_id: int, user_id: int, token_hash: string, expires_at: string} $data */
    public function create(array $data): int
    {
        return (int) $this->connection->table('refresh_tokens')->insert($data);
    }

    /**
     * @return array<string, mixed>|null The token row, only if it
     *         exists, hasn't been revoked, and hasn't expired.
     */
    public function findActiveByHash(string $hash): ?array
    {
        return $this->connection->table('refresh_tokens')
            ->where('token_hash', '=', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->first();
    }

    public function revoke(int $id): void
    {
        $this->connection->table('refresh_tokens')
            ->where('id', '=', $id)
            ->update(['revoked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }
}
