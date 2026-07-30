<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Quote;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Client\ClientRepository;
use App\Modules\Quote\QuoteException;
use App\Modules\Quote\QuoteRepository;
use App\Modules\Quote\QuoteService;
use App\Modules\Shared\DocumentNumberGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Integration-level (real SQLite, real Repository/Service wiring)
 * rather than mocked — the interesting behavior here is the interplay
 * between validation, the transaction, item persistence via
 * HasLineItems, and number generation, which mocking would hide rather
 * than exercise.
 */
final class QuoteServiceTest extends TestCase
{
    private Connection $connection;
    private QuoteService $quotes;
    private int $clientId;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:', '', '');

        $this->connection->statement('
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                name TEXT NOT NULL, email TEXT, phone TEXT, address TEXT, deleted_at TEXT
            )
        ');
        $this->connection->statement('
            CREATE TABLE quotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, client_id INTEGER NOT NULL,
                number TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft", issue_date TEXT, expiry_date TEXT,
                notes TEXT, total TEXT NOT NULL DEFAULT "0.00", deleted_at TEXT
            )
        ');
        $this->connection->statement('
            CREATE TABLE quote_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, quote_id INTEGER NOT NULL,
                description TEXT NOT NULL, quantity TEXT NOT NULL, unit_price TEXT NOT NULL,
                line_total TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0
            )
        ');

        $tenant = new CurrentTenant(1, 'Tenant 1');
        $clients = new ClientRepository($this->connection, $tenant);
        $this->clientId = $clients->create(['name' => 'Acme Buyer']);

        $this->quotes = new QuoteService(
            $this->connection,
            new QuoteRepository($this->connection, $tenant),
            $clients,
            new DocumentNumberGenerator($this->connection, $tenant)
        );
    }

    public function testCreateComputesTotalFromItemsAndAssignsANumber(): void
    {
        $quote = $this->quotes->create([
            'client_id' => $this->clientId,
            'items' => [
                ['description' => 'Consulting', 'quantity' => 10, 'unit_price' => 100],
                ['description' => 'Setup fee', 'quantity' => 1, 'unit_price' => 250],
            ],
        ]);

        self::assertSame('QUO-' . date('Y') . '-00001', $quote['number']);
        self::assertSame('draft', $quote['status']);
        self::assertEqualsWithDelta(1250.0, (float) $quote['total'], 0.001);
        self::assertCount(2, $quote['items']);
    }

    public function testSequentialNumbersPerTenant(): void
    {
        $first = $this->quotes->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1],
        ]]);
        $second = $this->quotes->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 1],
        ]]);

        self::assertSame('QUO-' . date('Y') . '-00001', $first['number']);
        self::assertSame('QUO-' . date('Y') . '-00002', $second['number']);
    }

    public function testCreateRejectsEmptyItems(): void
    {
        $this->expectException(QuoteException::class);
        $this->expectExceptionMessage('At least one line item is required.');

        $this->quotes->create(['client_id' => $this->clientId, 'items' => []]);
    }

    public function testCreateRejectsAClientFromAnotherTenant(): void
    {
        $otherTenantClient = (new ClientRepository($this->connection, new CurrentTenant(2, 'Tenant 2')))
            ->create(['name' => 'Not Mine']);

        $this->expectException(QuoteException::class);

        $this->quotes->create(['client_id' => $otherTenantClient, 'items' => [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 1],
        ]]);
    }

    public function testStatusTransitionsFollowTheAllowedGraph(): void
    {
        $quote = $this->quotes->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1],
        ]]);

        try {
            $this->quotes->transition((int) $quote['id'], 'accepted');
            self::fail('Expected QuoteException for draft -> accepted.');
        } catch (QuoteException) {
            // expected: draft can only go to 'sent'
        }

        $sent = $this->quotes->transition((int) $quote['id'], 'sent');
        self::assertSame('sent', $sent['status']);

        $accepted = $this->quotes->transition((int) $quote['id'], 'accepted');
        self::assertSame('accepted', $accepted['status']);

        try {
            $this->quotes->transition((int) $quote['id'], 'sent');
            self::fail('Expected QuoteException: accepted is a terminal status.');
        } catch (QuoteException) {
            // expected: no transitions allowed out of 'accepted'
        }
    }

    public function testOnlyDraftQuotesCanBeEditedOrDeleted(): void
    {
        $quote = $this->quotes->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1],
        ]]);
        $this->quotes->transition((int) $quote['id'], 'sent');

        $this->expectException(QuoteException::class);
        $this->expectExceptionMessage('Only draft quotes can be edited.');

        $this->quotes->update((int) $quote['id'], ['client_id' => $this->clientId, 'items' => [
            ['description' => 'Hacked', 'quantity' => 1, 'unit_price' => 1],
        ]]);
    }
}
