<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use Firebase\JWT\JWT;

/**
 * Signs application claims (user id, tenant id, RBAC roles/permissions)
 * into a JWT. Pure encoding: this class knows nothing about where the
 * claims come from — AuthService (Phase 1) is the caller, on
 * login/register/refresh.
 */
final class JwtEncoder
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm = 'HS256',
        private readonly int $ttlSeconds = 3600
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            env('JWT_SECRET'),
            env('JWT_ALGO', 'HS256'),
            (int) env('JWT_TTL', '3600')
        );
    }

    /**
     * @param array<string, mixed> $claims Application claims, e.g.
     *        ['sub' => $userId, 'tenant_id' => $tenantId, 'permissions' => [...]].
     *        `iat`/`exp` are added automatically; any caller-supplied
     *        values for them are overridden — expiry is this class's
     *        job, not the caller's.
     */
    public function encode(array $claims): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ]);

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }
}
