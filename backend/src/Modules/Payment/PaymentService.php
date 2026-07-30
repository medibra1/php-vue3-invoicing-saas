<?php

declare(strict_types=1);

namespace App\Modules\Payment;

use App\Core\Database\Connection;
use App\Modules\Invoice\InvoiceRepository;
use DateTimeImmutable;

/**
 * Records payments against an invoice and drives the invoice's
 * payment-side status transitions (sent/overdue -> partially_paid ->
 * paid), per CLAUDE.md's "Payment ↔ invoice status" decision. Writes
 * directly through InvoiceRepository (not InvoiceService) inside its own
 * transaction — same cross-module shape as Quote\QuoteToInvoiceConverter,
 * so this module doesn't need to depend on Invoice's service layer, only
 * its repository.
 */
final class PaymentService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PaymentRepository $payments,
        private readonly InvoiceRepository $invoices
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws PaymentException 404 if the invoice doesn't exist (or
     *         belongs to another tenant), 422 if the invoice can't take a
     *         payment right now or the amount is invalid.
     */
    public function create(int $invoiceId, array $data): array
    {
        $invoice = $this->requireInvoice($invoiceId);

        if (in_array($invoice['status'], ['draft', 'cancelled'], true)) {
            throw new PaymentException('Cannot record a payment against a draft or cancelled invoice.');
        }

        if ($invoice['status'] === 'paid') {
            throw new PaymentException('Invoice is already fully paid.');
        }

        $amount = $this->validatedAmount($data['amount'] ?? null);
        $remaining = round((float) $invoice['total'] - $this->payments->sumForInvoice($invoiceId), 2);

        if ($amount > $remaining + 0.01) {
            throw new PaymentException(sprintf('Payment amount exceeds the remaining balance of %.2f.', $remaining));
        }

        $paymentId = $this->connection->transaction(function () use ($invoiceId, $amount, $data, $remaining) {
            $id = (int) $this->payments->create([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'method' => $this->nullableString($data['method'] ?? null),
                'paid_at' => $this->stringOrDefault($data['paid_at'] ?? null, (new DateTimeImmutable())->format('Y-m-d')),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);

            $newStatus = $amount >= $remaining - 0.01 ? 'paid' : 'partially_paid';
            $this->invoices->update($invoiceId, ['status' => $newStatus]);

            return $id;
        });

        return $this->payments->find($paymentId) ?? throw new \RuntimeException('Just-created payment vanished.');
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws PaymentException 404 if the invoice doesn't exist (or belongs to another tenant).
     */
    public function listForInvoice(int $invoiceId): array
    {
        $this->requireInvoice($invoiceId);

        return $this->payments->allForInvoice($invoiceId);
    }

    /** @return array<string, mixed> */
    private function requireInvoice(int $id): array
    {
        $invoice = $this->invoices->find($id);

        if ($invoice === null) {
            throw new PaymentException('Invoice not found.', 404);
        }

        return $invoice;
    }

    private function validatedAmount(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new PaymentException('Payment amount must be a number.');
        }

        $amount = round((float) $value, 2);

        if ($amount <= 0) {
            throw new PaymentException('Payment amount must be greater than zero.');
        }

        return $amount;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
