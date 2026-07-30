<?php

declare(strict_types=1);

namespace App\Modules\Quote;

use App\Core\Database\Connection;
use App\Modules\Client\ClientRepository;
use App\Modules\Shared\DocumentNumberGenerator;
use DateTimeImmutable;

/**
 * Business logic for quote creation/editing/status transitions —
 * QuoteController is a thin HTTP layer on top, same split as
 * AuthService/AuthController. The first Service layer since AuthService:
 * Client module was pure CRUD and didn't need one.
 */
final class QuoteService
{
    /** @var array<string, string[]> Allowed next statuses per current status. */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['sent'],
        'sent' => ['accepted', 'rejected', 'expired'],
        'accepted' => [],
        'rejected' => [],
        'expired' => [],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly QuoteRepository $quotes,
        private readonly ClientRepository $clients,
        private readonly DocumentNumberGenerator $numbers
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws QuoteException 422 if the client doesn't exist (or belongs to another tenant) or items are invalid.
     */
    public function create(array $data): array
    {
        $client = $this->requireClient($data['client_id'] ?? null);
        $items = $this->validatedItems(is_array($data['items'] ?? null) ? $data['items'] : []);

        $quoteId = $this->connection->transaction(function () use ($data, $client, $items) {
            $id = (int) $this->quotes->create([
                'client_id' => $client['id'],
                'number' => $this->numbers->next('quotes', 'QUO'),
                'status' => 'draft',
                'issue_date' => $this->stringOrDefault($data['issue_date'] ?? null, (new DateTimeImmutable())->format('Y-m-d')),
                'expiry_date' => $this->nullableString($data['expiry_date'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'total' => array_sum(array_column($items, 'line_total')),
            ]);

            $this->quotes->saveItems($id, $items);

            return $id;
        });

        return $this->quotes->findWithItems($quoteId) ?? throw new \RuntimeException('Just-created quote vanished.');
    }

    /**
     * Only draft quotes can be edited — once sent, a quote is a record
     * of what was offered, not something to silently rewrite.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws QuoteException 404 if not found, 422 if not a draft or input is invalid.
     */
    public function update(int $id, array $data): array
    {
        $quote = $this->requireQuote($id);

        if ($quote['status'] !== 'draft') {
            throw new QuoteException('Only draft quotes can be edited.', 422);
        }

        $client = $this->requireClient($data['client_id'] ?? $quote['client_id']);
        $items = $this->validatedItems(is_array($data['items'] ?? null) ? $data['items'] : []);

        $this->connection->transaction(function () use ($id, $data, $client, $items) {
            $this->quotes->update($id, [
                'client_id' => $client['id'],
                'expiry_date' => $this->nullableString($data['expiry_date'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'total' => array_sum(array_column($items, 'line_total')),
            ]);

            $this->quotes->saveItems($id, $items);
        });

        return $this->quotes->findWithItems($id) ?? throw new \RuntimeException('Just-updated quote vanished.');
    }

    /**
     * @return array<string, mixed>
     * @throws QuoteException 404 if not found, 422 if the transition isn't allowed from the current status.
     */
    public function transition(int $id, string $newStatus): array
    {
        $quote = $this->requireQuote($id);
        $allowed = self::ALLOWED_TRANSITIONS[$quote['status']] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new QuoteException(
                "Cannot move a quote from [{$quote['status']}] to [{$newStatus}]."
            );
        }

        $this->quotes->update($id, ['status' => $newStatus]);

        return $this->quotes->findWithItems($id) ?? throw new \RuntimeException('Just-updated quote vanished.');
    }

    /** @throws QuoteException 404 if not found, 422 if not a draft. */
    public function delete(int $id): void
    {
        $quote = $this->requireQuote($id);

        if ($quote['status'] !== 'draft') {
            throw new QuoteException('Only draft quotes can be deleted.', 422);
        }

        $this->quotes->delete($id);
    }

    /** @return array<string, mixed> */
    public function findOrFail(int $id): array
    {
        return $this->quotes->findWithItems($id) ?? throw new QuoteException('Quote not found.', 404);
    }

    /** @return array<string, mixed> */
    private function requireQuote(int $id): array
    {
        $quote = $this->quotes->find($id);

        if ($quote === null) {
            throw new QuoteException('Quote not found.', 404);
        }

        return $quote;
    }

    /** @return array<string, mixed> */
    private function requireClient(mixed $clientId): array
    {
        $client = is_numeric($clientId) ? $this->clients->find((int) $clientId) : null;

        if ($client === null) {
            throw new QuoteException('Client not found.');
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
            throw new QuoteException('At least one line item is required.');
        }

        $items = [];

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                throw new QuoteException('Each item must be an object.');
            }

            $description = trim((string) ($raw['description'] ?? ''));
            $quantity = (float) ($raw['quantity'] ?? 0);
            $unitPrice = (float) ($raw['unit_price'] ?? 0);

            if ($description === '') {
                throw new QuoteException('Each item needs a description.');
            }

            if ($quantity <= 0) {
                throw new QuoteException('Item quantity must be greater than zero.');
            }

            if ($unitPrice < 0) {
                throw new QuoteException('Item unit price cannot be negative.');
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
