<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Team;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Auth\PermissionRepository;
use App\Modules\Auth\UserRepository;
use App\Modules\Team\TeamException;
use App\Modules\Team\TeamRepository;
use App\Modules\Team\TeamService;
use PHPUnit\Framework\TestCase;

final class TeamServiceTest extends TestCase
{
    private Connection $connection;
    private TeamService $team;

    protected function setUp(): void
    {
        $connection = $this->connection = new Connection('sqlite::memory:', '', '');

        $connection->statement('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                name TEXT NOT NULL, email TEXT NOT NULL, password_hash TEXT NOT NULL,
                deleted_at TEXT
            )
        ');
        $connection->statement('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL, name TEXT NOT NULL)');
        $connection->statement('
            CREATE TABLE user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL, role_id INTEGER NOT NULL
            )
        ');

        foreach (['owner', 'admin', 'accountant', 'viewer'] as $slug) {
            $connection->table('roles')->insert(['slug' => $slug, 'name' => ucfirst($slug)]);
        }

        $tenant = new CurrentTenant(1, 'Tenant 1');
        $users = new UserRepository($connection);

        $this->team = new TeamService(
            $connection,
            $tenant,
            new TeamRepository($connection, $tenant),
            $users,
            new PermissionRepository($connection, $tenant)
        );

        // Seed the tenant's owner, same as AuthService::register() would.
        $ownerId = $users->create([
            'tenant_id' => 1,
            'name' => 'Owner Person',
            'email' => 'owner@example.test',
            'password_hash' => password_hash('owner-password', PASSWORD_DEFAULT),
        ]);
        $ownerRole = $connection->table('roles')->where('slug', '=', 'owner')->first();
        $connection->table('user_roles')->insert(['tenant_id' => 1, 'user_id' => $ownerId, 'role_id' => $ownerRole['id']]);
    }

    public function testListReturnsMembersWithTheirRole(): void
    {
        $members = $this->team->list();

        self::assertCount(1, $members);
        self::assertSame('Owner Person', $members[0]['name']);
        self::assertSame('owner', $members[0]['role']);
    }

    public function testCreateAddsANewMemberWithTheGivenRole(): void
    {
        $member = $this->team->create([
            'name' => 'Teammate',
            'email' => 'teammate@example.test',
            'password' => 'a-strong-password',
            'role' => 'accountant',
        ]);

        self::assertSame('Teammate', $member['name']);
        self::assertSame('accountant', $member['role']);
        self::assertCount(2, $this->team->list());
    }

    public function testCreateRejectsADuplicateEmail(): void
    {
        $this->expectException(TeamException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $this->team->create([
            'name' => 'Duplicate',
            'email' => 'owner@example.test',
            'password' => 'a-strong-password',
            'role' => 'viewer',
        ]);
    }

    public function testCreateRejectsAnUnknownRole(): void
    {
        $this->expectException(TeamException::class);
        $this->expectExceptionMessage('Unknown role.');

        $this->team->create([
            'name' => 'Someone',
            'email' => 'someone@example.test',
            'password' => 'a-strong-password',
            'role' => 'superadmin',
        ]);
    }

    public function testCreateValidatesNameEmailAndPasswordLength(): void
    {
        $this->expectException(TeamException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters.');

        $this->team->create([
            'name' => 'Someone',
            'email' => 'someone@example.test',
            'password' => 'short',
            'role' => 'viewer',
        ]);
    }

    public function testListAndCreateAreTenantScoped(): void
    {
        $otherTenant = new CurrentTenant(2, 'Tenant 2');
        $otherTeam = new TeamService(
            $this->connection,
            $otherTenant,
            new TeamRepository($this->connection, $otherTenant),
            new UserRepository($this->connection),
            new PermissionRepository($this->connection, $otherTenant)
        );

        self::assertCount(0, $otherTeam->list());

        $otherTeam->create([
            'name' => 'Other Tenant Member',
            'email' => 'other-member@example.test',
            'password' => 'a-strong-password',
            'role' => 'viewer',
        ]);

        self::assertCount(1, $otherTeam->list());
        self::assertCount(1, $this->team->list());
    }
}
