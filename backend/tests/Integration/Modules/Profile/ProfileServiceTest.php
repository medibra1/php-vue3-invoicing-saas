<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Profile;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Auth\PermissionRepository;
use App\Modules\Auth\UserRepository;
use App\Modules\Profile\AvatarService;
use App\Modules\Profile\ProfileException;
use App\Modules\Profile\ProfileService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class ProfileServiceTest extends TestCase
{
    private Connection $connection;
    private ProfileService $profile;
    private string $storagePath;
    private int $userId;

    protected function setUp(): void
    {
        $connection = $this->connection = new Connection('sqlite::memory:', '', '');

        $connection->statement('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                name TEXT NOT NULL, email TEXT NOT NULL, password_hash TEXT NOT NULL,
                avatar_path TEXT, deleted_at TEXT
            )
        ');
        $connection->statement('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL, name TEXT NOT NULL)');
        $connection->statement('
            CREATE TABLE user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL, role_id INTEGER NOT NULL
            )
        ');

        $tenant = new CurrentTenant(1, 'Tenant 1');
        $users = new UserRepository($connection);
        $this->userId = $users->create([
            'tenant_id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'password_hash' => password_hash('old-password', PASSWORD_DEFAULT),
        ]);

        $roleId = (int) $connection->table('roles')->insert(['slug' => 'owner', 'name' => 'Owner']);
        $connection->table('user_roles')->insert(['tenant_id' => 1, 'user_id' => $this->userId, 'role_id' => $roleId]);

        $this->storagePath = sys_get_temp_dir() . '/invoicepro-avatars-' . uniqid();
        $avatars = new AvatarService($this->storagePath, 'http://localhost:8000/uploads/avatars');

        $this->profile = new ProfileService($users, $avatars, new PermissionRepository($connection, $tenant));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storagePath)) {
            $this->removeDirectory($this->storagePath);
        }
    }

    public function testShowReturnsThePresentedProfileIncludingRole(): void
    {
        $profile = $this->profile->show($this->userId);

        self::assertSame('Jane Doe', $profile['name']);
        self::assertSame('jane@example.test', $profile['email']);
        self::assertNull($profile['avatarUrl']);
        self::assertSame('owner', $profile['role']);
    }

    public function testUpdateChangesTheNameAndRejectsABlankOne(): void
    {
        $updated = $this->profile->update($this->userId, ['name' => 'Jane Smith']);
        self::assertSame('Jane Smith', $updated['name']);

        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage('Name is required.');

        $this->profile->update($this->userId, ['name' => '   ']);
    }

    public function testUploadAvatarStoresTheFileAndReturnsItsUrl(): void
    {
        $updated = $this->profile->uploadAvatar($this->userId, $this->makePngUpload());

        self::assertNotNull($updated['avatarUrl']);
        self::assertStringContainsString("/1/{$this->userId}.png", $updated['avatarUrl']);
    }

    public function testDeleteAvatarClearsIt(): void
    {
        $this->profile->uploadAvatar($this->userId, $this->makePngUpload());
        $updated = $this->profile->deleteAvatar($this->userId);

        self::assertNull($updated['avatarUrl']);
    }

    public function testChangePasswordRequiresTheCorrectCurrentPassword(): void
    {
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage('Current password is incorrect.');

        $this->profile->changePassword($this->userId, [
            'current_password' => 'wrong-password',
            'new_password' => 'a-new-password-123',
        ]);
    }

    public function testChangePasswordRejectsAShortNewPassword(): void
    {
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage('New password must be at least 8 characters.');

        $this->profile->changePassword($this->userId, [
            'current_password' => 'old-password',
            'new_password' => 'short',
        ]);
    }

    public function testChangePasswordSucceedsWithTheCorrectCurrentPassword(): void
    {
        $this->profile->changePassword($this->userId, [
            'current_password' => 'old-password',
            'new_password' => 'a-new-password-123',
        ]);

        $row = $this->connection->table('users')->where('id', '=', $this->userId)->first();
        self::assertTrue(password_verify('a-new-password-123', $row['password_hash']));
    }

    private function makePngUpload(): UploadedFile
    {
        $image = imagecreatetruecolor(300, 300);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return new UploadedFile(
            (new Psr17Factory())->createStream($bytes),
            strlen($bytes),
            UPLOAD_ERR_OK,
            'avatar.png',
            'image/png'
        );
    }

    private function removeDirectory(string $path): void
    {
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = "{$path}/{$item}";
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
