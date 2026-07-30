<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Payment;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Client\ClientRepository;
use App\Modules\Invoice\InvoiceRepository;
use App\Modules\Invoice\InvoiceService;
use App\Modules\Payment\PaymentException;
use App\Modules\Payment\PaymentRepository;
use App\Modules\Payment\PaymentService;
use App\Modules\Shared\DocumentNumberGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Same real-SQLite-and-real-wiring approach as InvoiceServiceTest — the
 * interesting behavior is the balance computation and the invoice
 * auto-transition it drives, which mocking would hide rather than
 * exercise.
 */
final class PaymentServiceTest extends TestCase
{
    private Connection $connection;
    private InvoiceService $invoiceService;
    private InvoiceRepository $invoices;
    private PaymentService $payments;
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
        $this->payments = new PaymentService($connection, new PaymentRepository($connection, $tenant), $this->invoices);
    }

    /** @return array<string, mixed> A 'sent' invoice with a total of 1000.00. */
    private function createSentInvoice(): array
    {
        $invoice = $this->invoiceService->create([
            'client_id' => $this->clientId,
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000]],
        ]);

        return $this->invoiceService->transition((int) $invoice['id'], 'sent');
    }

    public function testAPartialPaymentMovesTheInvoiceToPartiallyPaid(): void
    {
        $invoice = $this->createSentInvoice();

        $payment = $this->payments->create((int) $invoice['id'], ['amount' => 400]);

        self::assertEqualsWithDelta(400.0, (float) $payment['amount'], 0.001);
        self::assertSame('partially_paid', $this->invoices->find((int) $invoice['id'])['status']);
    }

    public function testASecondPaymentCoveringTheRestMovesTheInvoiceToPaid(): void
    {
        $invoice = $this->createSentInvoice();

        $this->payments->create((int) $invoice['id'], ['amount' => 400]);
        $this->payments->create((int) $invoice['id'], ['amount' => 600]);

        self::assertSame('paid', $this->invoices->find((int) $invoice['id'])['status']);
        self::assertCount(2, $this->payments->listForInvoice((int) $invoice['id']));
    }

    public function testAFullPaymentInOneShotMovesDirectlyToPaid(): void
    {
        $invoice = $this->createSentInvoice();

        $this->payments->create((int) $invoice['id'], ['amount' => 1000]);

        self::assertSame('paid', $this->invoices->find((int) $invoice['id'])['status']);
    }

    public function testAPaymentExceedingTheRemainingBalanceIsRejected(): void
    {
        $invoice = $this->createSentInvoice();
        $this->payments->create((int) $invoice['id'], ['amount' => 700]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment amount exceeds the remaining balance of 300.00.');

        $this->payments->create((int) $invoice['id'], ['amount' => 301]);
    }

    public function testAPaymentAgainstADraftInvoiceIsRejected(): void
    {
        $invoice = $this->invoiceService->create([
            'client_id' => $this->clientId,
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000]],
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Cannot record a payment against a draft or cancelled invoice.');

        $this->payments->create((int) $invoice['id'], ['amount' => 100]);
    }

    public function testAPaymentAgainstAnAlreadyPaidInvoiceIsRejected(): void
    {
        $invoice = $this->createSentInvoice();
        $this->payments->create((int) $invoice['id'], ['amount' => 1000]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Invoice is already fully paid.');

        $this->payments->create((int) $invoice['id'], ['amount' => 1]);
    }

    public function testANonPositiveAmountIsRejected(): void
    {
        $invoice = $this->createSentInvoice();

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->payments->create((int) $invoice['id'], ['amount' => 0]);
    }

    public function testRecordingAPaymentAgainstAnInvoiceFromAnotherTenantIsRejected(): void
    {
        $invoice = $this->createSentInvoice();

        $otherTenant = new CurrentTenant(2, 'Tenant 2');
        $otherPayments = new PaymentService(
            $this->connection,
            new PaymentRepository($this->connection, $otherTenant),
            new InvoiceRepository($this->connection, $otherTenant)
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Invoice not found.');

        $otherPayments->create((int) $invoice['id'], ['amount' => 100]);
    }
}
