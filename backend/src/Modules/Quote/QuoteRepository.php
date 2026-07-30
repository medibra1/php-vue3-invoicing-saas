<?php

declare(strict_types=1);

namespace App\Modules\Quote;

use App\Core\Database\HasLineItems;
use App\Core\Database\Repository;

final class QuoteRepository extends Repository
{
    use HasLineItems;

    protected function table(): string
    {
        return 'quotes';
    }

    protected function itemsTable(): string
    {
        return 'quote_items';
    }

    protected function itemsForeignKey(): string
    {
        return 'quote_id';
    }

    /** @param array<int, array<string, mixed>> $items */
    public function saveItems(int $quoteId, array $items): void
    {
        $this->replaceItems($quoteId, $items);
    }
}
