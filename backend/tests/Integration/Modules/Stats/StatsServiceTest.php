<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Stats;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Client\ClientRepository;
use App\Modules\Invoice\InvoiceRepository;
use App\Modules\Invoice\InvoiceService;
use App\Modules\Payment\PaymentRepository;
use App\Modules\Shared\DocumentNumberGenerator;
use App\Modules\Stats\StatsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StatsServiceTest extends TestCase
{
    private Connection $connection;
    private InvoiceService $invoiceService;
    private InvoiceRepository $invoices;
    private PaymentRepository $payments;
    private StatsService $stats;
    private int $clientId;

    protected function setUp(): void
    {
        $connection = $this->connection = new Connection('sqlite::memory:', '', '');

        $connection->statement('
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL,
                name TEXT NOT NULL, email TEXT, phone TEXT, address TEXT, deleted_at TEXT
            )
        ');
        $connection->statement('
            CREATE TABLE quotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, client_id INTEGER NOT NULL,
                number TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft",
                issue_date TEXT, expiry_date TEXT, notes TEXT, total TEXT NOT NULL DEFAULT "0.00", deleted_at TEXT
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
        $connection->statement('
            CREATE TABLE payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, invoice_id INTEGER NOT NULL,
                amount TEXT NOT NULL, method TEXT, paid_at TEXT, notes TEXT, deleted_at TEXT
            )
        ');

        $tenant = new CurrentTenant(1, 'Tenant 1');
        $clients = new ClientRepository($connection, $tenant);
        $this->clientId = $clients->create(['name' => 'Acme Buyer']);

        $this->invoices = new InvoiceRepository($connection, $tenant);
        $this->invoiceService = new InvoiceService(
            $connection,
            $this->invoices,
            $clients,
            new DocumentNumberGenerator($connection, $tenant)
        );
        $this->payments = new PaymentRepository($connection, $tenant);
        $this->stats = new StatsService($connection, $tenant, $this->payments);
    }

    /** @return array<string, mixed> A 'sent' invoice with the given total. */
    private function createSentInvoice(float $total): array
    {
        $invoice = $this->invoiceService->create([
            'client_id' => $this->clientId,
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => $total]],
        ]);

        return $this->invoiceService->transition((int) $invoice['id'], 'sent');
    }

    private function recordPayment(int $invoiceId, float $amount, string $paidAt): void
    {
        $this->payments->create(['invoice_id' => $invoiceId, 'amount' => $amount, 'paid_at' => $paidAt]);
    }

    public function testRevenueSeparatesThisMonthFromAllTime(): void
    {
        $invoice = $this->createSentInvoice(1000);
        $thisMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $lastMonth = (new DateTimeImmutable('first day of this month'))->modify('-1 month')->format('Y-m-d');

        $this->recordPayment((int) $invoice['id'], 300, $thisMonth);
        $this->recordPayment((int) $invoice['id'], 200, $lastMonth);

        $dashboard = $this->stats->dashboard();

        self::assertEqualsWithDelta(300.0, $dashboard['revenue']['thisMonth'], 0.001);
        self::assertEqualsWithDelta(500.0, $dashboard['revenue']['allTime'], 0.001);
    }

    public function testOverdueTotalIsTheRemainingBalanceNotTheFullInvoiceTotal(): void
    {
        $invoice = $this->createSentInvoice(1000);
        $this->recordPayment((int) $invoice['id'], 300, date('Y-m-d'));
        $this->invoices->update((int) $invoice['id'], ['status' => 'overdue']);

        $dashboard = $this->stats->dashboard();

        self::assertSame(1, $dashboard['overdue']['count']);
        self::assertEqualsWithDelta(700.0, $dashboard['overdue']['total'], 0.001);
    }

    public function testDraftQuotesCount(): void
    {
        $connection = $this->connection;
        $insertQuote = static function (int $tenantId, string $status) use ($connection): void {
            $connection->table('quotes')->insert([
                'tenant_id' => $tenantId,
                'client_id' => 1,
                'number' => 'QUO-TEST',
                'status' => $status,
                'total' => '0.00',
            ]);
        };

        $insertQuote(1, 'draft');
        $insertQuote(1, 'draft');
        $insertQuote(1, 'sent');

        self::assertSame(2, $this->stats->dashboard()['draftQuotes']);
    }

    public function testQuoteAcceptanceRateIgnoresDraftAndSentQuotes(): void
    {
        $connection = $this->connection;
        $insertQuote = static function (string $status) use ($connection): void {
            $connection->table('quotes')->insert([
                'tenant_id' => 1,
                'client_id' => 1,
                'number' => 'QUO-TEST',
                'status' => $status,
                'total' => '0.00',
            ]);
        };

        $insertQuote('draft');
        $insertQuote('sent');
        $insertQuote('accepted');
        $insertQuote('accepted');
        $insertQuote('rejected');

        // 2 accepted out of 3 decided (accepted+rejected) — draft/sent don't count as decided.
        self::assertEqualsWithDelta(2 / 3, $this->stats->dashboard()['quoteAcceptanceRate'], 0.0001);
    }

    public function testQuoteAcceptanceRateIsNullWhenNothingHasBeenDecidedYet(): void
    {
        self::assertNull($this->stats->dashboard()['quoteAcceptanceRate']);
    }

    public function testRevenueByMonthFillsGapsWithZeroAndCoversSixMonths(): void
    {
        $invoice = $this->createSentInvoice(1000);
        $thisMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $this->recordPayment((int) $invoice['id'], 250, $thisMonth);

        $series = $this->stats->dashboard()['revenueByMonth'];

        self::assertCount(6, $series);
        self::assertSame((new DateTimeImmutable('first day of this month'))->format('Y-m'), $series[5]['month']);
        self::assertEqualsWithDelta(250.0, $series[5]['revenue'], 0.001);
        self::assertEqualsWithDelta(0.0, $series[0]['revenue'], 0.001);
    }

    public function testDashboardIsTenantScoped(): void
    {
        $this->createSentInvoice(1000);
        $invoice = $this->invoiceService->create([
            'client_id' => $this->clientId,
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 500]],
        ]);
        $sent = $this->invoiceService->transition((int) $invoice['id'], 'sent');
        $this->recordPayment((int) $sent['id'], 500, date('Y-m-d'));

        $otherTenant = new CurrentTenant(2, 'Tenant 2');
        $otherStats = new StatsService($this->connection, $otherTenant, new PaymentRepository($this->connection, $otherTenant));

        $dashboard = $otherStats->dashboard();

        self::assertSame(0.0, $dashboard['revenue']['allTime']);
        self::assertSame(0, $dashboard['draftQuotes']);
    }
}
