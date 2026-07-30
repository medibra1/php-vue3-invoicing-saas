<?php

declare(strict_types=1);

namespace App\Modules\Profile;

use App\Modules\Auth\PermissionRepository;
use App\Modules\Auth\UserRepository;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Operates on the *current* authenticated user (id resolved from the
 * JWT's `sub` claim by ProfileController) rather than being tenant-
 * scoped like the business-data services — there's no id parameter
 * taken from the URL, a user can only ever act on themselves here.
 */
final class ProfileService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AvatarService $avatars,
        private readonly PermissionRepository $permissions
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws ProfileException 404 if the user vanished.
     */
    public function show(int $userId): array
    {
        return $this->present($this->requireUser($userId));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ProfileException 422 if the name is blank.
     */
    public function update(int $userId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new ProfileException('Name is required.');
        }

        $this->users->update($userId, ['name' => $name]);

        return $this->present($this->requireUser($userId));
    }

    /**
     * @return array<string, mixed>
     * @throws ProfileException 422 on an invalid file, 404 if the user vanished.
     */
    public function uploadAvatar(int $userId, UploadedFileInterface $file): array
    {
        $user = $this->requireUser($userId);

        $path = $this->avatars->store($file, (int) $user['tenant_id'], $userId, $user['avatar_path'] ?? null);
        $this->users->update($userId, ['avatar_path' => $path]);

        return $this->present($this->requireUser($userId));
    }

    /**
     * @return array<string, mixed>
     * @throws ProfileException 404 if the user vanished.
     */
    public function deleteAvatar(int $userId): array
    {
        $user = $this->requireUser($userId);

        $this->avatars->delete($user['avatar_path'] ?? null);
        $this->users->update($userId, ['avatar_path' => null]);

        return $this->present($this->requireUser($userId));
    }

    /**
     * @param array<string, mixed> $data
     * @throws ProfileException 404 if the user vanished, 422 if the current
     *         password is wrong or the new one is too short.
     */
    public function changePassword(int $userId, array $data): void
    {
        $user = $this->requireUser($userId);
        $current = (string) ($data['current_password'] ?? '');
        $new = (string) ($data['new_password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            throw new ProfileException('Current password is incorrect.');
        }

        if (strlen($new) < 8) {
            throw new ProfileException('New password must be at least 8 characters.');
        }

        $this->users->update($userId, ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
    }

    /** @return array<string, mixed> */
    private function requireUser(int $userId): array
    {
        return $this->users->findById($userId) ?? throw new ProfileException('User not found.', 404);
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
            'tenantId' => (int) $user['tenant_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatarUrl' => $this->avatars->urlFor($user['avatar_path'] ?? null),
            'role' => $roles[0] ?? null,
        ];
    }
}
