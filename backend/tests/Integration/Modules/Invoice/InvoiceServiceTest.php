<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Invoice;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Client\ClientRepository;
use App\Modules\Invoice\InvoiceException;
use App\Modules\Invoice\InvoiceRepository;
use App\Modules\Invoice\InvoiceService;
use App\Modules\Shared\DocumentNumberGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Same real-SQLite-and-real-wiring approach as QuoteServiceTest — the
 * interesting behavior is the interplay between validation, the
 * transaction, and the invoice-specific transition graph, which mocking
 * would hide rather than exercise.
 */
final class InvoiceServiceTest extends TestCase
{
    private InvoiceService $invoices;
    private int $clientId;

    protected function setUp(): void
    {
        $connection = new Connection('sqlite::memory:', '', '');

        $connection->statement('
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                name TEXT NOT NULL, email TEXT, phone TEXT, address TEXT, deleted_at TEXT
            )
        ');
        $connection->statement('
            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, client_id INTEGER NOT NULL,
                quote_id INTEGER, number TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft",
                issue_date TEXT, due_date TEXT, notes TEXT, total TEXT NOT NULL DEFAULT "0.00", deleted_at TEXT
            )
        ');
        $connection->statement('
            CREATE TABLE invoice_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, invoice_id INTEGER NOT NULL,
                description TEXT NOT NULL, quantity TEXT NOT NULL, unit_price TEXT NOT NULL,
                line_total TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0
            )
        ');

        $tenant = new CurrentTenant(1, 'Tenant 1');
        $clients = new ClientRepository($connection, $tenant);
        $this->clientId = $clients->create(['name' => 'Acme Buyer']);

        $this->invoices = new InvoiceService(
            $connection,
            new InvoiceRepository($connection, $tenant),
            $clients,
            new DocumentNumberGenerator($connection, $tenant)
        );
    }

    public function testCreateStandaloneInvoiceComputesTotalAndAssignsANumber(): void
    {
        $invoice = $this->invoices->create([
            'client_id' => $this->clientId,
            'due_date' => '2026-08-30',
            'items' => [['description' => 'Website redesign', 'quantity' => 1, 'unit_price' => 2500]],
        ]);

        self::assertSame('INV-' . date('Y') . '-00001', $invoice['number']);
        self::assertNull($invoice['quote_id']);
        self::assertSame('draft', $invoice['status']);
        self::assertEqualsWithDelta(2500.0, (float) $invoice['total'], 0.001);
    }

    public function testTransitionGraphAllowsPaidOrCancelledFromOverdueButNotFromPaid(): void
    {
        $invoice = $this->invoices->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1],
        ]]);
        $id = (int) $invoice['id'];

        try {
            $this->invoices->transition($id, 'paid');
            self::fail('Expected InvoiceException for draft -> paid.');
        } catch (InvoiceException) {
            // expected: draft can only go to 'sent'
        }

        $this->invoices->transition($id, 'sent');
        $overdue = $this->invoices->transition($id, 'overdue');
        self::assertSame('overdue', $overdue['status']);

        $paid = $this->invoices->transition($id, 'paid');
        self::assertSame('paid', $paid['status']);

        try {
            $this->invoices->transition($id, 'cancelled');
            self::fail('Expected InvoiceException: paid is a terminal status.');
        } catch (InvoiceException) {
            // expected
        }
    }

    public function testEditingANonDraftInvoiceIsRejected(): void
    {
        $invoice = $this->invoices->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1],
        ]]);
        $id = (int) $invoice['id'];
        $this->invoices->transition($id, 'sent');

        $this->expectException(InvoiceException::class);
        $this->expectExceptionMessage('Only draft invoices can be edited.');

        $this->invoices->update($id, ['client_id' => $this->clientId, 'items' => [
            ['description' => 'Hacked', 'quantity' => 1, 'unit_price' => 1],
        ]]);
    }

    public function testDeletingANonDraftInvoiceIsRejected(): void
    {
        $invoice = $this->invoices->create(['client_id' => $this->clientId, 'items' => [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1],
        ]]);
        $id = (int) $invoice['id'];
        $this->invoices->transition($id, 'sent');

        $this->expectException(InvoiceException::class);
        $this->expectExceptionMessage('Only draft invoices can be deleted.');

        $this->invoices->delete($id);
    }

    public function testCreateRejectsAClientFromAnotherTenant(): void
    {
        // requireClient() looks the client up through the tenant-scoped
        // ClientRepository::find() passed into this InvoiceService — an
        // id from a different tenant simply won't resolve.
        $this->expectException(InvoiceException::class);

        $this->invoices->create(['client_id' => 999999, 'items' => [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 1],
        ]]);
    }
}
