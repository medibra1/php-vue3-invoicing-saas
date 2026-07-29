<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database\Connection;
use App\Modules\Tenant\TenantRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Business logic for register/login/refresh/logout. AuthController is a
 * thin HTTP layer on top of this — no Request/Response object reaches
 * this far in, so it stays testable (and reusable, e.g. from a future
 * CLI admin tool) without any HTTP scaffolding.
 */
final class AuthService
{
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    public function __construct(
        private readonly Connection $connection,
        private readonly UserRepository $users,
        private readonly TenantRepository $tenants,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly JwtEncoder $jwtEncoder
    ) {
    }

    /**
     * Signs up a new tenant with its first user, who becomes 'owner'.
     * This is InvoicePro's only self-serve entry point — inviting
     * teammates into an existing tenant is separate, later work.
     *
     * @return array{accessToken: string, refreshToken: string, user: array<string, mixed>}
     * @throws AuthException 409 if the email is already registered, 422 on invalid input.
     */
    public function register(string $tenantName, string $name, string $email, string $password): array
    {
        $this->assertValidRegistration($tenantName, $name, $email, $password);

        if ($this->users->emailExists($email)) {
            throw new AuthException('An account with this email already exists.', 409);
        }

        $user = $this->connection->transaction(function () use ($tenantName, $name, $email, $password): array {
            $tenantId = $this->tenants->create(['name' => $tenantName]);

            $userId = $this->users->create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $ownerRole = $this->connection->table('roles')->where('slug', '=', 'owner')->first();

            if ($ownerRole === null) {
                throw new RuntimeException('Role [owner] is not seeded — run bin/seed.php.');
            }

            $this->connection->table('user_roles')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $ownerRole['id'],
            ]);

            return $this->users->findById($userId) ?? throw new RuntimeException('Just-inserted user vanished.');
        });

        return $this->issueTokensFor($user);
    }

    /**
     * @return array{accessToken: string, refreshToken: string, user: array<string, mixed>}
     * @throws AuthException 401 on bad credentials, 403 if the tenant is inactive.
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            // Same message for "no such user" and "wrong password" —
            // distinguishing them lets an attacker enumerate accounts.
            throw new AuthException('Invalid email or password.', 401);
        }

        if ($this->tenants->findActiveById((int) $user['tenant_id']) === null) {
            throw new AuthException('This account is no longer active.', 403);
        }

        return $this->issueTokensFor($user);
    }

    /**
     * Rotates the refresh token on every use (old one revoked, new one
     * issued) rather than reusing it — limits how long a stolen refresh
     * token stays useful to a single request.
     *
     * @return array{accessToken: string, refreshToken: string, user: array<string, mixed>}
     * @throws AuthException 401 if the token is invalid/expired/revoked, 403 if the tenant is inactive.
     */
    public function refresh(string $refreshToken): array
    {
        $row = $this->refreshTokens->findActiveByHash(hash('sha256', $refreshToken));

        if ($row === null) {
            throw new AuthException('Invalid or expired refresh token.', 401);
        }

        $user = $this->users->findById((int) $row['user_id']);

        if ($user === null || $this->tenants->findActiveById((int) $user['tenant_id']) === null) {
            throw new AuthException('This account is no longer active.', 403);
        }

        $this->refreshTokens->revoke((int) $row['id']);

        return $this->issueTokensFor($user);
    }

    /**
     * Idempotent on purpose: whether the token existed or not, the
     * caller gets the same (no) response — logout isn't a place to leak
     * whether a given refresh token was ever valid.
     */
    public function logout(string $refreshToken): void
    {
        $row = $this->refreshTokens->findActiveByHash(hash('sha256', $refreshToken));

        if ($row !== null) {
            $this->refreshTokens->revoke((int) $row['id']);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array{accessToken: string, refreshToken: string, user: array<string, mixed>}
     */
    private function issueTokensFor(array $user): array
    {
        $accessToken = $this->jwtEncoder->encode([
            'sub' => (int) $user['id'],
            'tenant_id' => (int) $user['tenant_id'],
        ]);

        $rawRefreshToken = bin2hex(random_bytes(32));

        $this->refreshTokens->create([
            'tenant_id' => (int) $user['tenant_id'],
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $rawRefreshToken),
            'expires_at' => (new DateTimeImmutable('+' . self::REFRESH_TOKEN_TTL_DAYS . ' days'))
                ->format('Y-m-d H:i:s'),
        ]);

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $rawRefreshToken,
            'user' => [
                'id' => (int) $user['id'],
                'tenantId' => (int) $user['tenant_id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
        ];
    }

    /** @throws AuthException 422 on invalid input */
    private function assertValidRegistration(string $tenantName, string $name, string $email, string $password): void
    {
        if (trim($tenantName) === '' || trim($name) === '') {
            throw new AuthException('Company name and your name are required.', 422);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AuthException('A valid email address is required.', 422);
        }

        if (strlen($password) < 8) {
            throw new AuthException('Password must be at least 8 characters.', 422);
        }
    }
}
