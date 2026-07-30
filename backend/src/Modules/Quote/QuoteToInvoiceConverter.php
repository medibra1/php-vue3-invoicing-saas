<?php

declare(strict_types=1);

namespace App\Modules\Quote;

use App\Core\Database\Connection;
use App\Modules\Invoice\InvoiceRepository;
use App\Modules\Shared\DocumentNumberGenerator;
use DateTimeImmutable;

/**
 * Only an accepted quote can become an invoice — copies quote + items
 * into a new invoice + invoice_items in one transaction. The quote
 * itself is untouched (stays 'accepted'); quotes.quote_id on the new
 * invoice row is what links them.
 */
final class QuoteToInvoiceConverter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly QuoteRepository $quotes,
        private readonly InvoiceRepository $invoices,
        private readonly DocumentNumberGenerator $numbers
    ) {
    }

    /**
     * @return array<string, mixed> The newly created invoice, with items.
     * @throws QuoteException 404 if the quote doesn't exist, 422 if it isn't accepted.
     */
    public function convert(int $quoteId): array
    {
        $quote = $this->quotes->findWithItems($quoteId);

        if ($quote === null) {
            throw new QuoteException('Quote not found.', 404);
        }

        if ($quote['status'] !== 'accepted') {
            throw new QuoteException('Only accepted quotes can be converted to an invoice.');
        }

        $invoiceId = $this->connection->transaction(function () use ($quote) {
            $id = (int) $this->invoices->create([
                'client_id' => $quote['client_id'],
                'quote_id' => $quote['id'],
                'number' => $this->numbers->next('invoices', 'INV'),
                'status' => 'draft',
                'issue_date' => (new DateTimeImmutable())->format('Y-m-d'),
                'due_date' => null,
                'notes' => $quote['notes'],
                'total' => $quote['total'],
            ]);

            $this->invoices->saveItems($id, array_map(
                static fn (array $item): array => [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ],
                $quote['items']
            ));

            return $id;
        });

        return $this->invoices->findWithItems($invoiceId) ?? throw new \RuntimeException('Just-created invoice vanished.');
    }
}
