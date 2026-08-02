<?php

declare(strict_types=1);

namespace App\Modules\Team;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Auth\PermissionRepository;
use App\Modules\Auth\UserRepository;

/**
 * Adds a teammate to the *current* tenant directly (name/email/password/
 * role in one call) rather than a token-based email invitation — this
 * project has no mailer/SMTP integration, consistent with every other
 * "no exotic external service" choice made throughout. The new account
 * is usable immediately; the temporary password is communicated
 * out-of-band by whoever created it.
 */
final class TeamService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentTenant $tenant,
        private readonly TeamRepository $team,
        private readonly UserRepository $users,
        private readonly PermissionRepository $permissions
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return array_map(fn (array $user): array => $this->present($user), $this->team->listMembers());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws TeamException 409 if the email is already registered, 422 on invalid input.
     */
    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $roleSlug = (string) ($data['role'] ?? '');

        $this->assertValid($name, $email, $password);

        if ($this->users->emailExists($email)) {
            throw new TeamException('An account with this email already exists.', 409);
        }

        $role = $this->connection->table('roles')->where('slug', '=', $roleSlug)->first();

        if ($role === null) {
            throw new TeamException('Unknown role.');
        }

        $userId = $this->connection->transaction(function () use ($name, $email, $password, $role) {
            $userId = $this->users->create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $this->connection->table('user_roles')->insert([
                'tenant_id' => $this->tenant->id,
                'user_id' => $userId,
                'role_id' => $role['id'],
            ]);

            return $userId;
        });

        return $this->present($this->users->findById($userId) ?? throw new \RuntimeException('Just-inserted user vanished.'));
    }

    /** @throws TeamException 422 on invalid input. */
    private function assertValid(string $name, string $email, string $password): void
    {
        if ($name === '') {
            throw new TeamException('Name is required.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new TeamException('A valid email address is required.');
        }

        if (strlen($password) < 8) {
            throw new TeamException('Password must be at least 8 characters.');
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function present(array $user): array
    {
        $roles = $this->permissions->roleSlugsForUser((int) $user['id']);

        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $roles[0] ?? null,
        ];
    }
}
