<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Core\Database\Connection;
use App\Modules\Client\ClientRepository;
use App\Modules\Shared\DocumentNumberGenerator;
use DateTimeImmutable;

/**
 * Same shape as Quote\QuoteService — direct invoice creation (not just
 * via QuoteToInvoiceConverter, since "invoice CRUD" is a standalone
 * roadmap item: a freelancer can bill a client with no quote on file).
 */
final class InvoiceService
{
    /** @var array<string, string[]> Allowed next statuses per current status. */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['sent'],
        'sent' => ['paid', 'overdue', 'cancelled'],
        'overdue' => ['paid', 'cancelled'],
        'paid' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly InvoiceRepository $invoices,
        private readonly ClientRepository $clients,
        private readonly DocumentNumberGenerator $numbers
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws InvoiceException 422 if the client doesn't exist (or belongs to another tenant) or items are invalid.
     */
    public function create(array $data): array
    {
        $client = $this->requireClient($data['client_id'] ?? null);
        $items = $this->validatedItems(is_array($data['items'] ?? null) ? $data['items'] : []);

        $invoiceId = $this->connection->transaction(function () use ($data, $client, $items) {
            $id = (int) $this->invoices->create([
                'client_id' => $client['id'],
                'quote_id' => null,
                'number' => $this->numbers->next('invoices', 'INV'),
                'status' => 'draft',
                'issue_date' => $this->stringOrDefault($data['issue_date'] ?? null, (new DateTimeImmutable())->format('Y-m-d')),
                'due_date' => $this->nullableString($data['due_date'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'total' => array_sum(array_column($items, 'line_total')),
            ]);

            $this->invoices->saveItems($id, $items);

            return $id;
        });

        return $this->invoices->findWithItems($invoiceId) ?? throw new \RuntimeException('Just-created invoice vanished.');
    }

    /**
     * Only draft invoices can be edited — once sent, an invoice is a
     * record of what was billed, not something to silently rewrite.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws InvoiceException 404 if not found, 422 if not a draft or input is invalid.
     */
    public function update(int $id, array $data): array
    {
        $invoice = $this->requireInvoice($id);

        if ($invoice['status'] !== 'draft') {
            throw new InvoiceException('Only draft invoices can be edited.');
        }

        $client = $this->requireClient($data['client_id'] ?? $invoice['client_id']);
        $items = $this->validatedItems(is_array($data['items'] ?? null) ? $data['items'] : []);

        $this->connection->transaction(function () use ($id, $data, $client, $items) {
            $this->invoices->update($id, [
                'client_id' => $client['id'],
                'due_date' => $this->nullableString($data['due_date'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'total' => array_sum(array_column($items, 'line_total')),
            ]);

            $this->invoices->saveItems($id, $items);
        });

        return $this->invoices->findWithItems($id) ?? throw new \RuntimeException('Just-updated invoice vanished.');
    }

    /**
     * @return array<string, mixed>
     * @throws InvoiceException 404 if not found, 422 if the transition isn't allowed from the current status.
     */
    public function transition(int $id, string $newStatus): array
    {
        $invoice = $this->requireInvoice($id);
        $allowed = self::ALLOWED_TRANSITIONS[$invoice['status']] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new InvoiceException(
                "Cannot move an invoice from [{$invoice['status']}] to [{$newStatus}]."
            );
        }

        $this->invoices->update($id, ['status' => $newStatus]);

        return $this->invoices->findWithItems($id) ?? throw new \RuntimeException('Just-updated invoice vanished.');
    }

    /** @throws InvoiceException 404 if not found, 422 if not a draft. */
    public function delete(int $id): void
    {
        $invoice = $this->requireInvoice($id);

        if ($invoice['status'] !== 'draft') {
            throw new InvoiceException('Only draft invoices can be deleted.');
        }

        $this->invoices->delete($id);
    }

    /** @return array<string, mixed> */
    public function findOrFail(int $id): array
    {
        return $this->invoices->findWithItems($id) ?? throw new InvoiceException('Invoice not found.', 404);
    }

    /** @return array<string, mixed> */
    private function requireInvoice(int $id): array
    {
        $invoice = $this->invoices->find($id);

        if ($invoice === null) {
            throw new InvoiceException('Invoice not found.', 404);
        }

        return $invoice;
    }

    /** @return array<string, mixed> */
    private function requireClient(mixed $clientId): array
    {
        $client = is_numeric($clientId) ? $this->clients->find((int) $clientId) : null;

        if ($client === null) {
            throw new InvoiceException('Client not found.');
        }

        return $client;
    }

    /**
     * @param array<int, mixed> $rawItems
     * @return array<int, array{description: string, quantity: float, unit_price: float, line_total: float}>
     */
    private function validatedItems(array $rawItems): array
    {
        if ($rawItems === []) {
            throw new InvoiceException('At least one line item is required.');
        }

        $items = [];

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                throw new InvoiceException('Each item must be an object.');
            }

            $description = trim((string) ($raw['description'] ?? ''));
            $quantity = (float) ($raw['quantity'] ?? 0);
            $unitPrice = (float) ($raw['unit_price'] ?? 0);

            if ($description === '') {
                throw new InvoiceException('Each item needs a description.');
            }

            if ($quantity <= 0) {
                throw new InvoiceException('Item quantity must be greater than zero.');
            }

            if ($unitPrice < 0) {
                throw new InvoiceException('Item unit price cannot be negative.');
            }

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
            ];
        }

        return $items;
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
