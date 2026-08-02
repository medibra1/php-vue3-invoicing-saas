<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\ActivityLog;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\ActivityLog\ActivityLogRepository;
use PHPUnit\Framework\TestCase;

final class ActivityLogRepositoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:', '', '');
        $this->connection->statement('
            CREATE TABLE activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER,
                action TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL,
                description TEXT NOT NULL
            )
        ');
    }

    private function repositoryFor(int $tenantId): ActivityLogRepository
    {
        return new ActivityLogRepository($this->connection, new CurrentTenant($tenantId, "Tenant {$tenantId}"));
    }

    public function testLogInsertsATenantScopedEntry(): void
    {
        $repo = $this->repositoryFor(1);

        $repo->log(7, 'invoice.status_changed', 'Invoice', 42, 'Invoice INV-2026-00001 moved to paid');

        $row = $this->connection->table('activity_logs')->first();
        self::assertSame(1, $row['tenant_id']);
        self::assertSame(7, $row['user_id']);
        self::assertSame('invoice.status_changed', $row['action']);
        self::assertSame('Invoice', $row['subject_type']);
        self::assertSame(42, $row['subject_id']);
        self::assertSame('Invoice INV-2026-00001 moved to paid', $row['description']);
    }

    public function testLogAcceptsANullUserId(): void
    {
        $repo = $this->repositoryFor(1);

        $repo->log(null, 'payment.recorded', 'Payment', 1, 'System payment');

        self::assertNull($this->connection->table('activity_logs')->first()['user_id']);
    }

    public function testPaginateOrdersNewestFirstAndScopesByTenant(): void
    {
        $tenant1 = $this->repositoryFor(1);
        $tenant2 = $this->repositoryFor(2);

        $tenant1->log(1, 'quote.status_changed', 'Quote', 1, 'first');
        $tenant1->log(1, 'quote.status_changed', 'Quote', 1, 'second');
        $tenant2->log(1, 'quote.status_changed', 'Quote', 1, 'other tenant');

        $page = $tenant1->paginate(1, 20);

        self::assertSame(2, $page['total']);
        self::assertCount(2, $page['items']);
        self::assertSame('second', $page['items'][0]['description']);
        self::assertSame('first', $page['items'][1]['description']);
    }

    public function testPaginateRespectsPageAndPerPage(): void
    {
        $repo = $this->repositoryFor(1);

        for ($i = 1; $i <= 5; $i++) {
            $repo->log(1, 'quote.status_changed', 'Quote', $i, "entry {$i}");
        }

        $page = $repo->paginate(2, 2);

        self::assertSame(5, $page['total']);
        self::assertSame(2, $page['page']);
        self::assertSame(2, $page['perPage']);
        self::assertCount(2, $page['items']);
        self::assertSame('entry 3', $page['items'][0]['description']);
        self::assertSame('entry 2', $page['items'][1]['description']);
    }

    public function testPaginateClampsInvalidPageAndPerPage(): void
    {
        $repo = $this->repositoryFor(1);
        $repo->log(1, 'quote.status_changed', 'Quote', 1, 'entry');

        $page = $repo->paginate(0, 500);

        self::assertSame(1, $page['page']);
        self::assertSame(100, $page['perPage']);
    }
}
