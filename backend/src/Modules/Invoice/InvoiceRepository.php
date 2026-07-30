<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Core\Database\HasLineItems;
use App\Core\Database\Repository;

/**
 * Minimal — just enough for QuoteToInvoiceConverter (Phase 3) to land a
 * converted quote. Full Invoice CRUD (InvoiceController, PDF generation,
 * payment status updates) is Phase 4.
 */
final class InvoiceRepository extends Repository
{
    use HasLineItems;

    protected function table(): string
    {
        return 'invoices';
    }

    protected function itemsTable(): string
    {
        return 'invoice_items';
    }

    protected function itemsForeignKey(): string
    {
        return 'invoice_id';
    }

    /** @param array<int, array<string, mixed>> $items */
    public function saveItems(int $invoiceId, array $items): void
    {
        $this->replaceItems($invoiceId, $items);
    }
}
