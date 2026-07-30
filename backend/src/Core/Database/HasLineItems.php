<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Mixed into a Repository subclass whose rows own a child collection of
 * line items in a separate table (quote_items, invoice_items). Editing
 * replaces the whole set (delete-then-reinsert) rather than diffing
 * add/remove/update per item — simpler, and appropriate for line items,
 * which have no identity or audit value outside their parent document.
 *
 * Relies on $this->connection and $this->tenant, both protected on the
 * base Repository class this trait is meant to be used inside of.
 */
trait HasLineItems
{
    abstract protected function itemsTable(): string;

    abstract protected function itemsForeignKey(): string;

    /** @return array<string, mixed>|null The parent row with an added 'items' key, or null if not found. */
    public function findWithItems(int $id): ?array
    {
        $parent = $this->find($id);

        if ($parent === null) {
            return null;
        }

        $parent['items'] = $this->items($id);

        return $parent;
    }

    /** @return array<int, array<string, mixed>> */
    protected function items(int $parentId): array
    {
        return $this->connection->table($this->itemsTable())
            ->forTenant($this->tenant->id)
            ->where($this->itemsForeignKey(), '=', $parentId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /** @param array<int, array<string, mixed>> $items */
    protected function replaceItems(int $parentId, array $items): void
    {
        $this->connection->table($this->itemsTable())
            ->forTenant($this->tenant->id)
            ->where($this->itemsForeignKey(), '=', $parentId)
            ->delete();

        foreach (array_values($items) as $sortOrder => $item) {
            $this->connection->table($this->itemsTable())->forTenant($this->tenant->id)->insert(
                $item + [$this->itemsForeignKey() => $parentId, 'sort_order' => $sortOrder]
            );
        }
    }
}
